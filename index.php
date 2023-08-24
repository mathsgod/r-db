<?php

use Laminas\Db\Sql\Ddl\Column\Integer;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use R\DB\Schema;
use R\DB\Stream;

use function R\DB\Q;

require_once __DIR__ . '/vendor/autoload.php';
interface ServiceInterface
{
}
class Service implements ServiceInterface
{
    public $name = 'Service';
    public function __construct()
    {
        echo "constructing service " . time() . "\n";
    }
}

class User
{
    public function __construct(ServiceInterface $service)
    {
        $this->service = $service;
    }
}



$container = new League\Container\Container();
$container->add(ServiceInterface::class, new Service);

Schema::Create()->setContainer($container);



print_R(Q(User::class)->get());

die();

Stream::Register(Schema::Create(), "db");
//rename("db://Testing5", "db://Testing4");

var_dump(file_exists("db://User"));


die();

$q = http_build_query([
    "columns" => [
        "user_id" => [
            "type" => "int",
        ],
        "username" => [
            "type" => "varchar",
            "length" => 255,
        ],
    ]
]);



/* var_dump(mkdir("db://Testing5?$q"));

rmdir("db://Testing5"); */
//("db://Testing/1", "db://Testing/2");

die();

//remove file
$q = http_build_query([
    "filters" => [
        "testing_id" => [
            "eq" => 2
        ]
    ],
]);

echo $q;
die;

unlink("db://Testing?$q");

die();

file_put_contents("db://User/11", json_encode([
    "username" => "power1"
]));


die();
$q = http_build_query([
    "fields" => ["user_id", "username"],
    /*     "filters" => [
        "user_id" => [
            "eq" => 1
        ]
    ],
 */    "sort" => "username:desc",
    "populate" => [
        "UserRole" => [
            "fields" => ["user_id"]
        ]
    ]

]);


print_r(json_decode(file_get_contents("db://User"), true));


die();

print_r(Schema::Create()->getMetadata());
die();



$container = new League\Container\Container();
$container->add(ServiceInterface::class, new Service);



User::Get(1);

die;




print_r($container->get(ServiceInterface::class));
print_r($container->get(ServiceInterface::class));
print_r($container->get(ServiceInterface::class));
die();

/* $container->add(User::class)->addArgument(Service::class);

print_r($container->get(User::class));
die();
 */

User::GetSchema()->setContainer($container);

User::Query()->toArray();
die();
$ref = new ReflectionClass(User::class);
$param = $ref->getConstructor()->getParameters()[1];

print_r($param->getAttributes()[0]->getName());


die();
