<?php

namespace R\DB;

use Exception;
use IteratorAggregate;
use JsonSerializable;
use PDOStatement;
use PHP\Util\QueryInterface;
use R\DataList;

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

    public function __construct(Schema $db, string $table = null, string $ref = null)
    {
        $this->db = $db;
        if ($table) {
            $this->from[] = [$table, $ref];
            $this->table = $table;
        }
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
        return $this->sql();
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

    public function sql()
    {
        if ($this->_type == "SELECT") {
            $sql = "SELECT";
            if ($this->select) {
                $sql .= " " . implode(",", $this->select);
            } else {
                if (count($this->inner_join)) {
                    foreach ($this->from as $f) {
                        $sql .= " `" . $f[0] . "`.*";
                    }
                } else {
                    $sql .= " *";
                }
            }

            $from = [];
            foreach ($this->from as $f) {
                $from[] = "`" . $f[0] . "` " . $f[1];
            }
            $sql .= " FROM " . implode(",", $from);

            if ($this->join) {
                foreach ($this->join as $j) {
                    $sql .= " LEFT JOIN " . $j;
                }
            }

            if ($this->inner_join) {
                foreach ($this->inner_join as $j) {
                    $sql .= " INNER JOIN " . $j;
                }
            }

            if ($this->where) {
                $sql .= " WHERE (" . implode(") AND (", $this->where) . ")";
            }

            if ($this->groupby) {
                $sql .= " GROUP BY " . implode(",", $this->groupby);
            }

            if ($this->orderby) {
                $sql .= " ORDER BY " . implode(",", $this->orderby);
            }

            if ($this->limit) {
                $sql .= " LIMIT " . $this->limit;
            }

            if ($this->offset) {
                $sql .= " OFFSET " . $this->offset;
            }
            //
            return $sql;
        }


        if ($this->_type == "INSERT") {
            $sql = "INSERT INTO";
            $sql .= " `" . $this->table . "`";

            if ($this->into) {

                $names = implode(",", array_map(function ($name) {
                    return "`" . $name . "`";
                }, $this->into));

                $sql .= " ($names)";

                $values = implode(",", array_map(function ($name) {
                    return ":$name";
                }, $this->into));

                $sql .= " VALUES ($values)";
            }

            if ($this->set) {
                $this->params = [];
                $sql .= " SET ";
                $s = [];
                foreach ($this->set as $k => $v) {
                    $s[] = "`$k`=:$k";
                    if (is_array($v) || is_object($v)) {
                        $this->params[":$k"] = json_encode($v);
                    } else {
                        $this->params[":$k"] = $v;
                    }
                }
                $sql .= implode(",", $s);
            }

            return $sql;
        }

        if ($this->_type == "UPDATE") {
            $sql = "UPDATE `$this->table`";

            $sql .= " SET ";
            $s = [];
            foreach ($this->set as $k => $v) {
                $s[] = "`$k`=:$k";
                if (is_array($v) || is_object($v)) {
                    $this->params[":$k"] = json_encode($v);
                } else {
                    $this->params[":$k"] = $v;
                }
            }

            foreach ($this->set_raw as $set) {
                $s[] = $set;
            }

            $sql .= implode(",", $s);

            if ($this->where) {
                $sql .= " WHERE (" . implode(") AND (", $this->where) . ")";
            }

            return $sql;
        }

        if ($this->_type == "DELETE") {
            $sql = "DELETE";

            $from = [];
            foreach ($this->from as $f) {
                $from[] = "`$f[0]`";
            }
            $sql .= " FROM " . implode(",", $from);


            if ($this->where) {
                $sql .= " WHERE (" . implode(") AND (", $this->where) . ")";
            }

            return $sql;
        }

        if ($this->_type == "TRUNCATE") {
            $sql = "TRUNCATE `$this->table`";

            return $sql;
        }

        return "";
    }

    public function setRaw(array $set_raw = [])
    {
        $this->set_raw = $set_raw;
        return $this;
    }

    public function set(array $set = [])
    {
        $this->set = $set;
        return $this;
    }

    public function into($into)
    {
        $this->into[] = $into;
        return $this;
    }

    public function update()
    {
        $this->_dirty = true;
        $this->_type = "UPDATE";
        return $this;
    }

    public function errorInfo(): array
    {
        return $this->statement->errorInfo();
    }

    public function execute(array $input_parameters = []): PDOStatement
    {
        if ($this->_dirty) {
            $sql = $this->sql();
            if (!$this->statement = $this->db->prepare($sql)) {
                $error = $this->db->errorInfo();
                throw new Exception("PDO SQLSTATE [" . $error[0] . "] " . $error[2] . " sql: $sql", $error[1]);
            }
            $this->_dirty = false;
        }

        $params = array_merge($this->params, $input_parameters);

        if (!$this->statement->execute($params)) {
            $error = $this->statement->errorInfo();
            throw new Exception("PDO SQLSTATE [" . $error[0] . "] " . $error[2] . " sql: $sql params:" . json_encode($params), $error[1]);
        }
        return $this->statement;
    }

    public function where($where, $bindParam = null)
    {
        $this->_dirty = true;
        if (is_null($where)) return $this;
        if (is_array($where)) {
            foreach ($where as $k => $w) {

                if (is_string($k)) {
                    if ($w === null) {
                        $this->where("`$k` is null");
                    } else {
                        $this->where("`$k`=:$k", [$k => $w]);
                    }
                } elseif (is_array($w)) {
                    $this->where($w[0], $w[1]);
                } else {
                    $this->where($w);
                }
            }
            return $this;
        }

        $this->where[] = $where;
        if (func_num_args() == 2) {
            if (is_array($bindParam)) {
                foreach ($bindParam as $k => $v) {
                    if (is_string($k)) {
                        $this->params[$k] = $v;
                    } else {
                        $this->params[] = $v;
                    }
                }
            } else {
                if ($bindParam !== null) {
                    $this->params[] = $bindParam;
                }
            }
        }

        return $this;
    }

    public function orderBy($order)
    {
        $this->_dirty = true;
        if (!$order) return $this;
        if (is_array($order)) {
            foreach ($order as $k => $v) {
                $this->orderby[] = "$k $v";
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

    public function offset(int $offset)
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
