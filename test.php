<?php
require_once __DIR__ . "/vendor/autoload.php";



class User extends \R\ORM\Model
{
}
print_r(User::Query()->toArray());
