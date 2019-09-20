<?
require_once(__DIR__ . "/User.php");
require_once(__DIR__ . "/UserList.php");
require_once(__DIR__ . "/UserGroup.php");

class Testing extends R\ORM\Model
{
    public static function __db()
    {

        ///aaa
        return new R\DB\Schema("raymond", "127.0.0.1", "root", "111111");
    }

}
