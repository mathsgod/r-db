<?
namespace R\ORM;

use R\RSList;

class Query extends \R\DB\Query
{
    public function __construct($class)
    {
        $this->class = $class;
        parent::__construct($class::__db(), $class::_table());
    }

    public function getIterator()
    {

        $iterator = parent::getIterator();

        return new RSList($iterator, $this->class);
    }

    public function first()
    {
        $this->limit(1);
        $l = $this->getIterator();
        return $l->first();
    }

    
}