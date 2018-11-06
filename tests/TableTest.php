<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{


    public function testCreate()
    {
        $db = new R\DB\PDO("raymond", "127.0.0.1", "root", "111111");

        $table = new R\DB\Table($db, "Testing");

        $this->assertInstanceOf(R\DB\Table::class, $table);
    }




}