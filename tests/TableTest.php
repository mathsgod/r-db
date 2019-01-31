<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{


    public function testCreate()
    {
        $db = new R\DB\Schema("raymond", "127.0.0.1", "root", "111111");
        $table = $db->table("Testing");
        $this->assertInstanceOf(R\DB\Table::class, $table);
    }


    public function testColumn()
    {
        $db = new R\DB\Schema("raymond", "127.0.0.1", "root", "111111");
        $table = $db->table("Testing");
        $testing_id_column = $table->column("testing_id");

        $this->assertInstanceOf(R\DB\Column::class, $testing_id_column);
    }


}