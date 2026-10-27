<?php

namespace R\DB\Tests\Integration;

use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\TestCase;
use R\DB\Model;
use R\DB\Paginator\Adapter;
use R\DB\Schema;

final class Record extends Model
{
    protected static $_table = 'RdbCompatibilityRecord';
}

final class OrmTest extends TestCase
{
    private Schema $schema;

    protected function setUp(): void
    {
        $database = getenv('RDB_TEST_DATABASE');
        if (!$database) {
            self::markTestSkipped('Set RDB_TEST_DATABASE and RDB_TEST_* connection variables for isolated MySQL tests.');
        }
        if (!str_starts_with($database, 'rdb_test_')) {
            self::fail('Use a disposable database whose name starts with rdb_test_.');
        }
        $this->schema = new Schema(
            $database, getenv('RDB_TEST_HOST') ?: '127.0.0.1',
            getenv('RDB_TEST_USER') ?: 'root', getenv('RDB_TEST_PASSWORD') ?: '',
            'utf8mb4', (int) (getenv('RDB_TEST_PORT') ?: 3306)
        );
        Record::SetSchema($this->schema);
        $this->schema->query('CREATE TABLE IF NOT EXISTS RdbCompatibilityRecord (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL DEFAULT "",
            payload JSON NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            upload TEXT NULL
        )');
        $this->schema->table('RdbCompatibilityRecord')->truncate();
    }

    protected function tearDown(): void
    {
        if (isset($this->schema)) {
            if ($this->schema->inTransaction()) {
                $this->schema->rollBack();
            }
            $this->schema->table('RdbCompatibilityRecord')->truncate();
        }
    }

    public function testTableCrudAndMetadata(): void
    {
        $table = $this->schema->table('RdbCompatibilityRecord');
        self::assertSame('id', $table->getPrimaryKey());
        $table->insert(['name' => 'Alpha']);
        $row = $table->first(['name' => 'Alpha']);
        self::assertSame('Alpha', $row['name']);
        $table->update(['name' => 'Beta'], ['id' => $row['id']]);
        self::assertSame('Beta', $table->first(['id' => $row['id']])['name']);
        $table->delete(['id' => $row['id']]);
        self::assertSame(0, $table->count());
    }

    public function testModelCrudAndJsonReferenceUpdates(): void
    {
        $model = Record::Create(['name' => '客戶', 'payload' => ['items' => [1]], 'enabled' => true]);
        $model->save();
        self::assertGreaterThan(0, $model->id);
        self::assertFalse($model->isDirty());
        self::assertFalse($model->wasChanged('name'));
        $loaded = Record::Get($model->id);
        self::assertSame('客戶', $loaded->name);
        self::assertSame(['items' => [1]], $loaded->payload);
        self::assertTrue($loaded->enabled);
        $loaded->payload['items'][] = 2;
        $loaded->name = 'updated';
        self::assertTrue($loaded->isDirty('payload'));
        self::assertSame('客戶', $loaded->getOriginal('name'));
        $loaded->save();
        self::assertTrue($loaded->wasChanged('name'));
        $reloaded = Record::Load($model->id);
        self::assertSame('updated', $reloaded->name);
        self::assertSame(['items' => [1, 2]], $reloaded->payload);
        self::assertSame(['items' => [1, 2]], json_decode(json_encode($reloaded), true)['payload']);
        $reloaded->delete();
        self::assertNull(Record::Get($model->id));
    }

    public function testQueryFilteringSortingPaginationAndCollections(): void
    {
        Record::Create(['name' => 'Alpha'])->save();
        Record::Create(['name' => 'Beta'])->save();
        self::assertSame(2, Record::Query()->count());
        self::assertSame('Beta', Record::Query()->filters(['name' => ['eq' => 'Beta']])->first()->name);
        $query = Record::Query();
        self::assertSame('Beta', $query->sort('name:DESC')->first()->name);
        self::assertSame('Alpha', $query->order('name ASC')->first()->name);
        self::assertSame('Beta', Record::Query()->order('name ASC')->limit(1)->offset(1)->toArray()[0]->name);
        $adapter = new Adapter(Record::Query()->order('name ASC'));
        self::assertCount(1, $adapter->getItems(0, 1));
        self::assertSame(2, $adapter->count());
        self::assertSame(['Alpha', 'Beta'], Record::Query()->order('name ASC')->map(static fn ($record) => $record->name)->toArray());
    }

    public function testTransactionRollback(): void
    {
        $this->schema->beginTransaction();
        $this->schema->table('RdbCompatibilityRecord')->insert(['name' => 'rollback']);
        self::assertTrue($this->schema->inTransaction());
        $this->schema->rollBack();
        self::assertSame(0, Record::Query()->count());
    }

    public function testUploadedFileUsesPsrInterface(): void
    {
        $file = new UploadedFile((new StreamFactory())->createStream('uploaded-content'), 16, UPLOAD_ERR_OK, 'test.txt', 'text/plain');
        $record = Record::Create(['name' => 'upload', 'upload' => $file]);
        $record->save();
        self::assertSame('uploaded-content', Record::Get($record->id)->upload);
    }
}
