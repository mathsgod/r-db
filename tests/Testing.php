<?
require_once(__DIR__ . "/User.php");
require_once(__DIR__ . "/UserList.php");

class Testing extends R\ORM\Model
{
    public static function __db()
    {
        return new R\DB\Schema("raymond", "127.0.0.1", "root", "111111");
    }

}
