<?php

namespace R\DB;

use Closure;
use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Db\Sql\Ddl\Column\ColumnInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Sql;
use Laminas\Hydrator\ObjectPropertyHydrator;

class Table implements TableInterface
{
    private $pdo;
    public $name;
    private $adapter;

    public function __construct(PDOInterface $pdo, string $name)
    {
        $this->pdo = $pdo;
        $this->name = $name;
        $this->adapter = $pdo->getAdapter();
    }

    function  getAdapter()
    {
        return $this->adapter;
    }

    function getPrimaryKeys(): array
    {
        $ret = array_filter($this->describe(), function ($o) {
            return $o["Key"] == "PRI";
        });

        return array_map(function ($o) {
            return $o["Field"];
        }, $ret);
    }

    function getName(): string
    {
        return $this->name;
    }

    public function getPDO()
    {
        return $this->pdo;
    }

    /**
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $predicate
     * @return \R\DB\Rows|\R\DB\Row[]
     */
    public function getRows($predicate = null, string $combination = Predicate\PredicateSet::OP_AND)
    {
        $select = new Select($this->name);
        if ($predicate) {
            $select->where($predicate, $combination);
        }

        $row = new Row($this);
        $row->setDbAdapter($this->pdo->getAdapter());
        $resultSet = new  Rows(new ObjectPropertyHydrator, $row);
        $gateway = new  TableGateway($this->name, $this->adapter, null, $resultSet);

        return $gateway->selectWith($select);
    }

    public function dropColumn(string $name)
    {
        $alter = new AlterTable($this->name);
        $alter->dropColumn($name);
        $sql = new Sql($this->adapter);
        $this->pdo->exec($sql->buildSqlString($alter));
    }

    public function addColumn(ColumnInterface $column)
    {
        $alter = new AlterTable($this->name);
        $alter->addColumn($column);
        $sql = new Sql($this->adapter);
        $this->pdo->exec($sql->buildSqlString($alter));
    }

    public function changeColumn(string $name, ColumnInterface $column)
    {
        $alter = new AlterTable($this->name);
        $alter->changeColumn($name, $column);
        $sql = new Sql($this->adapter);
        $this->pdo->exec($sql->buildSqlString($alter));
    }

    public function truncate()
    {
        $sql = "TRUNCATE `{$this->name}`";
        return $this->pdo->exec($sql);
    }


    public function getColumns()
    {
        $sth = $this->pdo->query("SHOW COLUMNS FROM `$this->name`");
        $sth->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, Column::class, [$this]);
        return $sth->fetchAll();
    }

    /**
     * @return ColumnInterface[]
     */
    public function columns()
    {
        $sth = $this->pdo->query("SHOW COLUMNS FROM `$this->name`");
        $sth->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, Column::class, [$this]);
        return $sth->fetchAll();
    }

    public function column(string $field): ?Column
    {
        $sth = $this->pdo->query("SHOW COLUMNS FROM `{$this->name}` WHERE Field='$field'");
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
        if ($sth = $this->pdo->query($sql)) {
            return $sth->fetchAll();
        }
        return [];
    }

    public function getPrimaryKey()
    {
        return $this->keys()[0];
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

    /**
     * @param Where|\Closure|string|array $where
     */
    public function select($where = null)
    {
        $gateway = $this->pdo->getTableGateway($this->name);
        return $gateway->select($where);
    }

    public function insert(array $data = [])
    {
        $gateway = $this->pdo->getTableGateway($this->name);
        return $gateway->insert($data);
    }

    /**
     * @param Where|\Closure|string|array $where
     */
    public function delete($where)
    {
        $gateway = $this->pdo->getTableGateway($this->name);
        return $gateway->delete($where);
    }

    /**
     * @param Where|\Closure|string|array $where
     */
    public function update(array $data, $where = null)
    {
        $gateway = $this->pdo->getTableGateway($this->name);
        return $gateway->update($data, $where);
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
        return $this->pdo->prepare("REPLACE INTO `$this->name` ({$names}) values ({$values})")->execute($records);
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


        return  $this->pdo->prepare("INSERT INTO `$this->name` ({$names}) values ({$values}) on duplicate key update {$set}")->execute($records);
    }

    /**
     * @param Where|\Closure|string|array $where
     */
    public function first($where = null, $combination = Predicate\PredicateSet::OP_AND)
    {
        $select = new Select($this->name);
        if (isset($where)) {
            $select->where($where, $combination);
        }

        $select->limit(1);


        $sql = new Sql($this->adapter, $this->name);
        return $this->pdo->query($sql->buildSqlString($select))->fetch();
    }

    protected function getGateway()
    {
        return $this->pdo->getTableGateway($this->name);
    }

    public function max($column)
    {
        $select = new Select($this->name);
        $select->columns([
            "c" => new Expression("max(`$column`)")
        ]);

        $sql = new Sql($this->adapter, $this->name);
        return $this->pdo->query($sql->buildSqlString($select))->fetchColumn(0);
    }

    public function count(): int
    {
        $select = new Select($this->name);
        $select->columns([
            "c" => new Expression("count(*)")
        ]);

        $sql = new Sql($this->adapter, $this->name);
        return $this->pdo->query($sql->buildSqlString($select))->fetchColumn(0);
    }

    public function min(string $column)
    {
        $select = new Select($this->name);
        $select->columns([
            "c" => new Expression("min(`$column`)")
        ]);

        $sql = new Sql($this->adapter, $this->name);
        return $this->pdo->query($sql->buildSqlString($select))->fetchColumn(0);
    }

    public function avg(string $column)
    {
        $select = new Select($this->name);
        $select->columns([
            "c" => new Expression("avg(`$column`)")
        ]);

        $sql = new Sql($this->adapter, $this->name);
        return $this->pdo->query($sql->buildSqlString($select))->fetchColumn(0);
    }

    public function top(int $top)
    {
        $select = new Select($this->name);
        $select->limit($top);

        $sql = new Sql($this->adapter, $this->name);
        return $this->pdo->query($sql->buildSqlString($select))->fetchAll();
    }
}
