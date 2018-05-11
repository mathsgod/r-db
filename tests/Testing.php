<?

class Testing extends DB\Model
{
    public static function __db()
    {
        return new DB\PDO("raymond", "127.0.0.1", "root", "1111112");
    }

}
