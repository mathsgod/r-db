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
        $key = Testing::__key();
        $this->assertEquals("testing_id", $key);
    }

    public function test_table()
    {
        $table = Testing::__table();
        $this->assertEquals("Testing", $table->name);
    }

    public function test_attribute()
    {
        $attr = Testing::__attribute();
        $this->assertTrue(is_array($attr));
        $this->assertTrue(sizeof($attr) > 0);
    }

}