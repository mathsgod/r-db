<?php

use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use R\DB\Schema;
use R\ORM\Model;

//date_default_timezone_set('Asia/Hong_Kong');
//ini_set("display_errors", "On");
//error_reporting(E_ALL && ~E_WARNING);
setlocale(LC_ALL, 'en_US.UTF-8'); //do not remove

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/tests/Testing.php";

echo User::Load(1)->UserList->count();
die();
print_r(get_class(User::Load(1)->UserList()));
die();
print_r(new Testing2(2));

die();
print_r(Testing::Query()->count());
die();

$t = new Testing();
$t->name = "abc";
$t->save();

die();


$s = Testing::__db();
echo $i = $s->exec("select * from User");
die();
$t = $schema->table("Testing");
$t->column("name")->rename("name1");

die();





return;
print_r(new User(1));
die();

print_r(User::_table()->describe());
die();

print_r(User::Query(["user_id" => 1])->toArray());

die();
$adatper = User::__db()->adatper;

$statmemt = $adatper->createStatement("Select * from User");
$statmemt->prepare();
$s = $statmemt->getResource();

$s->execute();
$s->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, "UserA", []);
print_r($s->fetchAll());

die();

print_r(User::Query(["user_id" => 1])->first());


return;
// Script start
$rustart = getrusage();

foreach (range(1, 100000) as $i) {
    UserList::Query()->toArray();
}


// Script end
function rutime($ru, $rus, $index)
{
    return ($ru["ru_$index.tv_sec"] * 1000 + intval($ru["ru_$index.tv_usec"] / 1000))
        -  ($rus["ru_$index.tv_sec"] * 1000 + intval($rus["ru_$index.tv_usec"] / 1000));
}


$ru = getrusage();
echo "This process used " . rutime($ru, $rustart, "utime") .
    " ms for its computations\n";
echo "It spent " . rutime($ru, $rustart, "stime") .
    " ms in system calls\n";
die();


print_r(User::Query()->where(function (Where $where) {
    $where->equalTo("user_id", 1);
})->toArray());

//print_r(User::Query()->toArray());

die();



print_r(Testing::Query()->setOrderMap("a", "(select count(*) from Testing)")->orderBy(["a" => "desc"])->sql());
die();


$t = new Testing(3);
print_R($t->Testing2());
exit();
$q = Testing::Query();
//$list = $q->toList();

//$list[0]->name = "1a";

//print_r($q->toArray());

$q->where("name like :name");


print_r($q->toArray(["name" => "%1%"]));





die();

$table = User::_table();


$q = User::Query()->where("username like :u or password like :u", ["u" => "a"]);

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
