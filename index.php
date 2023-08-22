<?php

use Laminas\Di\Injector;

require_once __DIR__ . '/vendor/autoload.php';

class Service
{
    public $name = 'Service';
    public function __construct()
    {
        echo "constructing service " . time() . "\n";
    }
}

/* $container = new League\Container\Container();

$container->add(Service::class);
 */

class User extends \R\DB\Model
{
    public $_service;
    public function __construct(Service $service)
    {
        $this->_service = $service;
        $service->name = 'Service 2';
    }
}

/* $container->add(User::class)->addArgument(Service::class);

print_r($container->get(User::class));
die();
 */

/* print_r(User::Query()->toArray());
die(); */

$container = new League\Container\Container();
$container->add(Service::class, new Service());



$injector = new Injector(null, $container);

print_R($injector->getContainer()->get(Service::class));
die;



print_r($injector->create(User::class));
