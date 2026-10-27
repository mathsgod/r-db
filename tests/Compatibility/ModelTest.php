<?php

namespace R\DB\Tests\Compatibility;

use PHPUnit\Framework\TestCase;
use R\DB\Model;
use R\DB\Table;

final class DefaultModel extends Model
{
    public static Table $testTable;

    public static function _table(): Table
    {
        return self::$testTable;
    }
}

final class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        $table = $this->createStub(Table::class);
        $table->method('describe')->willReturn([
            ['Field' => 'id', 'Type' => 'int', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null],
            ['Field' => 'enabled', 'Type' => 'tinyint(1)', 'Null' => 'NO', 'Key' => '', 'Default' => '0'],
            ['Field' => 'price', 'Type' => 'decimal(10,2)', 'Null' => 'NO', 'Key' => '', 'Default' => '1.25'],
            ['Field' => 'name', 'Type' => 'varchar(100)', 'Null' => 'NO', 'Key' => '', 'Default' => 'default'],
            ['Field' => 'payload', 'Type' => 'json', 'Null' => 'YES', 'Key' => '', 'Default' => null],
        ]);
        DefaultModel::$testTable = $table;
    }

    public function testDefaultValuesKeepTheirTypesWithoutBecomingDirty(): void
    {
        $model = new DefaultModel();
        self::assertSame(0, $model->id);
        self::assertFalse($model->enabled);
        self::assertSame(1.25, $model->price);
        self::assertSame('default', $model->name);
        self::assertNull($model->payload);
        self::assertSame([], $model->getDirty());
        self::assertFalse($model->isDirty());
        self::assertSame([], $model->getOriginal());
        self::assertNull($model->getOriginal('name'));
    }

    public function testNewModelTracksChangesWithoutAnOriginalRecord(): void
    {
        $model = DefaultModel::Create(['name' => '客戶', 'enabled' => true, 'payload' => ['a' => 1]]);
        self::assertTrue($model->isDirty());
        self::assertTrue($model->isDirty('name'));
        self::assertSame(['name' => '客戶', 'enabled' => true, 'payload' => ['a' => 1]], $model->getDirty());
        self::assertFalse($model->wasChanged());
        self::assertFalse($model->wasChanged('name'));
    }

    public function testJsonPropertiesCanStillBeModifiedByReference(): void
    {
        $model = DefaultModel::Create(['payload' => ['items' => [1]]]);
        $model->payload['items'][] = 2;
        self::assertSame(['items' => [1, 2]], $model->payload);
        self::assertSame(['items' => [1, 2]], $model->jsonSerialize()['payload']);
        self::assertSame(['items' => [1, 2]], iterator_to_array($model)['payload']);
    }
}
