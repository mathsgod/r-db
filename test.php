<?php
date_default_timezone_set('Asia/Hong_Kong');
ini_set("display_errors", "On");
error_reporting(E_ALL && ~E_WARNING);
setlocale(LC_ALL, 'en_US.UTF-8'); //do not remove

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/tests/Testing.php";


$t = new Testing(1);

$t->delete();


return;
$q = new R\DB\Query(Testing::__db(), "Testing");
$q->where(["name" => 1]);

print_r($q);