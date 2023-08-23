<?php

use R\DB\Schema;

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


$container = new League\Container\Container();
$container->add(ServiceInterface::class, new Service);

class User extends \R\DB\Model
{
    public $_service;
    public function __construct(?ServiceInterface $service)
    {
        echo $service->name . "\n";
    }
}
Schema::Create()->setContainer($container);

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




