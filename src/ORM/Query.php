<?php

namespace R\ORM;

use R\RSList;

class Query extends \R\DB\Query
{
    public function __construct(string $class)
    {
        $this->class = $class;
        parent::__construct($class::__db(), $class::_table());
    }

    /**
     * @return R\RSList
     */
    public function getIterator()
    {
        
        $iterator = parent::getIterator();
        
        if ($this->columns) {
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

    /**
     * Use Class::Query()->truncate()
     */
    public function truncate()
    {
        return parent::truncate()->execute();
    }
}
