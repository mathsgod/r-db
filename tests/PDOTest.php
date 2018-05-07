<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

final class PDOTest extends TestCase
{

    public function testCreate()
    {
        $db = new DB\PDO("raymond", "127.0.0.1", "root", "111111");

        $this->assertInstanceOf(DB\PDO::class, $db);
    }

}