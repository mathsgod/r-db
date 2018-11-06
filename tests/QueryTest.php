<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

use R\DB\PDO;
use R\DB\Table;
use R\DB\Query;

final class QueryTest extends TestCase
{
    private function getQuery()
    {
        $db = new R\DB\PDO("raymond", "127.0.0.1", "root", "111111");;
        return new Query($db);
    }


    public function test_select()
    {
        $q = $this->getQuery();
        $this->assertInstanceOf(PDOStatement::class, $q->select()->from("Testing")->execute());
    }



    

    /*public function test_delete()
    {

        //$db = new PDO("raymond", "127.0.0.1", "root", "111111");
        //$q=new Query($db,"Testing");
    }*/

    /*public function test_insert(){

    }*/


}