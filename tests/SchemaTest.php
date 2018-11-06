<?
declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{

    public function testCreate()
    {
        $schema = new R\DB\Schema($db, "raymond");
        $this->assertInstanceOf(R\DB\Schema::class, $schema);
    }

}