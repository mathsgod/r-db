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
        return new Query($db, "Testing");
    }


    public function test_select()
    {
        $db = new PDO("raymond", "127.0.0.1", "root", "111111");
        $this->assertInstanceOf(PDOStatement::class, $db->from("Testing")->select());
    }

    public function test_count()
    {
        $q = $this->getQuery();

        $this->assertTrue($q->count() >= 0);
    }

    public function test_insert()
    {
        $q1 = $this->getQuery();
        $c1 = $q1->count();
        $q1->insert([]);
        $q2 = $this->getQuery();
        $c2 = $q2->count();
        $this->assertEquals($c1 + 1, $c2);


        $q = $this->getQuery();
        $q->orderBy("testing_id desc");
        $q->limit(1);
        $rs = $q->select();
        $r = $rs->fetch();
        $rs->closeCursor();

        $this->assertArrayHasKey("testing_id", $r);


        $q1 = $this->getQuery()->count();
        //test del
        $q = $this->getQuery();
        $w[] = ["testing_id=:testing_id", ["testing_id" => $r["testing_id"]]];
        $q->where($w);
        $q->delete();

        $this->assertEquals($q1 - 1, $this->getQuery()->count());
    }

    public function test_truncate()
    {
        $this->getQuery()->insert([]);

        $q = $this->getQuery();
        $q->truncate();
        $this->assertEquals(0, $this->getQuery()->count());



    }

    

    /*public function test_delete()
    {

        //$db = new PDO("raymond", "127.0.0.1", "root", "111111");
        //$q=new Query($db,"Testing");
    }*/

    /*public function test_insert(){

    }*/


}