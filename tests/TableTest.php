<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

use R\DB\Column;
use R\DB\Query;


final class TableTest extends TestCase
{
    public function getTable()
    {
        $db = Testing::__db();
        return $db->table("Testing");
    }

    public function testCreate()
    {
        $db = Testing::__db();
        $table = $db->table("Testing");
        $this->assertInstanceOf(R\DB\Table::class, $table);
    }


    public function testColumn()
    {
        $db = Testing::__db();
        $table = $db->table("Testing");
        $testing_id_column = $table->column("testing_id");

        $this->assertInstanceOf(R\DB\Column::class, $testing_id_column);

        $col_not_exist = $table->column("testing_id_not_exist");
        $this->assertNull($col_not_exist);
    }

    public function testAddDropColumn()
    {
        $db = Testing::__db();
        $table = $db->table("Testing");
        $table->addColumn("new_column", "int");
        $new_column = $table->column("new_column");
        $this->assertInstanceOf(Column::class, $new_column);

        $table->dropColumn("new_column");
        $new_column = $table->column("new_column");
        $this->assertNull($new_column);
    }

    public function testInsert()
    {
        $table = $this->getTable();
        $table->truncate();
        $this->assertEquals($table->count(), 0);

        $table->insert(["name" => 'test1']);
        $this->assertEquals($table->count(), 1);

        $table->delete(["name" => "test1"]);
        $this->assertEquals($table->count(), 0);
    }

    public function testFind()
    {
        $table = $this->getTable();
        $query = $table->find();
        $this->assertInstanceOf(Query::class, $query);
    }

    public function testFirst()
    {
        $table = $this->getTable();
        $table->truncate();

        $table->insert(["name" => 'test1']);
        $result = $table->first("name='test1'");

        $this->assertArrayHasKey('name', $result);
    }

    public function testTop()
    {
        $table = $this->getTable();
        $table->truncate();

        $table->insert(["name" => 'test1']);
        $table->insert(["name" => 'test2']);
        $table->insert(["name" => 'test3']);

        $result = $table->top(2);

        $this->assertEquals(count($result), 2);
    }

}