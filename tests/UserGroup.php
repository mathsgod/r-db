<?php

class UserGroup extends R\ORM\Model
{
    public static function __db()
    {
        return new R\DB\Schema("raymond", "127.0.0.1", "root", "111111");
    }
}
