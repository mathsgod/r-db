<?php

namespace DB;

class Table
{
    private $db;
    public $name;
    private $sql;
    private $where = [];
    private $bindParam = [];
    private $join = [];
    private $groupby = [];
    private $having = [];
    private $logger = null;

    public function __construct($db, $name, $logger)
    {
        $this->db = $db;
        $this->name = $name;
        $this->logger = $logger;
    }

    public function dropColumn($name)
    {
        $sql = "ALTER TABLE `{$this->name}` DROP COLUMN `$name`";
        $this->sql = $sql;
        return $this->db->exec($sql);
    }

    public function addColumn($name, $type, $constraint)
    {
        $sql = "ALTER TABLE `{$this->name}` ADD COLUMN `$name` $type $constraint";
        $this->sql = $sql;
        return $this->db->exec($sql);
    }

    public function truncate()
    {
        $this->sql = "TRUNCATE `{$this->name}`";
        return $this->db->exec($this->sql);
    }

    public function columns($field)
    {
        if ($field) {
            $sth = $this->db->query("SHOW COLUMNS FROM `{$this->name}` WHERE Field='$field'");
            $sth->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, "\DB\Column", [$this]);
            return $sth->fetch();
        }

        $sth = $this->db->query("SHOW COLUMNS FROM `$this->name`");
        $sth->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, "\DB\Column", [$this]);
        return $sth->fetchAll();
    }

    public function __toString()
    {
        return $this->name;
    }

    public function describe()
    {
        $sql = "DESCRIBE `{$this->name}`";
		//		$sql .= $this->where();
        $sth = $this->db->query($sql)->fetchAll();
        return $sth;
    }

    public function keys()
    {
        $ret = array_filter($this->describe(), function ($o) {
            return $o["Key"] == "PRI";
        });

        return array_map(function ($o) {
            return $o["Field"];
        }, $ret);

    }

    public function insert($records = [])
    {
        $names = array_keys($records);
        $values = implode(",", array_map(function ($name) {
            return ":" . $name;
        }, $names));
        $names = implode(",", array_map(function ($name) {
            return "`" . $name . "`";
        }, $names));

        $stm = $this->db->prepare("INSERT INTO `$this->name` ({$names}) values ({$values})");
        $stm->execute($records);
        return $stm;
    }

    public function replace($records = [])
    {
        $names = array_keys($records);
        $values = implode(",", array_map(function ($name) {
            return ":" . $name;
        }, $names));
        $names = implode(",", array_map(function ($name) {
            return "`" . $name . "`";
        }, $names));
        return $this->db->prepare("REPLACE INTO `$this->name` ({$names}) values ({$values})")->execute($records);
    }

    public function orderBy()
    {
        if (func_num_args() == 0) {
            if (!count($this->orderby)) {
                return "";
            }
            $orderby = array();
            foreach ($this->orderby as $values) {
                $orderby[] = $values[0] . " " . $values[1];
            }
            return " ORDER BY " . implode(",", $orderby);
        }

        if (func_get_arg(0) == "") {
            return $this;
        }

        $order = func_get_arg(0);
        if (is_array($order)) {
            $this->orderby[] = $order;
        } else {
            $this->orderby[] = func_get_args();
        }

        return $this;
    }

    public function where($where, $bindParam)
    {
        if (func_num_args() == 0) {
            if (!count($this->where)) {
                return "";
            }
            return " WHERE (" . implode(") AND (", $this->where) . ")";
        }

        if (is_array($where)) {
            foreach ($where as $w) {
                $this->where($w[0], $w[1]);
            }
            return $this;
        }

        $this->where[] = $where;
        if ($bindParam) {
            if (is_array($bindParam)) {
                foreach ($bindParam as $k => $v) {
                    $this->bindParam[$k] = $v;
                }
            } else {
                $this->bindParam[] = $bindParam;
            }
        }
        return $this;
    }

    public function limit()
    {
        if (func_num_args() == 0) {
            if (!count($this->limit)) {
                return false;
            }
            $s = " limit " . $this->limit[0];
            if ($this->limit[1] != "") {
                $s .= "," . $this->limit[1];
            }
            return $s;
        }
        $limit = func_get_arg(0);
        if (is_null($limit)) {
            return $this;
        }
        if (is_array($limit)) { // page limit
            $this->limit = array(($limit[0] - 1) * $limit[1], $limit[1]);
        } else {
            $this->limit = func_get_args();
        }

        return $this;
    }


    public function select($fields = ['*'], $class)
    {
        $sql = "SELECT " . implode(",", $fields) . " FROM `$this->name`";

        $sql .= $this->join();
        $sql .= $this->where();
        $sql .= $this->groupBy();
        $sql .= $this->orderBy();
        $sql .= $this->limit();

        $this->sql = $sql;

        if ($this->bindParam) {
            $sth = $this->db->prepare($sql);
            $sth->execute($this->bindParam);
        } else {
            $sth = $this->db->query($sql);
        }
        if ($class) {
            $sth->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $class, []);
        }
        return $sth->fetchAll();
    }

    public function updateOrCreate($records = [])
    {
        $names = array_keys($records);
        $values = implode(",", array_map(function ($name) {
            return ":" . $name;
        }, $names));
        $names = implode(",", array_map(function ($name) {
            return "`" . $name . "`";
        }, $names));

        $set = "";
        foreach ($records as $k => $v) {
            $update[] = "`$k`=values(`$k`)";
        }
        $set = implode(",", $update);


        return $this->db->prepare("INSERT INTO `$this->name` ({$names}) values ({$values}) on duplicate key update {$set}")->execute($records);
    }


    public function update($values = [])
    {
        $set = [];
        foreach ($values as $k => $v) {
            $set[] = "`$k`=:$k";
        }
        $set = implode(",", $set);

        $sql = "UPDATE `$this->name` SET $set ";

        $sql .= $this->where();
        $this->sql = $sql;

        if ($this->bindParam) {
            $values = array_merge($this->bindParam, $values);
        }

        $stm = $this->db->prepare($sql);
        $stm->execute($values);
        return $stm;
    }


    public function join()
    {
        if (func_num_args() == 0) {
            if (!count($this->join)) {
                return "";
            }
            foreach ($this->join as $values) {
                $join[] = " LEFT JOIN `" . $values[0] . "` ON " . $values[1];
            }
            return implode(chr(10), $join);
        }
        $this->join[] = func_get_args();
        return $this;
    }

    public function groupBy()
    {
        if (func_num_args() == 0) {
            if (!count($this->groupby)) {
                return "";
            }
            return " GROUP BY " . implode(",", $this->groupby);
        }
        $groupby = func_get_arg(0);
        if (is_array($groupby)) {
            foreach ($groupby as $g) {
                $this->groupby[] = $g;
            }
        } else {
            $this->groupby[] = $groupby;
        }

        return $this;
    }

    public function having()
    {
        if (func_num_args() == 0) {
            if (!count($this->having)) {
                return "";
            }
            return " HAVING (" . implode(" AND ", $this->having) . ")";
        }

        $having = func_get_arg(0);
        if (is_array($having)) {
            foreach ($having as $h) {
                $this->having[] = $h;
            }
        } else {
            $this->having[] = $h;
        }
        return $this;
    }

    public function count($field = "*")
    {
        $sql = "SELECT count($field) FROM `$this->name`";

        $sql .= $this->join();
        $sql .= $this->where();
        $sql .= $this->groupBy();

        $this->sql = $sql;

        if ($this->bindParam) {
            $sth = $this->db->prepare($sql);
            return $sth->execute($this->bindParam)->fetchColumn(0);
        } else {
            return $this->db->query($sql)->fetchColumn(0);
        }
    }

    public function sql()
    {
        return $this->sql;
    }

    public function delete()
    {
        $sql = "DELETE FROM `$this->name`";
        $sql .= $this->where();
        $this->sql = $sql;

        if ($this->bindParam) {
            $sth = $this->db->prepare($sql);
            $sth->execute($this->bindParam);
            return $sth->rowCount();
        } else {
            return $this->db->exec($sql);
        }
    }

    public function name()
    {
        return $this->name;
    }

    public function db()
    {
        return $this->db;
    }
}
