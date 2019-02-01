<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{

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

    public function test_first()
    {
        Testing::_table()->truncate();

        $f = new Testing();
        $f->save();

        $f = Testing::First();
        $this->assertEquals($f, new Testing($f->testing_id));
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

    public function testFind()
    {
        $testing = Testing::Find();
        $this->assertInstanceOf(R\RSList::class, $testing);
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
        $this->assertInstanceOf(User::class, $u);


        $this->assertInstanceOf(R\ORM\Query::class, $u->UserList);

    }






}