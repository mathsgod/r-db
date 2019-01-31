<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

use R\DB\Schema;

final class ColumnTest extends TestCase
{
    public function testRename()
    {
        $db = new Schema("raymond", "127.0.0.1", "root", "111111");
        $table = $db->table("Testing");

        $col_name = $table->column("name");
        $col_name->rename("name1");

        $new_name = $table->column("name1");
        $this->assertEquals($new_name->Field, "name1");

        $new_name = $table->column("name1");
        $new_name->rename("name");

        $org_name = $table->column("name");

        $this->assertEquals($org_name->Field, "name");

    }


}