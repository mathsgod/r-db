<?php

namespace R\DB;

use Closure;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Exception;
use PDO;
use ReflectionClass;
use stdClass;

/**
 * @template T
 * @property-read \Laminas\Db\Sql\Where $where
 */
class Q
{
    protected $class;
    protected $populate = [];
    protected $where = [];
    protected $fields = [];
    protected $select;
    protected $limit = null;
    protected $offset = null;

    /**
     * @param class-string<T> $class
     */
    public function __construct(string $class, array $query = [])
    {
        $this->class = $class;

        if (class_exists($class)) {
            $short_name = (new ReflectionClass($class))->getShortName();
            $this->select = new Select($short_name);
        } else {
            $this->select = new Select($class);
        }

        if (isset($query["fields"])) {
            $this->fields($query["fields"]);
        }

        if (isset($query["limit"])) {
            $this->limit($query["limit"]);
        }

        if (isset($query["offset"])) {
            $this->offset($query["offset"]);
        }

        if (isset($query["filters"])) {
            $this->filters($query["filters"]);
        }

        if (isset($query["sort"])) {
            $sort = explode(":", $query["sort"]);
            $this->order($sort[0] . " " . ($sort[1] ?: "asc"));
        }

        $this->populate($query["populate"] ?? []);
    }


    public function filters(array $filters)
    {

        foreach ($filters as $name => $filter) {

            foreach ($filter as $operator => $value) {

                switch ($operator) {
                    case "eq":
                        $this->select->where->equalTo($name, $value);
                        break;
                    case "ne":
                        $this->select->where->notEqualTo($name, $value);
                        break;
                    case "gt":
                        $this->select->where->greaterThan($name, $value);
                        break;
                    case "gte":
                        $this->select->where->greaterThanOrEqualTo($name, $value);
                        break;
                    case "lt":
                        $this->select->where->lessThan($name, $value);
                        break;
                    case "lte":
                        $this->select->where->lessThanOrEqualTo($name, $value);
                        break;
                    case "in":
                        $this->select->where->in($name, $value);
                        break;
                    case "notIn":
                        $this->select->where->notIn($name, $value);
                        break;
                    case "contains":
                        $this->select->where->like($name, "%$value%");
                        break;
                    case "notContains":
                        $this->select->where->notLike($name, "%$value%");
                        break;
                    case "between":
                        $this->select->where->between($name, $value[0], $value[1]);
                        break;
                    case "notBetween":
                        $this->select->where->notBetween($name, $value[0], $value[1]);
                        break;
                    case "null":
                        $this->select->where->isNull($name);
                        break;
                    case "notNull":
                        $this->select->where->isNotNull($name);
                        break;
                }
            }
        }

        return clone $this;
    }

    public function fields(array $fields)
    {
        $this->fields = $fields;
        return clone $this;
    }

    /**
     * @param Where|Closure|string|array|Predicate\PredicateInterface $predicate
     */
    public function where(array $predicate, $combination = Predicate\PredicateSet::OP_AND)
    {
        $this->select->where($predicate, $combination);
        return clone $this;
    }

    public function getName()
    {
        $ref_class = new ReflectionClass($this->class);
        return $ref_class->getShortName();
    }

    /**
     * @param string|array|Expression $order
     * @return $this Provides a fluent interface
     */
    public function order($order)
    {
        $this->select->order($order);
        return clone $this;
    }

    /**
     * @param int $limit
     * @return $this Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return clone $this;
    }

    /**
     * @param int $offset
     * @return $this Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return clone $this;
    }


    public function populate(array $populate)
    {
        $this->populate = $populate;
        return clone $this;
    }

    public function getSchema(): Schema
    {
        if (!class_exists($this->class)) {
            return Schema::Create();
        }

        $ref_class = new ReflectionClass($this->class);

        if (in_array(SchemaAwareInterface::class, $ref_class->getInterfaceNames())) {
            return $ref_class->getMethod("GetSchema")->invoke(null);
        }
        return Schema::Create();
    }

    private function getTableName()
    {
        if (!class_exists($this->class)) {
            return $this->class;
        }

        $ref_class = new ReflectionClass($this->class);
        return $ref_class->getStaticPropertyValue("_table", $ref_class->getShortName());
    }

    public function getPrimaryKey(): string
    {
        $schema = $this->getSchema();
        return  $schema->getTablePrimaryKey($this->getTableName());
    }

    public function getFields()
    {
        $schema = $this->getSchema();
        return  $schema->getTable($this->getTableName())->getColumns();
    }

    /**
     * @return array<T>
     */
    public function get()
    {
        $schema = Schema::Create();
        $primary_key = $this->getPrimaryKey();

        $select = $this->select;
        if (count($this->fields) > 0) {
            //if custom fields are set, primary key is required
            $this->fields[] = $primary_key;
            $select->columns($this->fields);
        }


        $sql = $select->getSqlString($schema->getPlatform());


        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
        }

