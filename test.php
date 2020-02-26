<?php
date_default_timezone_set('Asia/Hong_Kong');
ini_set("display_errors", "On");
error_reporting(E_ALL && ~E_WARNING);
setlocale(LC_ALL, 'en_US.UTF-8'); //do not remove

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/tests/Testing.php";

print_r(new Testing2(1));

die();

$table = User::_table();


$q=User::Query()->where("username like :u or password like :u",["u"=>"a"]);

print_r($q->toArray());

die();


print_r($table->describe());
die();

$a = ["a" => 1, "b" => null];
//unset($a["b"]);
print_r($a);

return;

/*print_r(User::Query()->filter([
    "username" => "admin"
])->toArray()[0]->username);
die();
*/
foreach (User::Query()->select(["username"]) as $a) {
    print_r($a);
}
return;

print_r(Testing::__attribute());
return;
$a = new stdClass();
$st = Testing::_table()->where(["testing_id" => 100])->get();
$st->setFetchMode(PDO::FETCH_INTO, $a);
if ($st->fetch() === false) {
    echo "false";
} else {
    print_r($a);
}



return;
$t = new Testing(1);

$t->delete();


return;
$q = new R\DB\Query(Testing::__db(), "Testing");
$q->where(["name" => 1]);

print_r($q);
