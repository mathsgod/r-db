<?

class AuthLock extends R\DB\Model
{
    public static function __db()
    {
        return new R\DB\PDO("raymond", "127.0.0.1", "root", "111111");
    }

    public static function IsLock()
    {
        $ip=$_SERVER["REMOTE_ADDR"];
        $w[]=["ip=?",$ip];
        $w[]="value>=3";
        $w[]="date_add(time,Interval 180 second) > now()";
        return AuthLock::First($w);
    }
}
