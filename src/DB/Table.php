<?php
namespace R\DB;

use Exception;

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

    public function __construct(PDO $db, $name, $logger)
    {
        $this->db = $db;
        $this->name = $name;
        $this->logger = $logger;
    }

    public function dropColumn($name)
    {
        $sql = "ALTER TABLE `{$this->name}` DROP COLUMN `$name`";
        $this->sql = $sql;
        $ret = $this->db->exec($sql);
        if ($ret === false) {
            $error = $this->db->errorInfo();
            throw new Exception($error[2], $error[1]);
        }
        return $ret;
    }

    public function addColumn($name, $type, $constraint)
    {
        $sql = "ALTER TABLE `{$this->name}` ADD COLUMN `$name` $type $constraint";
        $this->sql = $sql;
        $ret = $this->db->exec($sql);
        if ($ret === false) {
            $error = $this->db->errorInfo();
            throw new Exception($error[2], $error[1]);
        }
        return $ret;
    }

    public function truncate()
    {
        $this->sql = "TRUNCATE `{$this->name}`";
        $ret = $this->db->exec($sql);
        if ($ret === false) {
            $error = $this->db->errorInfo();
            throw new Exception($error[2], $error[1]);
        }
        return $ret;
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
        if ($sth = $this->db->query($sql)) {
            return $sth->fetchAll();
        }

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


    public function count($where)
    {
        $q = $this->from();
        $q->where($where);
        $rs = $q->select("count(*)");
        return $rs->fetchColumn(0);
    }

    public function delete($where)
    {
        $q = $this->from();
        $q->where($where);
        return $q->delete();
    }

    public function name()
    {
        return $this->name;
    }

    public function db()
    {
        return $this->db;
    }

    public function from()
    {
        return $this->db->from($this->name);
    }

    public function find($where, $order, $limit)
    {
        $q = $this->from();
        $q->where($where);
        $q->orderBy($order);
        $q->limit($limit);
        return $q->select();
    }

    public function first($where, $order)
    {
        $q = $this->from();
        $q->where($where);
        $q->orderBy($order);
        $q->limit(1);
        return $q->select()->fetch();
    }

    public function top($count, $where, $order)
    {
        $q = $this->from();
        $q->where($where);
        $q->orderBy($order);
        $q->limit($count);
        return $q->select();
    }

}
