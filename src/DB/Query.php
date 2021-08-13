<?php

namespace R\DB;

use Exception;
use IteratorAggregate;
use JsonSerializable;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\Pdo\Pdo;
use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\Adapter\Platform\Mysql;
use Laminas\Db\Sql\Platform\Platform;
use Laminas\Db\Sql\Select;
use PDOStatement;
use PHP\Util\QueryInterface;
use R\DataList;
use Laminas\Db\Sql\Predicate;

class Query implements IteratorAggregate, QueryInterface, JsonSerializable
{
    protected $_type = "SELECT";
    protected $_dirty = true;
    protected $step = [];
    protected $from = [];
    protected $set = [];
    protected $into = [];
    protected $join = [];
    protected $inner_join = [];
    protected $where = [];
    protected $orderby = [];
    protected $groupby = [];
    protected $limit;
    protected $offset;

    protected $values = [];

    protected $db = null;
    protected $select = null;

    protected $params = [];

    /** @var PDOStatement */
    protected $statement = null;

    protected $set_raw = [];

    protected $orderMap = [];
    protected $columns;

    public function __construct(Schema $db, string $table = null, string $ref = null)
    {
        $this->db = $db;
        if ($table) {
            $this->from[] = [$table, $ref];
            $this->table = $table;
        }
        $this->select = new Select($table);

        //   parent::__construct($table);
    }

    public function columns()
    {
    }

    public function setFetchMode(int $mode, string $classname, array $ctorargs): bool
    {
        return $this->statement->setFetchMode($mode, $classname, $ctorargs);
    }

    public function getIterator()
    {
        if ($this->statement === null || $this->_dirty) {
            $this->execute();
        }
        return $this->statement;
    }

    public function from($table, $ref = null)
    {
        foreach ($this->from as $f) {
            if ($f[0] == $table && $f[1] == $ref) {
                return $this;
            }
        }
        $this->from[] = [$table, $ref];
        return $this;
    }

    public function __toString()
    {
        return $this->select->getSqlString();
    }

    public function toList(array $bindParam = []): DataList
    {
        return new DataList($this->toArray($bindParam));
    }

    public function toArray(array $bindParam = [])
    {
        $params = [];
        foreach ($bindParam as $k => $v) {
            $params[":$k"] = $v;
        }

        $this->execute($params);

        return (array) iterator_to_array($this->getIterator());
    }


    public function errorInfo(): array
    {
        return $this->statement->errorInfo();
    }

    public function execute(array $input_parameters = [])
    {


        if ($this->_dirty) {

            $sql = $this->select->getSqlString($this->db->adatper->getPlatform());
            $statement = $this->db->adatper->createStatement($sql);
            $statement->prepare();

            $this->statement = $statement->getResource();

            /*     if (!$this->statement = $this->db->prepare($sql)) {
                $error = $this->db->errorInfo();
                throw new Exception("PDO SQLSTATE [" . $error[0] . "] " . $error[2] . " sql: $sql", $error[1]);
            } */
            $this->_dirty = false;
        }

        if (!$this->statement->execute($input_parameters)) {
            $error = $this->statement->errorInfo();
            throw new Exception("PDO SQLSTATE [" . $error[0] . "] " . $error[2] . " sql: $sql ", $error[1]);
        }
        return $this->statement;
    }

    /**
     * Create where clause
     *
     * @param  Where|\Closure|string|array|Predicate\PredicateInterface $predicate
     * @param  string $combination One of the OP_* constants from Predicate\PredicateSet
     * @return self Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function where($predicate, $combination = Predicate\PredicateSet::OP_AND)
    {
        $this->select->where($predicate, $combination);
        return $this;
    }

    public function setOrderMap(string $name, string $value)
    {
        $this->orderMap[$name] = $value;
        return $this;
    }

    public function orderBy($order)
    {
        $this->_dirty = true;
        if (!$order) return $this;
        if (is_array($order)) {
            foreach ($order as $k => $v) {
                $this->orderby[] = [$k, $v];
            }
            return $this;
        } else {
            $this->orderby[] = $order;
        }

        return $this;
    }

    public function groupBy($group)
    {
        $this->_dirty = true;
        if (!$group) return $this;
        if (is_array($group)) {
            foreach ($group as $k => $v) {
                $this->groupby[] = "$k $v";
            }
        } else {
            $this->groupby[] = $group;
        }

        return $this;
    }

    public function limit($limit)
    {
        $this->_dirty = true;
        if (is_array($limit)) { // page limit
            $this->limit =  $limit[1];
            $this->offset = ($limit[0] - 1) * $limit[1];
        } else {
            $this->limit = $limit;
        }

        return $this;
    }

    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function leftJoin(string $table, string $on)
    {
        $this->join[] = "$table on $on";
        return $this;
    }


    public function innerJoin(string $table, string $on)
    {
        $this->inner_join[] = "$table on $on";
        return $this;
    }


    public function select($query = null)
    {

        return $this;
        $this->_dirty = true;
        $this->_type = "SELECT";
        $this->select = $query;
        return $this;
    }

    public function insert()
    {
        $this->_dirty = true;
        $this->_type = "INSERT";
        return $this;
    }

    public function value($column, $value)
    {
        $this->columns[] = $column;
        $this->values[] = $value;
        return $this;
    }

    public function delete()
    {
        $this->_dirty = true;
        $this->_type = "DELETE";
        return $this;
    }

    public function count(string $query = "*"): int
    {
        $new = clone $this;
        $new->_dirty = true;
        $new->limit = null;
        $new->offset = null;
        $new->select(["count($query)"]);
        return $new->execute()->fetchColumn(0);
    }

    public function truncate()
    {
        $this->_dirty = true;
        $this->_type = "TRUNCATE";
        return $this;
    }

    public function map(callable $callback)
    {
        return array_map($callback, $this->toArray());
    }

    public function first()
    {
        $this->limit(1);
        return $this->execute()->fetch();
    }

    public function each(callable $callback)
    {
        array_walk($this->getIterator(), $callback);
    }


    public function filter(array $filter)
    {
        $this->_dirty = true;
        foreach ($filter as $field => $f) {
            if (is_array($f)) {

                if (array_keys($f) === range(0, count($f) - 1)) {
                    //sequential array


                    $i = 0;
                    $or = [];
                    foreach ($f as $v) {
                        $or[] = "`$field` = :{$field}_{$i}";
                        $this->params["{$field}_{$i}"] = $v;
                        $i++;
                    }
                    $this->where[] = implode(") or (", $or);
                    continue;
                }


                $i = 0;
                foreach ($f as $operator => $value) {
                    if ($operator == "between") {
                        $this->where[] = "`$field` between :{$field}_{$i}_from and :{$field}_{$i}_to";
                        $this->params["{$field}_{$i}_from"] = $value[0];
                        $this->params["{$field}_{$i}_to"] = $value[1];
                    } else {

                        $this->where[] = "`$field` $operator :{$field}_{$i}";
                        $this->params["{$field}_{$i}"] = $value;
                    }

                    $i++;
                }
            } else {
                if ($f === null) {
                    $this->where[] = "`$field` is null";
                } else {
                    $this->where[] = "`$field`=:$field";
                    $this->params[$field] = $f;
                }
            }
        }
        return $this;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
