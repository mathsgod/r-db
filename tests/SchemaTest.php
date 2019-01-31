<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

use R\DB\Schema;
use R\DB\Table;
use R\DB\Query;

final class SchemaTest extends TestCase
{

    public function testCreate()
    {
        $db = new Schema("raymond", "127.0.0.1", "root", "111111");

        $this->assertInstanceOf(Schema::class, $db);
    }

    public function test_table()
    {
        $db = new Schema("raymond", "127.0.0.1", "root", "111111");
        $this->assertInstanceOf(Table::class, $db->table("Testing"));
    }

    public function test_from()
    {
        $db = new Schema("raymond", "127.0.0.1", "root", "111111");
        $this->assertInstanceOf(Query::class, $db->from("Testing"));
    }

    public function testTable()
    {
        $db = new Schema("raymond", "127.0.0.1", "root", "111111");
        $table = $db->table("Testing");
        $this->assertInstanceOf(Table::class, $table);

        $table = $db->table("Testing_NOT_EXIST");
        $this->assertNull($table);

        $table = $db->createTable("NEW_TABLE", [
            ["name" => "testing", "type" => "INT"]
        ]);
        $this->assertTrue($db->hasTable("NEW_TABLE"));

        $db->dropTable("NEW_TABLE");
        $this->assertFalse($db->hasTable("NEW_TABLE"));
    }

}