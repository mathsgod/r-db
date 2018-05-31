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
        Testing::_table()->from()->truncate();

        $f = new Testing();
        $f->save();

        $f = Testing::First();
        $this->assertEquals($f, new Testing($f->testing_id));
    }

    /*public function test_save()
    {

        $count = Testing::_count();

        $t = new Testing();
        $t->save();
        $this->assertTrue($t->testing_id > 0);

        $this->assertEquals($count + 1, Testing::_count());


        $t->delete();

        $this->assertEquals($count, Testing::_count());




    }*/


}