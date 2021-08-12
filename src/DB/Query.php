<?php

namespace R\DB;

use IteratorAggregate;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\AbstractHydrator;

use Traversable;

class Query extends Select implements IteratorAggregate, AdapterAwareInterface, Traversable
{
    use AdapterAwareTrait;

    protected $objectPrototype;
    protected $hydrator;

    public function __construct($table, $objectPrototype = null, AbstractHydrator $hydrator)
    {
        parent::__construct($table);
        $this->objectPrototype = $objectPrototype;
        $this->hydrator = $hydrator;
    }

    public function count()
    {
        $c = clone $this;
        $gatweay = new TableGateway($this->table, $this->adapter);
        $c->columns(["c" => new Expression("count(*)")]);
        $row = $gatweay->selectWith($c)->current();
        return $row["c"];
    }

    /**
     * @return ResultSetInterface
     */
    public function getIterator()
    {
        $rs = new HydratingResultSet($this->hydrator, $this->objectPrototype);
        //table columns
        $gateway = new TableGateway($this->table, $this->adapter, null, $rs);

        return $gateway->selectWith($this);
    }
}
