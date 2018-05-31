<?

class Testing extends R\DB\Model
{
    public static function __db()
    {
        return new R\DB\PDO("raymond", "127.0.0.1", "root", "111111");
    }

}
