<?php

namespace R\ORM;

use R\RSList;
use R\ObjectList;
use Traversable;

class Query extends \R\DB\Query
{
    public function __construct(string $class)
    {
        $this->class = $class;
        parent::__construct($class::__db(), $class::_table());
    }

    public function getIterator(): Traversable
    {
        $iterator = parent::getIterator();
        if ($this->select) {
            return new RSList($iterator);
        } else {
            return new RSList($iterator, $this->class);
        }
    }

    public function first()
    {
        $this->limit(1);
        $l = $this->getIterator();
        return $l->first();
    }

    public function delete()
    {
        return parent::delete()->execute();
    }

    public function insert()
    {
        return parent::insert()->execute();
    }

    public function update()
    {
        return parent::update()->execute();
    }

    public function truncate()
    {
        return parent::truncate()->execute();
    }

    public function filter(array $filter = [])
    {
        $this->where($filter);
        return $this;
    }
}
