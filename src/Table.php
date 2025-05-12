<?php

namespace R\DB;

use Closure;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Db\Sql\Ddl\Column\ColumnInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Insert;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Update;
use Laminas\Hydrator\ObjectPropertyHydrator;

class Table extends TableGateway
{
    function getPrimaryKeys(): array
    {
        $ret = array_filter($this->describe(), function ($o) {
            return $o["Key"] == "PRI";
        });

        return array_map(function ($o) {
            return $o["Field"];
        }, $ret);
    }

    /**
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $predicate
     * @return \R\DB\Rows|\R\DB\Row[]
     */
    public function getRows($predicate = null, string $combination = Predicate\PredicateSet::OP_AND)
    {
        $select = new Select($this->table);
        if ($predicate) {
            $select->where($predicate, $combination);
        }

        $row = new Row($this);
        $row->setDbAdapter($this->adapter);
        $resultSet = new  Rows(new ObjectPropertyHydrator, $row);
        $gateway = new  TableGateway($this->table, $this->adapter, null, $resultSet);

        return $gateway->selectWith($select);
    }

    public function dropColumn(string $name)
    {
        $alter = new AlterTable($this->table);
        $alter->dropColumn($name);
        $sql = new Sql($this->adapter);
        return $this->execute($sql->buildSqlString($alter));
    }

    public function addColumn(ColumnInterface $column)
    {
        $alter = new AlterTable($this->table);
        $alter->addColumn($column);
        $sql = new Sql($this->adapter);
        return $this->execute($sql->buildSqlString($alter));
    }

    public function changeColumn(string $name, ColumnInterface $column)
    {
        $alter = new AlterTable($this->table);
        $alter->changeColumn($name, $column);
        $sql = new Sql($this->adapter);
        return $this->execute($sql->buildSqlString($alter));
    }


    private function execute(string $sql, $parametersOrQueryMode = Adapter::QUERY_MODE_EXECUTE)
    {
        /**
         * @var  \Laminas\Db\Adapter\Adapter $adapter
         */
        $adapter = $this->adapter;

        return $adapter->query($sql, $parametersOrQueryMode);
    }

    private function query(string $sql, $parametersOrQueryMode = Adapter::QUERY_MODE_PREPARE)
    {
        /**
         * @var \Laminas\Db\Adapter\Adapter $adapter
         */
        $adapter = $this->adapter;
        return iterator_to_array($adapter->query($sql, $parametersOrQueryMode)->execute());
    }

    public function truncate()
    {
        return $this->execute("TRUNCATE TABLE `{$this->table}`");
    }

    public function columns()
    {
        $metadata = \Laminas\Db\Metadata\Source\Factory::createSourceFromAdapter($this->adapter);
        return $metadata->getColumns($this->table);
    }

    public function column(string $field): ?Column
    {
        $ret = $this->query("SHOW COLUMNS FROM `{$this->table}` WHERE Field='$field'")[0];

        if (empty($ret)) {
            return null;
        }

        $col = new Column($this);

        foreach ($ret as $k => $v) {
            if (property_exists($col, $k)) {
                $col->$k = $v;
            }
        }
        return $col;
    }


    public function describe(): array
    {
        $name = $this->getTable();

        return $this->query("DESCRIBE `{$name}`");
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
    /*     public function update(array $data, $where = null)
    {
        $gateway = $this->pdo->getTableGateway($this->name);
        return $gateway->update($data, $where);
    } */

    public function replace(array $records = [])
    {
        $insert = new Insert($this->table);
        $insert->values($records);
        $sql = new Sql($this->adapter);
        $s = $sql->buildSqlString($insert);
        $s = str_replace("INSERT INTO", "REPLACE INTO", $s);
        return $this->execute($s);
    }

    public function updateOrCreate(array $records = [])
    {

        $insert = new Insert($this->table);
        $insert->values($records);

        $update = new Update($this->table);
        $update->set($records);



        $set = "";
        $update = [];
        foreach ($records as $k => $v) {
            $update[] = "`$k`=A.`$k`";
        }
        $set = implode(",", $update);

        $sql = new Sql($this->adapter);
        $s = $sql->buildSqlString($insert) . " AS A ON DUPLICATE KEY UPDATE " . $set;

        return $this->execute($s);
    }

    /**
     * @param Where|\Closure|string|array $where
     */
    public function first($where = null, $combination = Predicate\PredicateSet::OP_AND)
    {
        $select = new Select($this->table);
        if (isset($where)) {
            $select->where($where, $combination);
        }
        $select->limit(1);
        return iterator_to_array($this->selectWith($select))[0] ?? null;
    }

    public function max($column)
    {
        $select = new Select($this->table);
        $select->columns([
            "c" => new Expression("max(`$column`)")
        ]);
        return iterator_to_array($this->selectWith($select))[0]["c"] ?? null;
    }

    public function count(): int
    {
        $select = new Select($this->table);
        $select->columns([
            "c" => new Expression("count(*)")
        ]);
        $select->limit(1);

        return iterator_to_array($this->selectWith($select))[0]["c"] ?? 0;
    }

    public function min(string $column)
    {
        $select = new Select($this->table);
        $select->columns([
            "c" => new Expression("min(`$column`)")
        ]);

        return iterator_to_array($this->selectWith($select))[0]["c"] ?? null;
    }

    public function avg(string $column)
    {
        $select = new Select($this->table);
        $select->columns([
            "c" => new Expression("avg(`$column`)")
        ]);

        return iterator_to_array($this->selectWith($select))[0]["c"] ?? null;
    }

    public function top(int $top)
    {
        $select = new Select($this->table);
        $select->limit($top);
        return iterator_to_array($this->selectWith($select));
    }
}
