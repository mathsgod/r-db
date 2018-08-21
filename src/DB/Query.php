<?php
namespace R\DB;

use Exception;
use IteratorAggregate;

class Query implements IteratorAggregate 
{
    private $_type = "SELECT";
    private $_dirty = true;
    private $step = [];
    private $table = null;
    private $ref = null;
    private $from = [];
    private $set = [];
    private $into = [];
    private $join = [];
    private $where = [];
    private $orderby = [];
    private $groupby = [];

    private $values = [];

    private $db = null;
    private $select = [];

    private $params = [];

    private $statement = null;
    private $_iterator = null;


    public function __construct(PDO $db,$table)
    {
        $this->db = $db;
        if ($table) {
            $this->table = $table;
            $this->ref = ($ref) ? $ref : $this->table;
            $this->from[] = [$table, $table];
        }
    }

    public function getIterator()
    {
        if ($this->_iterator === null || $this->_dirty) {
            $this->_iterator = $this->execute();
        }
        return $this->_iterator;
    }

    public function from($table, $ref)
    {
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
            $sql = "INSERT";
            $sql .= " " . $this->insert;

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
                $sql .= " SET " . implode(",", $this->set);
            }

            return $sql;
        }

        if ($this->_type == "UPDATE") {
            $sql = "UPDATE `$this->table`";

            if ($this->set) {
                $sql .= " SET " . implode(",", $this->set);
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

        return $sql;
    }

    public function set($set)
    {
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

    public function update($table)
    {
        $this->_dirty = true;
        $this->_type = "UPDATE";
        $this->table = $table;
        return $this;
    }

    public function execute($input_parameters = [])
    {
        $params = array_merge($this->params, $input_parameters);

        if (!$this->statement = $this->db->prepare($this->sql())) {
            $error = $this->db->errorInfo();
            throw new Exception($error[2], $error[1]);
        }
        $this->_dirty = false;

        if ($params) {
            $this->statement->execute($params);
        } else {
            $this->statement->execute();
        }
        return $this->statement;
    }

    public function where($where, $bindParam)
    {
        $this->_dirty = true;
        if (is_null($where)) return $this;
        if (is_array($where)) {
            foreach ($where as $w) {
                if (is_array($w)) {
                    $this->where($w[0], $w[1]);
                } else {
                    $this->where($w);
                }
            }
            return $this;
        }

        $this->where[] = $where;
        if (isset($bindParam)) {
            if (is_array($bindParam)) {
                foreach ($bindParam as $k => $v) {
                    if(is_string($k)){
                        $this->params[$k] = $v;
                    }else{
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
        if (!$order) return;
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
        if (is_array($limit)) { // page limit
            $this->limit = ($limit[0] - 1) * $limit[1] . "," . $limit[1];
        } else {
            $this->limit = $limit;
        }

        return $this;
    }

    public function leftJoin($table, $on)
    {
/*        if (func_num_args() == 0) {
            if (!count($this->join)) return " ";
            foreach ($this->join as $values) {
                $class = $values[0];
                $table = forward_static_call(array($this->class, " __table "));
                $join_table = forward_static_call(array($class, " __table "));
                $key = $class::__key();
                if ($values[2] != " ") {
                    $join[] = " Left Join `" . $join_table . "` " . $values[1] . " on " . $values[2];
                } elseif ($values[1] != " ") {
                    $join[] = " Left Join " . $join_table . " on " . $values[1];
                } else {
                    $join[] = " Left Join " . $join_table . " on `{$join_table}` . {
            $key
        }
        = `{$this->ref}` . {
            $key
        }
        ";
                }
            }
            return implode(chr(10), $join);
        }*/


        $this->join[] = "$table on $on";
        return $this;
    }


    public function select($query = null)
    {
        $this->_type = "SELECT";
        if (is_null($query)) {
            $query = "*";
        }
        $this->select[] = $query;
        return $this;
    }

    public function insert($tbl)
    {
        $this->_type = "INSERT";
        $this->insert = $tbl;
        return $this;

        $values = implode(", ", array_map(function ($name) {
            return " : " . $name;
        }, $columns));
        $names = implode(", ", array_map(function ($name) {
            return " `" . $name . "` ";
        }, $columns));

        $table = $this->from[0][0];
        $this->sql = " INSERT INTO `$table` ({
            $names}) values({
            $values}) ";
        return $this->prepare();
    }

    public function value($column, $value)
    {
        $this->columns[] = $columns;
        $this->values[] = $value;
        return $this;
    }


    public function delete()
    {
        $this->_type = "DELETE";
        return $this;
    }

    public function count($query = " * ")
    {
        $this->select = [];
        $this->select[] = "count($query)";
        $s = $this->execute();
        return $s->fetchColumn(0);
    }

    public function truncate($table)
    {
        $sql = "TRUNCATE `$table` ";
        return $this->db->exec($sql);
    }

    public function map($callback)
    {
        return array_map($callback, $this->toArray());
    }

}