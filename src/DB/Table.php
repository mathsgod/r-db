<?php

namespace R\DB;

use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Predicate;

class Table
{
    private $db;
    public $name;

    public function __construct(Schema $db, string $name)
    {
        $this->db = $db;
        $this->name = $name;
    }

    public function dropColumn(string $name)
    {
        $sql = "ALTER TABLE `{$this->name}` DROP COLUMN `$name`";
        return $this->db->exec($sql);
    }

    public function addColumn(string $name, string $type, $constraint = null)
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

    public function column(string $field): ?Column
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

    public function describe(): array
    {
        $sql = "DESCRIBE `{$this->name}`";
        if ($sth = $this->db->query($sql)) {
            return $sth->fetchAll();
        }
        return [];
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

    public function select(Where|\Closure|string|array $where = null)
    {
        $gateway = new TableGateway($this->name, $this->db->getDbAdatpter());
        return $gateway->select($where);
    }

    public function insert(array $set = [])
    {
        $gateway = new TableGateway($this->name, $this->db->getDbAdatpter());
        return $gateway->insert($set);
    }

    public function delete(Where|\Closure|string|array $where)
    {
        $gateway = new TableGateway($this->name, $this->db->getDbAdatpter());
        return $gateway->delete($where);
    }

    public function replace(array $records = [])
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

    public function updateOrCreate(array $records = [])
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


    public function first(Where|\Closure|string|array $where = null, $combination = Predicate\PredicateSet::OP_AND)
    {
        $select = new Select($this->name);
        if (isset($where)) {
            $select->where($where, $combination);
        }

        $select->limit(1);
        $gateway = $this->getGateway();
        $result = $gateway->selectWith($select);
        return $result->current();
    }

    protected function getGateway()
    {
        return new TableGateway($this->name, $this->db->getDbAdatpter());
    }

    public function max($column)
    {
        $result = $this->select(function (Select $select) use ($column) {
            $select->columns([
                "m" => new Expression("max(`$column`)")
            ]);
        });
        return $result->current()["m"];
    }

    public function count(): int
    {
        $select = new Select($this->name);
        $select->columns([
            "c" => new Expression("count(*)")
        ]);

        $result = $this->getGateway()->selectWith($select);
        return $result->current()["c"];
    }

    public function min(string $column)
    {
        $select = new Select($this->name);
        $select->columns([
            "c" => new Expression("min(`$column`)")
        ]);

        $result = $this->getGateway()->selectWith($select);
        return $result->current()["c"];
    }

    public function avg(string $column)
    {
        $select = new Select($this->name);
        $select->columns([
            "c" => new Expression("avg(`$column`)")
        ]);

        $result = $this->getGateway()->selectWith($select);
        return $result->current()["c"];
    }

    public function top(int $top)
    {
        $select = new Select($this->name);
        $select->limit($top);
        $result = $this->getGateway()->selectWith($select);
        return $result;
    }
}
