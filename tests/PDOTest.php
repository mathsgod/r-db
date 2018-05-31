<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

use R\DB\PDO;
use R\DB\Table;
use R\DB\Query;

final class PDOTest extends TestCase
{

    public function testCreate()
    {
        $db = new R\DB\PDO("raymond", "127.0.0.1", "root", "111111");

        $this->assertInstanceOf(R\DB\PDO::class, $db);
    }

    public function test_table()
    {
        $db = new PDO("raymond", "127.0.0.1", "root", "111111");
        $this->assertInstanceOf(Table::class, $db->table("Testing"));
    }

    public function test_from()
    {
        $db = new PDO("raymond", "127.0.0.1", "root", "111111");
        $this->assertInstanceOf(Query::class, $db->from("Testing"));
    }

}