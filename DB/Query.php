<?php
namespace DB;

class Query
{
    private $step = array();
    private $table = null;
    private $ref = null;
    private $from = array();
    private $set = array();
    private $join = array();
    private $where = array();
    private $orderby = array();
    private $groupby = array();

    private $db = null;

    private $bindParam = [];

    public function __construct($class, $ref = null)
    {
        $this->class = $class;
        $this->db = $class::__db();
        $this->table = $class::__table();
        $this->ref = ($ref) ? $ref : $this->table;
        $this->from[] = [$class, $ref];
    }

    public static function from($class, $ref = null)
    {
        return new Query($class, $ref);
    }

    public function set()
    {
        $set = func_get_arg(0);
        if (is_array($set)) {
            foreach ($set as $k => $v) {
                $this->set[] = "`{$k}`=" . $this->db()->quote($v);
            }
        } else {
            $this->set[] = $set;
        }
        return $this;
    }

    public function update()
    {
        $set = implode(",", $this->set);
        $table = $this->table;
        $sql = "update `{$table}` {$this->ref}";
        $sql .= $this->join();
        $sql .= " set $set";
        $sql .= $this->where();
        $this->db->query($sql);
    }

    public function where($where = null)
    {
        if (func_num_args() == 0) {
            if (!count($this->where)) return "";
            return " where (" . implode(") and (", $this->where) . ")";
        }
        if (is_null($where)) return $this;
        if (is_array($where)) {
            foreach ($where as $w) {
                if (is_null($w)) continue;
                if (is_array($w)) {
                    $this->where[] = $w[0];

                    if (is_array($w[1])) {
                        foreach ($w[1] as $k => $v) {
                            $this->bindParam[] = $v;
                        }
                    } else {
                        $this->bindParam[] = $w[1];
                    }
                } else {
                    $this->where[] = $w;
                }
            }
        } else {
            $this->where[] = $where;
        }

        return $this;
    }

    public function orderBy()
    {
        if (func_num_args() == 0) {
            if (!count($this->orderby)) return "";
            $orderby = array();
            foreach ($this->orderby as $values) {
                $orderby[] = $values[0] . " " . $values[1];
            }
            return " order by " . implode(",", $orderby);
        }

        if (func_get_arg(0) == "") return $this;

        $order = func_get_arg(0);
        if (is_array($order)) {
            $this->orderby[] = $order;
        } else {
            $this->orderby[] = func_get_args();
        }

        return $this;
    }

    public function groupBy()
    {
        if (func_num_args() == 0) {
            if (!count($this->groupby)) return "";
            return " group by " . implode(",", $this->groupby);
        }
        $group = func_get_arg(0);
        if (is_array($group)) {
            foreach ($group as $g) {
                $this->groupby[] = $g;
            }
        } else {
            $this->groupby[] = $group;
        }

        return $this;
    }

    public function limit()
    {
        if (func_num_args() == 0) {
            if (!count($this->limit)) return false;
            $s = " limit " . $this->limit[0];
            if ($this->limit[1] != "") $s .= "," . $this->limit[1];
            return $s;
        }
        $limit = func_get_arg(0);
        if (is_null($limit)) return $this;
        if (is_array($limit)) { // page limit
            $this->limit = array( ($limit[0] - 1) * $limit[1], $limit[1]);
        } else {
            $this->limit = func_get_args();
        }

        return $this;
    }

    public function join()
    {
        if (func_num_args() == 0) {
            if (!count($this->join)) return "";
            foreach ($this->join as $values) {
                $class = $values[0];
                $table = forward_static_call(array($this->class, "__table"));
                $join_table = forward_static_call(array($class, "__table"));
                $key = $class::__key();
                if ($values[2] != "") {
                    $join[] = " Left Join `" . $join_table . "` " . $values[1] . " on " . $values[2];
                } elseif ($values[1] != "") {
                    $join[] = " Left Join " . $join_table . " on " . $values[1];
                } else {
                    $join[] = " Left Join " . $join_table . " on `{$join_table}`.{$key}=`{$this->ref}`.{$key}";
                }
            }
            return implode(chr(10), $join);
        }
        $this->join[] = func_get_args();
        return $this;
    }

    public function select($query = null)
    {
        $ts = array();
        foreach ($this->from as $i => $f) {
            $class = $f[0];
            $t = "`" . $this->table . "`";
            if ($f[1] != "") $t .= " " . $f[1];
            $ts[] = $t;
        }

        $from = implode(",", $ts);
        if (is_null($query)) {
            $ref = $this->ref;
            $sql = "Select `$ref`.* From $from";
        } else {
            $sql = "Select $query From $from";
        }

        $sql .= $this->join();
        $sql .= $this->where();
        $sql .= $this->groupBy();
        $sql .= $this->orderBy();
        $sql .= $this->limit();

        if ($this->bindParam) {
            if ($sth = $this->db->prepare($sql)) {
                $sth->execute($this->bindParam);
                return $sth;
            }
        } else {
            return $this->db->query($sql);
        }
    }

    public function delete()
    {
        $class = $this->class;
        $table = $this->table;
        $sql = "Delete `{$table}`.* From `$table`";
        $sql .= $this->join();
        $sql .= $this->where();
        $sql .= $this->orderBy();
        $sql .= $this->limit();
        return $this->db->exec($sql);
    }

    public function count($query = "*")
    {
        $ts = array();
        foreach ($this->from as $i => $f) {
            $class = $f[0];
            $t = "`" . $this->table . "`";
            if ($f[1] != "") $t .= " " . $f[1];
            $ts[] = $t;
        }
        $from = implode(",", $ts);
        $sql = "Select count($query) From $from";
        $sql .= $this->join();
        $sql .= $this->where();
        try {
            if ($this->bindParam) {
                $sth = $this->db->prepare($sql);
                $sth->execute($this->bindParam);
                return $sth->fetchColumn(0);
            } else {
                $result = $this->db->query($sql)->fetchColumn(0);
            }

        } catch (\PDOException $e) {
            throw new \Exception($e->getMessage() . " " . $sql);
        }
        return $result;
    }
}