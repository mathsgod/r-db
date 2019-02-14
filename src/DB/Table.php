<?php
namespace R\DB;

use Exception;

class Table
{
    private $db;
    public $name;
    private $query;

    public function __construct(Schema $db, $name)
    {
        $this->db = $db;
        $this->name = $name;
    }

    public function dropColumn($name)
    {
        $sql = "ALTER TABLE `{$this->name}` DROP COLUMN `$name`";
        return $this->db->exec($sql);
    }

    public function addColumn($name, $type, $constraint = null)
    {
        $sql = "ALTER TABLE `{$this->name}` ADD COLUMN `$name` $type $constraint";
        return $this->db->exec($sql);
    }

    public function truncate()
    {
        $sql = "TRUNCATE `{$this->name}`";
        return $this->db->exec($sql);
    }

    public function columns()
    {
        $sth = $this->db->query("SHOW COLUMNS FROM `$this->name`");
        $sth->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, Column::class, [$this]);
        return $sth->fetchAll();
    }

    public function column($field)
    {
        $sth = $this->db->query("SHOW COLUMNS FROM `{$this->name}` WHERE Field='$field'");
        $sth->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, Column::class, [$this]);
        $ret = $sth->fetch();
        if ($ret === false) {
            return null;
        }
        return $ret;
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

    public function query()
    {
        if ($this->query) {
            return $this->query;
        }
        $this->query = new Query($this->db, $this->name);
        return $this->query;
    }

    public function where($where = null, $bindParam = null)
    {
        $q = $this->query();
        $q->where($where, $bindParam);
        return $this;
    }

    public function update($records = [])
    {
        $q = $this->query();
        $q->set($records);
        $q->update();
        return $q->execute();
    }

    public function select($field = [])
    {
        $q = $this->query();
        $q->select($field);
        return $this;
    }

    public function insert($records = [])
    {
        $q = $this->query();
        $q->set($records);
        $q->insert();
        $this->query = null;
        return $q->execute();
    }

    public function delete()
    {
        $q = $this->query();
        $q->delete();
        $this->query = null;
        return $q->execute();
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

    public function db()
    {
        return $this->db;
    }

    public function from()
    {
        return $this->db->from($this->name);
    }

    public function find($where = null, $orderby = null, $limit = null)
    {
        $q = new Query($this->db, $this->name);
        $q->select();
        $q->where($where);
        $q->orderby($orderby);
        $q->limit($limit);
        return $q;
    }

    public function first()
    {
        $q = $this->query();
        $q->select();
        $q->limit(1);
        return $q->execute()->fetch();
    }

    public function top($count = null)
    {
        $q = $this->query();
        $q->limit($count);
        return $q->execute()->fetchAll();
    }

    public function __get($name)
    {
        if ($name == "columns") {
            return $this->columns();
        }

        if ($name == "index") {
            return $this->_index();
        }
    }

    public function max($column)
    {
        return $this->select(["max(`$column`)"])->get()->fetchColumn(0);
    }

    public function count()
    {
        return $this->select(["count(*)"])->get()->fetchColumn(0);
    }

    public function min($column)
    {
        return $this->select(["min(`$column`)"])->get()->fetchColumn(0);
    }

    public function avg($column)
    {
        return $this->select(["avg(`$column`)"])->get()->fetchColumn(0);
    }

    private function _index()
    {
        $sql = "SHOW INDEX FROM `{$this->name}`";
        if ($sth = $this->db->query($sql)) {
            return $sth->fetchAll();
        }
    }

    public function orderBy($orderBy)
    {
        $q = $this->query();
        $q->orderBy($orderBy);
        return $this;
    }

    public function get()
    {
        return $this->query()->execute();
    }


}
