<?php

declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING);

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{

    
    function test_wasChanged()
    {
        Testing::Query()->delete();
        $t = Testing::Create([
            "name" => "testing"
        ]);


        $this->assertFalse($t->wasChanged("name"));

        $t->save();

        $this->assertTrue($t->wasChanged("name"));
    }

    function test_isDirty()
    {
        Testing::Query()->delete();
        Testing::Create([
            "name" => "testing"
        ])->save();

        $first = Testing::Query()->first();

        $this->assertFalse($first->isDirty());
        $this->assertFalse($first->isDirty("name"));

        $first->name = "changed";

        $this->assertTrue($first->isDirty());
        $this->assertTrue($first->isDirty("name"));
    }

    public function test_save_false()
    {
        $table = Testing2::_table();
        $table->truncate();
        $o = new Testing2();
        $o->b = true;
        $o->save();

        $o2 = new Testing2($o->testing2_id);
        $this->assertEquals(true, $o2->b);

        $o2->b = false;
        $o2->save();

        $o3 = new Testing2($o->testing2_id);
        $this->assertEquals(false, $o3->b);
    }


    public function test_save_int_not_null()
    {

        $table = Testing2::_table();
        $table->truncate();
        //insert
        $o = new Testing2();
        $o->name = "a";
        $o->int_null = 1;
        $o->save();
        $o = new Testing2($o->testing2_id);
        $this->assertEquals(1, $o->int_null);

        $o->int_null = "";
        $o->save();

        $o = new Testing2($o->testing2_id);
        $this->assertNull($o->int_null);
    }

    public function testCreate()
    {
        $t = new Testing();
        $this->assertInstanceOf(Testing::class, $t);
    }

    public function test_key()
    {
        $key = Testing::_key();
        $this->assertEquals("testing_id", $key);
    }

    public function test_table()
    {
        $table = Testing::_table();
        $this->assertEquals("Testing", $table->name);
    }

    public function test_attribute()
    {
        $attr = Testing::__attribute();
        $this->assertTrue(is_array($attr));
        $this->assertTrue(sizeof($attr) > 0);
    }


    public function test_save()
    {

        $t = new Testing();
        $t->name = "abc";
        $t->save();

        $n = new Testing($t->testing_id);
        $this->assertEquals("abc", $n->name);

        $t->name = "xyz";
        $t->save();

        $n = new Testing($t->testing_id);
        $this->assertEquals("xyz", $n->name);
    }

    public function testDelete()
    {
        //clear all
        Testing::_table()->truncate();
        $this->assertEquals(Testing::Query()->count(), 0);

        $t = new Testing();
        $t->name = "abc";
        $t->save();

        $this->assertEquals(Testing::Query(["name" => "abc"])->count(), 1);

        $n = new Testing($t->testing_id);
        $n->delete();

        $this->assertEquals(Testing::Query(["name" => "abc"])->count(), 0);
    }


    public function testUpdate()
    {
        Testing::_table()->truncate();
        $t = new Testing();
        $t->name = "abc";
        $t->save();


        $t = new Testing($t->testing_id);
        $t->name = "xyz";
        $t->save();
        $this->assertEquals(Testing::Query(["name" => "xyz"])->count(), 1);
    }

    public function testGet()
    {
        $u = new User(1);

        $a = $u->first_name;
        $this->assertInstanceOf(User::class, $u);
        $this->assertInstanceOf(R\DB\Query::class, $u->UserList);


        $ul = $u->UserList->first();
        $this->assertInstanceOf(UserList::class, $ul);


        $user = $ul->User();
        $this->assertInstanceOf(User::class, $user);


        $b = $user->first_name;

        $this->assertEquals($a, $b);
    }


    public function testCall()
    {
        $u = new User(1);
        $this->assertInstanceOf(R\DB\Query::class, $u->UserList);

        $ul = $u->UserList->First();
        $this->assertInstanceOf(UserList::class, $ul);


        $this->assertInstanceOf(R\DB\Query::class,  $ul->User);


        $ul = $u->UserList();
        $this->assertInstanceOf(ArrayObject::class, $ul);

        $ul = $ul[0];
        $this->assertInstanceOf(UserList::class, $ul);
        $user = $ul->User();
        $this->assertInstanceOf(User::class, $user);
    }

    public function testQuery()
    {
        $table = Testing::_table();
        $table->truncate();
        $table->insert(["name" => '1']);
        $table->insert(["name" => '2']);
        $table->insert(["name" => '3']);

        $query = Testing::Query();
        $this->assertEquals($query->count(), 3);

        $query = Testing::Query(["name" => 1]);
        $this->assertInstanceOf(R\DB\Query::class, $query);
        $this->assertEquals($query->count(), 1);

        Testing::_table()->delete(["name" => 1]);

        $this->assertEquals($query->count(), 0);

        $query = Testing::Query();
        $this->assertEquals($query->count(), 2);
    }

    public function testSaveNull()
    {
        $table = Testing2::_table();
        $table->truncate();
        //insert
        $o = new Testing2();
        $o->name = "null test";
        $o->null_field = null;
        $o->not_null_field = null;
        $o->save();

        $o1 = new Testing2($o->testing_id);
        $this->assertNull($o1->null_field);
        $this->assertEquals("", $o1->not_null_field);


        //update
        $o1->null_field = null;
        $o1->not_null_field = null;
        $o->save();

        $o2 = new Testing2($o1->testing_id);
        $this->assertNull($o2->null_field);
        $this->assertEquals("", $o2->not_null_field);
    }

    public function test_save_newid()
    {
        Testing2::_table()->truncate();

        $o = new Testing2();
        $o->name = ["a", "b", "c"];
        $o->save();

        $this->assertEquals(1, $o->testing2_id);
    }
    public function test_save_array()
    {
        Testing2::_table()->truncate();
        $o = new Testing2();
        $o->name = ["a", "b", "c"];
        $o->save();

        $o1 = new Testing2($o->testing2_id);
        $this->assertEquals("a,b,c", $o1->name);
    }

    public function test_bind()
    {

        $testing = new Testing();
        $testing->bind([
            "name" => "testing"
        ]);

        $this->assertEquals("testing", $testing->name);
    }

    public function test_bind2()
    {

        $testing = new Testing();
        $testing->bind([
            "name" => ["testing"]
        ]);
        $this->assertEquals("testing", $testing->name[0]);
    }
}
