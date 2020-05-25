<?php

declare(strict_types=1);
error_reporting(E_ALL && ~E_WARNING);

use PHPUnit\Framework\TestCase;

use R\DB\Schema;
use R\DB\Table;
use R\DB\Query;

final class QueryTest extends TestCase
{
    public function test_toArray()
    {
        $q = $this->getQuery();
        $q->truncate()->execute();
        $q = $this->getQuery();
        $q->set(["name" => 1]);
        $q->insert()->execute();
        $q = $this->getQuery();
        $q->set(["name" => 2]);
        $q->insert()->execute();
        $q = $this->getQuery();
        $q->set(["name" => 3]);
        $q->insert()->execute();


        $q = $this->getQuery();

        $q->where("name = :name");

        $o = $q->toArray(["name" => 1])[0];
        $this->assertEquals("1", $o["name"]);

        $o = $q->toArray(["name" => 2])[0];
        $this->assertEquals("2", $o["name"]);
    }

    public function test_toList()
    {
        $q = $this->getQuery();
        $list = $q->toList();
        $this->assertTrue($q->count() == $list->count());
    }

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

    public function test_insert_array()
    {
        $q = $this->getQuery();
        $q->truncate()->execute();

        $q = $this->getQuery();
        $q->set(["name" => [
            "a" => 1,
            "b" => 2
        ]])->insert();
        $q->execute();

        $q = $this->getQuery();
        $this->assertEquals($q->count(), 1);

        $q = $this->getQuery();
        $a = $q->first();
        $name = json_decode($a["name"], true);

        $this->assertEquals(1, $name["a"]);
        $this->assertEquals(2, $name["b"]);
    }
}
