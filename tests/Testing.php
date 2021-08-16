<?php
require_once(__DIR__ . "/Model.php");
require_once(__DIR__ . "/User.php");
require_once(__DIR__ . "/UserList.php");
require_once(__DIR__ . "/UserGroup.php");

class Testing extends Model
{
    public function getName()
    {
        return "a";
    }
}

class Testing2 extends Model
{
}
