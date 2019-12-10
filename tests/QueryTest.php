<?

declare(strict_types=1);
error_reporting(E_ALL && ~E_WARNING);

use PHPUnit\Framework\TestCase;

use R\DB\Schema;
use R\DB\Table;
use R\DB\Query;

final class QueryTest extends TestCase
{
    private function getQuery()
    {
        //Testing::Query
        $db = Testing::__db();
        return new Query($db, "Testing");
    }

    public function test_select()
    {
        $q = $this->getQuery();
        $this->assertInstanceOf(PDOStatement::class, $q->select()->execute());
    }

    public function testSQL()
    {

        $q = $this->getQuery();
        $q->select();
        $this->assertEquals($q->sql(), "SELECT * FROM `Testing` ");

        $q = $this->getQuery();
        $q->select(["testing_id"]);
        $this->assertEquals($q->sql(), "SELECT testing_id FROM `Testing` ");
    }

    public function testCount()
    {
        $q = $this->getQuery();
        $q->truncate()->execute();
        $this->assertEquals($q->count(), 0);

        $q = $this->getQuery();
        $q->set(["name" => "abc"])->insert();
        $q->execute();


        $q = $this->getQuery();
        $this->assertEquals($q->count(), 1);
    }

    public function test_delete()
    {
        $q = $this->getQuery();
        $q->truncate()->execute();
        $q = $this->getQuery();
        $q->set(["name" => 1]);
        $q->insert()->execute();
        $q = $this->getQuery();
        $q->set(["name" => 1]);
        $q->insert()->execute();
        $q = $this->getQuery();
        $q->set(["name" => 3]);
        $q->insert()->execute();

        $q = $this->getQuery();
        $this->assertEquals($q->count(), 3);


        $q = $this->getQuery();
        $q->where("name='1'");
        $q->delete()->execute();

        $q = $this->getQuery();
        $this->assertEquals($q->count(), 1);
    }

    public function testLeftJoin()
    {
        $q = UserGroup::Query()->leftJoin('UserList', 'UserList.usergroup_id=UserGroup.usergroup_id');
        $q->where("UserList.user_id=1");
        $this->assertEquals($q->count(), 1);
    }

    public function testUpdate()
    {
        $q = $this->getQuery();
        $q->truncate()->execute();

        $this->getQuery()->set(["name" => 1])->insert()->execute();

        $q = $this->getQuery()->update()->set(["name" => 2])->where(["testing_id" => 1]);
        $this->assertEquals($q->sql(), "UPDATE `Testing` SET `name`=:name WHERE (`testing_id`=:testing_id)");

        $q->execute();
    }

    public function testLimit()
    {
        $q = $this->getQuery();
        $q->limit(1);
        $q->offset(2);

        $this->assertEquals($q->sql(), "SELECT * FROM `Testing`  LIMIT 1 OFFSET 2");
    }
}
