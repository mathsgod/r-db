<?

declare(strict_types=1);
error_reporting(E_ALL && ~E_WARNING);

use PHPUnit\Framework\TestCase;

use R\DB\Schema;
use R\DB\Table;
use R\DB\Query;

final class SchemaTest extends TestCase
{



    public function testCreate()
    {
        $db = Testing::__db();
        $this->assertInstanceOf(Schema::class, $db);
    }

    public function test_table()
    {
        $db = Testing::__db();
        $this->assertInstanceOf(Table::class, $db->table("Testing"));
    }

    public function test_from()
    {
        $db = Testing::__db();
        $this->assertInstanceOf(Query::class, $db->from("Testing"));
    }

    public function testTable()
    {
        $db = Testing::__db();
        $table = $db->table("Testing");
        $this->assertInstanceOf(Table::class, $table);

        /*  $table = $db->table("Testing_NOT_EXIST");
        $this->assertNull($table);*/

        $table = $db->createTable("NEW_TABLE", [
            ["name" => "testing", "type" => "INT"]
        ]);
        $this->assertTrue($db->hasTable("NEW_TABLE"));

        $db->dropTable("NEW_TABLE");
        $this->assertFalse($db->hasTable("NEW_TABLE"));
    }

    public function testPrepare()
    {
        $s = Testing::__db();
        $sth = $s->prepare("select * from User");
        $this->assertInstanceOf(\PDOStatement::class, $sth);
    }

    public function testExec()
    {
        $s = Testing::__db();
        $i = $s->exec("select * from User");
        $this->assertTrue($i === 0);
    }

    public function testQuery()
    {
        $s = Testing::__db();
        $r = $s->query("select * from Testing");
        foreach ($r as $ss) {
            $this->assertTrue(is_array(($ss)));
        }
    }
}
