<?php
date_default_timezone_set('Asia/Hong_Kong');
ini_set("display_errors", "On");
error_reporting(E_ALL && ~E_WARNING);
setlocale(LC_ALL, 'en_US.UTF-8'); //do not remove

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/tests/Testing.php";
require_once __DIR__ . "/tests/AuthLock.php";

$db=Testing::__db();;
$r=$db->query("select testing_id from Testing order by testing_id desc")->fetch();


$testing_id=$r['testing_id'];

$t = Testing::_table();
$q = $t->from();
$w[]=["testing_id=:testing_id", ["testing_id" => $testing_id]];
$q->where($w);
$q->delete();



die();

print_r(AuthLock::IsLock());

return;
$w[] = ["ip>=?", "127.0.0.1"];
$w[] = "value>=3";
$w[] = "date_add(time,Interval 180 second) > now()";


print_r(AuthLock::Find(null, null, 1));

return;
print_r(Testing::_count());
return;
foreach (Testing::Find() as $t) {
    print_r($t);
}
die();



$db = new R\DB\PDO("raymond", "127.0.0.1", "root", "111111");

foreach ($db->from('Testing')->select() as $i) {
    print_r($i);
}
return;

print_r($table);
die();
print_r($table->_find());

//$table = new DB\Table($db, "Testing");
//print_r($table->first());