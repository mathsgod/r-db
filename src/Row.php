<?php

namespace R\DB;


use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Db\RowGateway\RowGatewayInterface;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\ObjectPropertyHydrator;

class Row implements RowGatewayInterface, AdapterAwareInterface
{
    use AdapterAwareTrait;

    protected $table;

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    public function save()
    {
        $gateway = new TableGateway($this->table->name, $this->adapter);
        $key = $this->table->getPrimaryKey();

        $hydrator = new ObjectPropertyHydrator;
        $set = $hydrator->extract($this);
        $gateway->update($set, [$key => $this->$key]);
    }

    public function delete()
    {
        $gateway = new TableGateway($this->table->name, $this->adapter);
        $key = $this->table->getPrimaryKey();
        $gateway->delete([$key => $this->$key]);
    }
}
