<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

use R\DB\Schema;
use R\DB\Table;
use R\DB\Query;

final class QueryTest extends TestCase
{
    private function getQuery()
    {
        $db = Testing::__db();
        return new Query($db, "Testing");
    }

    public function test_select()
    {
        $q = $this->getQuery();
        $this->assertInstanceOf(PDOStatement::class, $q->select()->from("Testing")->execute());
    }

    public function testSQL()
    {

        $q = $this->getQuery();
        $q->from("Testing");
        $q->select();
        $this->assertEquals($q->sql(), "SELECT * FROM `Testing` ");

        $q = $this->getQuery();
        $q->from("Testing");
        $q->select(["testing_id"]);
        $this->assertEquals($q->sql(), "SELECT testing_id FROM `Testing` ");
    }

    public function testCount()
    {
        $q = $this->getQuery();
        $q->truncate();
        $this->assertEquals($q->count(), 0);

        $q = $this->getQuery();
        $q->set(["name" => "abc"])->insert();
        $q->execute();

        
        $q = $this->getQuery();
        $this->assertEquals($q->count(), 1);

    }

    /*public function test_delete()
    {

        //$db = new PDO("raymond", "127.0.0.1", "root", "111111");
        //$q=new Query($db,"Testing");
    }*/

    /*public function test_insert(){

    }*/


}