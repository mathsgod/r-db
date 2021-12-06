<?php

use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Db\Sql\Ddl\Column\Column;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use R\DB\Event\AfterDelete;
use R\DB\Event\BeforeInsert;
use R\DB\Model;
use Symfony\Component\Validator\Validation;


//date_default_timezone_set('Asia/Hong_Kong');
//ini_set("display_errors", "On");
//error_reporting(E_ALL && ~E_WARNING);
setlocale(LC_ALL, 'en_US.UTF-8'); //do not remove

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/tests/Testing.php";

$schema = Model::GetSchema();


$schema->beginTransaction();
$ug = new UserGroup();
$ug->name = "Test";
$ug->delete();
$schema->rollback();



die();
$dispatcher = $schema->eventDispatcher();
$dispatcher->subscribeTo(BeforeInsert::class, function (BeforeInsert $event) {
    echo "1";
    print_r($event);
});

$dispatcher->subscribeTo(BeforeInsert::class, function (BeforeInsert $event) {
    echo "2";
    print_r($event);
});

$dispatcher->subscribeTo(AfterDelete::class, function (AfterDelete $event) {
    print_r($event);
});


$d = UserGroup::Create(['name' => 'test1']);
$d->save();
$d->delete();;


$ug = UserGroup::Query();




die();


$validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
$schema->setDefaultValidator($validator);

$ugs = UserGroup::Query()->toArray();
$ug = $ugs[0];
$ug->name = "";
$ug->save();


die();


print_r(new User(1));
die();

print_r(User::Load(1)->UserLog->delete());
die();

foreach (Testing::_table()->columns() as $column) {
    print_r($column->getMetaData());
    die();
}
die();


foreach ($rows as $row) {
    $row->delete();
    die();
}


die();
$db = Model::GetSchema();
foreach ($db->getTables() as $t) {
    echo $t->name;
}

die();
$res = $db->alterTable("Testing", function (AlterTable $table) {
    $column = new Column("test_abc");


    $table->addColumn($column);
});

print_R($res);

die();





$db = Model::GetSchema();

$sql    = new  Sql($db->getDbAdatpter());
$select = $sql->select();
$select->from('foo');
$select->where(['id' => 2]);
$sql->prepareStatementForSqlObject($select);




print_R($db->query("Select * from User")->fetchAll());

die();
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


$s = Testing::GetSchema();
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
$adatper = User::GetSchema()->adatper;

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
$q = new R\DB\Query(Testing::GetSchema(), "Testing");
$q->where(["name" => 1]);

print_r($q);
