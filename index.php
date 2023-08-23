<?php

use R\DB\Schema;
use R\DB\Stream;

use function R\DB\Q;

require_once __DIR__ . '/vendor/autoload.php';

/* class User
{
}

class UserRole
{
}
 */

Stream::Register(Schema::Create(), "db");

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


print_r(json_decode(file_get_contents("db://User?meta=1"), true));


die();

print_r(Schema::Create()->getMetadata());
die();


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
