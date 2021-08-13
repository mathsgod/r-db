<?php

class Model extends R\DB\Model
{
    protected static $_db;
    public static function __db()
    {
        if (self::$_db) return self::$_db;
        return self::$_db = new R\DB\Schema("raymond", "127.0.0.1", "root", "111111");
    }
}
