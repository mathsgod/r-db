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
        $sql = "TRUNCATE `{$this->name}`";
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

    public function update($records = [])
    {
        $q = new Query($this->db);
        $q->update($this->name);
        $q->set($records);
        return $q;
    }

    public function select($field)
    {
        $q = new Query($this->db);
        $q->select($field)->from($this->name);
        return $q;
    }

    public function insert($records = [])
    {
        $q = new Query($this->db);
        $q->insert($this->name);
        $q->set($records);
        return $q;
    }

    public function delete()
    {
        $q = new Query($this->db);
        $q->delete()->from($this->name);
        return $q;
        $q = $this->from();
        return $q->delete();
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

    public function count($where)
    {
        $q = new Query($this->db);
        $q->select("count(*)")->from($this->name)->where($where);
        return $q->execute()->fetchColumn(0);
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

    public function find($where, $orderby, $limit)
    {
        $q = new Query($this->db);
        $q->select()->from($this->name);
        $q->where($where);
        $q->orderby($orderby);
        $q->limit($limit);

        return $q;


        $q->select()->from($this->name)->where($where)->orderBy($order)->limit($limit);
        return $q->execute();
    }

    public function first($where, $order)
    {
        $q = new Query($this->db);
        $q->select()->from($this->name)->where($where)->orderBy($order)->limit(1);
        return $q->execute()->fetch();
    }

    public function top($count, $where, $order)
    {
        $q = new Query($this->db);
        $q->select()->from($this->name)->where($where)->orderBy($order)->limit($count);
        return $q->execute();
    }


    public function __get($name)
    {
        if ($name == "columns") {
            return $this->describe();
        }

        if ($name == "index") {
            return $this->_index();
        }
    }

    private function _index()
    {
        $sql = "SHOW INDEX FROM `{$this->name}`";
        if ($sth = $this->db->query($sql)) {
            return $sth->fetchAll();
        }
    }



}