        if ($this->offset) {
            $sql .= " OFFSET " . $this->offset;
        }

        $statement = $schema->prepare($sql);
        $statement->execute();


        $need_hydrator = false;
        if (count($this->fields) > 0) {
            $statement->setFetchMode(PDO::FETCH_CLASS, stdClass::class);
        } else {
            $need_hydrator = true;
            if (class_exists($this->class)) {


                //injection
                //reflection 
                $args = [];
                $ref_class = new ReflectionClass($this->class);
                if ($constructor = $ref_class->getConstructor()) {
                    $ref_params = array_map(function ($item) {
                        return $item->getClass()->getName();
                    }, $constructor->getParameters());

                    if ($container = $schema->getContainer()) {
                        foreach ($ref_params as $param) {
                            if ($container->has($param)) {
                                $args[] = $container->get($param);
                            } else {
                                $args[] = null;
                            }
                        }
                    } else {
                        $args[] = null;
                    }
                }


                $statement->setFetchMode(PDO::FETCH_CLASS, $this->class, $args);
            } else {
                $statement->setFetchMode(PDO::FETCH_CLASS, stdClass::class);
            }
        }

        if ($need_hydrator) {
            $attributes = [];
            foreach ($this->getFields() as $field) {
                switch ($field->getType()) {
                    case "json":
                        $attributes[$field->getName()] = function ($v) {
                            if (!is_array($v)) {
                                return json_decode($v, true);
                            }
                            return $v;
                        };
                        break;
                    case "tinyint(1)":
                        $attributes[$field->getName()] = function ($v) {

                            if (!is_bool($v)) {
                                return (bool)$v;
                            }
                            return $v;
                        };
                        break;
                }
            }
        }


        $data = [];
        foreach ($statement as $o) {

            if ($need_hydrator) {
                $o = (function ($o) use ($attributes) {
                    foreach ($attributes as $name => $callback) {
                        $o->$name = $callback($o->$name);
                    }
                    return $o;
                })($o);
            }


            foreach ($this->populate as $class => $qq) {

                $q = Q($class, $qq);

                $key = $q->getPrimaryKey();

                if (!property_exists($o, $key)) {

                    $o->$class = $q->where([$primary_key => $o->$primary_key])->get();
                } else {
                    $r = $q->where([$key => $o->$key])->get();
                    if (count($r) > 0) {
                        $o->$class = $r[0];
                    } else {
                        $o->$class = null;
                    }
                }
            }
            $data[] = $o;
        }
        return $data;
    }

    public function __get($name)
    {
        if ($name === "where") {
            return $this->select->where;
        }
        return null;
    }

    public function getMeta(): array
    {

        $clone = clone $this;

        $clone->select->columns([
            "count" => new Expression("COUNT(*)")
        ]);

        return [
            "total" => ($clone->get()[0])->count
        ];
    }

    public function insert(array $data)
    {
        $schema = $this->getSchema();
        $table = $schema->getTable($this->getTableName());

        $primary_key = $this->getPrimaryKey();

        if (isset($data[$primary_key])) {
            unset($data[$primary_key]);
        }

        return $table->insert($data);
    }

    public function update(array $data)
    {
        $schema = $this->getSchema();
        $table = $schema->getTable($this->getTableName());

        $primary_key = $this->getPrimaryKey();

        return $table->update($data, [$primary_key => $data[$primary_key]]);
    }

    public function delete()
    {
        $schema = $this->getSchema();
        $table = $schema->getTable($this->getTableName());
        return $table->delete($this->select->where);
    }
}
