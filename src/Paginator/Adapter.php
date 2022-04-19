<?php

namespace R\DB\Paginator;

use ArrayObject;
use Laminas\Paginator\Adapter\AdapterInterface;
use R\DB\Query;

class Adapter implements AdapterInterface
{
    private $query;

    public function __construct(Query $query)
    {
        $this->query = $query;
    }

    function getItems($offset, $itemCountPerPage)
    {
        $this->query->offset($offset);
        $this->query->limit($itemCountPerPage);
        return new ArrayObject($this->query->toArray());
    }

    function count(): int
    {
        return $this->query->count();
    }
}
