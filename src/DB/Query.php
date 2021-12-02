<?php

namespace R\DB;

use Laminas\Db\Sql\Select;
use Exception;
use IteratorAggregate;
use Laminas\Db\Adapter\Driver\DriverInterface;
use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\Delete;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Update;

/**
 * @method static order(string|array|Expression $order)
 * @method static limit(int $limit)
 * @method static offset(int $offset)
 * @method static where(\Laminas\Db\Sql\Where|\Closure|string|array|\Laminas\Db\Sql\Predicate\PredicateInterface $predicate,string $combination = Predicate\PredicateSet::OP_AND)
 */
class Query extends Select implements IteratorAggregate
{
    protected $class;
    protected $statement;
    private $_custom_column = false;
    /**
     * @var \R\DB\Schema 
     */
    protected $schema;

    public function __construct(string $class)
    {
        $this->class = $class;
        parent::__construct((string)$class::_table());
        $this->schema = $class::GetSchema();
    }

    /**
     * @return static
     */
    public function columns(array $columns, $prefixColumnsWithTable = true)
    {
        $this->_custom_column = true;
        return parent::columns($columns, $prefixColumnsWithTable);
    }

    public function count(): int
    {
        $c = clone $this;
        $c->columns([
            "c" => new Expression("count(*)")
        ]);

        $sql = $c->getSqlString($this->schema->getPlatform());
        return $this->schema->query($sql)->fetchColumn(0);
    }

    public function first()
    {
        $c = clone $this;
        $c->limit(1);
        $result = $c->execute();
        if ($result->count()) {
            return $result->toArray()[0];
        }
        return null;
    }

    // https://github.com/laminas/laminas-db/issues/136
    protected function processOffset(
        PlatformInterface $platform,
        DriverInterface $driver = null,
        ParameterContainer $parameterContainer = null
    ) {
        if ($this->offset === null) {
            return;
        }
        if ($parameterContainer) {
            $paramPrefix = $this->processInfo['paramPrefix'];
            $parameterContainer->offsetSet($paramPrefix . 'offset', $this->offset, ParameterContainer::TYPE_INTEGER);
            return [$driver->formatParameterName($paramPrefix . 'offset')];
        }

        return [intval($this->offset)];
    }

    // https://github.com/laminas/laminas-db/issues/136
    protected function processLimit(
        PlatformInterface $platform,
        DriverInterface $driver = null,
        ParameterContainer $parameterContainer = null
    ) {
        if ($this->limit === null) {
            return;
        }
        if ($parameterContainer) {
            $paramPrefix = $this->processInfo['paramPrefix'];
            $parameterContainer->offsetSet($paramPrefix . 'limit', $this->limit, ParameterContainer::TYPE_INTEGER);
            return [$driver->formatParameterName($paramPrefix . 'limit')];
        }
        return [intval($this->limit)];
    }


    public function execute(array $input_parameters = [])
    {
        $sql = $this->getSqlString($this->schema->getPlatform());

        $this->statement = $this->schema->prepare($sql);

        if (!$this->statement->execute($input_parameters)) {
            $error = $this->statement->errorInfo();
            throw new Exception("PDO SQLSTATE [" . $error[0] . "] " . $error[2] . " sql: $sql ", $error[1]);
        }

        if ($this->_custom_column) {
            $this->statement->setFetchMode(\PDO::FETCH_ASSOC);
        } else {
            $this->statement->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $this->class, []);
        }



        $attr = $this->class::__attribute();
        $json_fields = array_filter($attr, function ($o) {
            return $o["Type"] == "json";
        });

        $json_fields = array_map(function ($o) {
            return $o["Field"];
        }, $json_fields);

        $bool_fields = array_filter($attr, function ($o) {
            return $o["Type"] == "tinyint(1)";
        });
        $bool_fields = array_column($bool_fields, "Field");


        $a = collect([]);
        foreach ($this->statement as $obj) {

            if ($this->_custom_column) {
                $aa = [];
                foreach ($obj as $k => $v) {
                    $aa[$k] = $v;
                    if (in_array($k, $json_fields)) {
                        $aa[$k] = json_decode($v, true);
                    }

                    if (in_array($k, $bool_fields)) {
                        $aa[$k] = (bool)$obj[$v];
                    }
                }
                $a->add($aa);
            } else {
                foreach ($json_fields as $field) {
                    $obj->$field = json_decode($obj->$field, true);
                }

                foreach ($bool_fields as $field) {
                    $obj->$field = (bool)$obj->$field;
                }
                $a->add($obj);
            }


            
        }

        return $a;
    }

    public function getIterator()
    {
        return $this->execute();
    }

    /**
     * @return T[]
     */
    public function toArray()
    {
        return iterator_to_array($this);
    }

    public function delete()
    {
        $delete = new Delete($this->table);
        $delete->where($this->where);
        $sql = $delete->getSqlString($this->schema->getPlatform());
        return $this->schema->exec($sql);
    }

    public function update(array $values)
    {
        $update = new Update($this->table);
        $update->where($this->where);
        $update->set($values);
        $sql = $update->getSqlString($this->schema->getPlatform());
        return $this->schema->exec($sql);
    }

    public function filter(callable $filter)
    {
        return collect($this)->filter($filter);
    }

    public function map(callable $map)
    {
        return collect($this)->map($map);
    }
}
