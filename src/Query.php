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
use Laminas\Di\Injector;
use Laminas\Paginator\Paginator;
use R\DB\Paginator\Adapter;
use Traversable;
use PDO;
use ReflectionClass;
use ReflectionParameter;

/**
 * @template T
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

    /**
     * @param class-string<T> $class
     */
    public function __construct(string $class)
    {
        $this->class = $class;
        parent::__construct((string)$class::_table());
        $this->schema = $class::GetSchema();
    }

    public function getClassName()
    {
        return $this->class;
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
        $c->offset(0);
        $c->limit(1);
        $c->columns([
            "c" => new Expression("count(*)")
        ]);

        $sql = $c->getSqlString($this->schema->getPlatform());
        return $this->schema->query($sql)->fetchColumn(0);
    }

    /**
     * @return T|null
     */
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

        try {
            $this->statement = $this->schema->prepare($sql);
        } catch (Exception $e) {
            throw new Exception("Error preparing query: " . $e->getMessage() . "\n\n" . $sql);
        }

        if (!$this->statement->execute($input_parameters)) {
            $error = $this->statement->errorInfo();
            throw new Exception("PDO SQLSTATE [" . $error[0] . "] " . $error[2] . " sql: $sql ", $error[1]);
        }

        if ($this->_custom_column) {
            $this->statement->setFetchMode(PDO::FETCH_ASSOC);
        } else {

            //dependency injection

            //reflection 
            $args = [];
            $ref_class = new ReflectionClass($this->class);
            if ($constructor = $ref_class->getConstructor()) {
                $ref_params = array_map(function (ReflectionParameter $item) {
                    return $item->getType()->getName();
                }, $constructor->getParameters());
                $container = $this->schema->getContainer();
                foreach ($ref_params as $param) {
                    if ($container->has($param)) {
                        $args[] = $container->get($param);
                    } else {
                        $args[] = null;
                    }
                }
            }


            $this->statement->setFetchMode(PDO::FETCH_CLASS, $this->class, $args);
        }

        $a = collect([]);
        foreach ($this->statement as $obj) {
            if ($this->_custom_column) {
                $aa = [];
                foreach ($obj as $k => $v) {
                    $aa[$k] = $v;
                }
                $a->add($aa);
            } else {
                $a->add($obj);
            }
        }

        return $a;
    }

    function getIterator(): Traversable
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

    public function sort(string $sort)
    {
        $query = clone $this;
        if ($sort) {
            $s = explode(':', $sort);
            $query->order($s[0] . " " . $s[1]);
        }
        return $query;
    }

    public function filters(array $filters)
    {
        $query = clone $this;
        foreach ($filters as $field => $filter) {

            foreach ($filter as $operator => $value) {

                if ($operator == 'eq') {
                    $query->where->equalTo($field, $value);
                }

                if ($operator == 'contains') {
                    $query->where->like($field, "%$value%");
                }

                if ($operator == 'in') {
                    $query->where->in($field, $value);
                }

                if ($operator == 'between') {
                    $query->where->between($field, $value[0], $value[1]);
                }

                if ($operator == 'gt') {
                    $query->where->greaterThan($field, $value);
                }

                if ($operator == 'gte') {
                    $query->where->greaterThanOrEqualTo($field, $value);
                }

                if ($operator == 'lt') {
                    $query->where->lessThan($field, $value);
                }

                if ($operator == 'lte') {
                    $query->where->lessThanOrEqualTo($field, $value);
                }

                if ($operator == 'ne') {
                    $query->where->notEqualTo($field, $value);
                }
            }
        }
        return $query;
    }

    public function filter(callable $filter)
    {
        return collect($this)->filter($filter);
    }

    public function map(callable $map)
    {
        return collect($this)->map($map);
    }

    public function getPaginator()
    {
        return new Paginator(new Adapter($this));
    }

    public function each(callable $callback)
    {
        foreach ($this as $obj) {
            $callback($obj);
        }
    }
}
