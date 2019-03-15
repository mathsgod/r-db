<?php
namespace R\DB;

use Exception;
use IteratorAggregate;

class Query implements IteratorAggregate
{
    private $_type = "SELECT";
    private $_dirty = true;
    private $step = [];
    private $from = [];
    private $set = [];
    private $into = [];
    private $join = [];
    private $where = [];
    private $orderby = [];
    private $groupby = [];

    private $values = [];

    private $db = null;
    private $select = ["*"];

    private $params = [];

    private $statement = null;
    public function __construct(Schema $db, $table = null, $ref = null)
    {
        $this->db = $db;
        if ($table) {
            $this->from[] = [$table, $ref];
            $this->table = $table;
        }
    }

    public function setFetchMode($mode, $classname, $ctorargs)
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

    public function toArray()
    {

        return iterator_to_array($this->getIterator());
    }

    public function sql()
    {
        if ($this->_type == "SELECT") {
            $sql = "SELECT";
            $sql .= " " . implode(",", $this->select);
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
                    $this->params[":$k"] = $v;
                }
                $sql .= implode(",", $s);
            }

            return $sql;
        }

        if ($this->_type == "UPDATE") {
            $sql = "UPDATE `$this->table`";

            if ($this->set) {
                $sql .= " SET ";
                $s = [];
                foreach ($this->set as $k => $v) {
                    $s[] = "`$k`=:$k";
                    $this->params[":$k"] = $v;
                }
                $sql .= implode(",", $s);
            }

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

        return $sql;
    }

    public function set($set = [])
    {
        $this->set = $set;
        return $this;
        if (is_array($set)) {
            foreach ($set as $k => $v) {
                if ($v === null) {
                    $this->set[] = "`$k`=null";
                } else {
                    $this->set[] = "`$k`=" . $this->db->quote($v);
                }

            }
            return $this;
        }
        $this->set[] = $set;
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

    public function errorInfo()
    {
        return $this->statement->errorInfo();
    }

    public function execute($input_parameters = [])
    {
        if ($this->_dirty) {
            $sql = $this->sql();
            if (!$this->statement = $this->db->prepare($sql)) {
                $error = $this->db->errorInfo();
                throw new Exception($error[2] . " sql: $sql", $error[1]);
            }
            $this->_dirty = false;
        }

        $params = array_merge($this->params, $input_parameters);
        if (!$this->statement->execute($params)) {
            $error = $this->statement->errorInfo();
            throw new Exception($error[2] . " sql: $sql params:" . json_encode($params), $error[1]);
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
                    $this->where("`$k`=:$k", [":$k" => $w]);
                } elseif (is_array($w)) {
                    $this->where($w[0], $w[1]);
                } else {
                    $this->where($w);
                }
            }
            return $this;
        }

        $this->where[] = $where;
        if (func_num_args()==2) {
            if (is_array($bindParam)) {
                foreach ($bindParam as $k => $v) {
                    if (is_string($k)) {
                        $this->params[$k] = $v;
                    } else {
                        $this->params[] = $v;
                    }

                }
            } else {
                $this->params[] = $bindParam;
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
            $this->limit = ($limit[0] - 1) * $limit[1] . "," . $limit[1];
        } else {
            $this->limit = $limit;
        }

        return $this;
    }

    public function leftJoin($table, $on)
    {
/*        if ($this->join) {
            foreach ($this->join as $values) {

                $table = $this->db->table($this->table);
                $join_table = $this->db->table($values[0]);
                $key = $join_table->keys()[0];

                if ($values[2] != " ") {
                    $join[] = " Left Join `" . $join_table . "` " . $values[1] . " on " . $values[2];
                } elseif ($values[1] != " ") {
                    $join[] = " Left Join " . $join_table . " on " . $values[1];
                } else {
                    $join[] = " Left Join " . $join_table . " on `{$join_table}`.{$key}=`{$this->ref}`.{$key}";
                }
            }
            $sql .= implode("\n", $join);
        }*/

        $this->join[] = "$table on $on";
        return $this;
    }


    public function select($query = null)
    {
        $this->_dirty = true;
        $this->_type = "SELECT";
        if (is_null($query)) {
            $query = ["*"];
        }
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
        $this->columns[] = $columns;
        $this->values[] = $value;
        return $this;
    }

    public function delete()
    {
        $this->_dirty = true;
        $this->_type = "DELETE";
        return $this;
    }

    public function count($query = " * ")
    {
        $this->_dirty = true;
        $this->select = [];
        $this->select[] = "count($query)";
        $statement = $this->execute();
        return $statement->fetchColumn(0);
    }

    public function truncate()
    {
        $this->_dirty = true;
        $this->_type = "TRUNCATE";
        return $this;
    }

    public function map($callback)
    {
        return array_map($callback, $this->toArray());
    }

    public function first()
    {
        $this->limit(1);
        return $this->getIterator()->current();
    }

    public function each(callable $callback)
    {
        array_walk($this->getIterator(), $callback);
    }

}