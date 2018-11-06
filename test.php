<?php
date_default_timezone_set('Asia/Hong_Kong');
ini_set("display_errors", "On");
error_reporting(E_ALL && ~E_WARNING);
setlocale(LC_ALL, 'en_US.UTF-8'); //do not remove

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/tests/Testing.php";
require_once __DIR__ . "/tests/User.php";
require_once __DIR__ . "/tests/AuthLock.php";

foreach(User::_table()->find() as $u){
    print_r($u);
}
return;
print_r(User::_table()->find()->map(function ($o) {
    return ["a" => $o["user_id"]];
}));

return;

print_r(User::_table()->select("count(*),status")->groupBy("status")->toArray());
return;

$w = [];
$w[] = "status=0";

print_r(User::Find($w, ["user_id" => "desc"]));
return;

$t = new Testing(1);
$t->name = "abc";
$t->save();

print_R($t);
return;

$t = new Testing(1);

print_r($t);
return;
$t->save();


return;
$t = Testing::_table();

print_r(iterator_to_array($t->select()->where("testing_id=2")));
die();

$t->delete()->where("testing_id=1")->execute();

return;


$q = $t->select()->where("name1=:name1");
foreach ($q->execute(["name1" => 1]) as $r) {
    print_r($r);
}

$t->find()->where("testing_id=1")->update();

//$t->insert()



return;
$t = new Testing();
$t->name1 = "t";
$t->save();

print_r($t);

return;
$t = new R\DB\Table(Testing::__db(), "Testing");
print_r($t->count());

return;
$t->update(["name1" => 2], "testing_id=1");

return;
foreach ($t->select() as $a) {
    print_r($a);
}
return;

$q = new R\DB\Query(Testing::__db());


$rs = $q->select()->from("Testing")->limit(2)->execute();

foreach ($rs as $r) {
    print_r($r);
}


return;

$t = new R\DB\Table(Testing::__db(), "Testing");
$t->insert([
    "name1" => 1
]);
return;



//$q->insert("Testing")->set(["name"])

//echo $q->select("count(*)")->from("Testing")->sql();

return;
//echo $q->insert("Testing")->set("name1=:name1")->sql();
echo $q->update("Testing")
    ->where("testing_id=:testing")
    ->set("name1=:name1")
    ->set("test3=:test3")->sql();

//->execute(["name1"=>1]);
//->execute(["name" => 1]);
return;



$q = $q->insert("Testing")->into("name1");
foreach (range(1, 100) as $i) {
    $q->execute(["name1" => $i]);
}


//print_r($q->select()->from("Testing")->where("testing_id=1")->execute());
return;



//$q->from("Testing")->where("testing_id=1")->set("name1=:name1")->update()->execute(["name" => 1]);

$q->update("Tesing")->where("testing_id=1")->set("name1=:name1")->execute();
return;

$s = $q->from("User")->where("user_id=?")->select()->execute([2]);
foreach ($s as $k) {
    print_r($k);
}

return;

$db = Testing::__db();;
$r = $db->query("select testing_id from Testing order by testing_id desc")->fetch();




$testing_id = $r['testing_id'];

$t = Testing::_table();
$q = $t->from();
$w[] = ["testing_id=:testing_id", ["testing_id" => $testing_id]];
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