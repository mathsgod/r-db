<?php

namespace R\DB;

use Closure;
use Laminas\Db\Adapter\Platform\Mysql;
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

    /**
     * @param class-string<T> $class
     */
    public function __construct(string $class)
    {
        $this->class = $class;

        $ref_class = new ReflectionClass($class);
        $short_name = $ref_class->getShortName();
        $this->select = new Select($short_name);
    }

    public function fields(array $fields)
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * @param Where|Closure|string|array|Predicate\PredicateInterface $predicate
     */
    public function where(array $predicate, $combination = Predicate\PredicateSet::OP_AND)
    {
        $this->select->where($predicate, $combination);
        return $this;
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
        return $this;
    }

    /**
     * @param int $limit
     * @return $this Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @param Q[] $populate
     */
    public function populate(array $populate)
    {
        $this->populate = $populate;
        return $this;
    }

    private function getSchema(): Schema
    {
        $ref_class = new ReflectionClass($this->class);
        $short_name = $ref_class->getShortName();

        if (in_array(SchemaAwareInterface::class, $ref_class->getInterfaceNames())) {
            return $ref_class->getMethod("GetSchema")->invoke(null);
        }
        return Schema::Create();
    }

    private function getTableName()
    {
        $ref_class = new ReflectionClass($this->class);
        return $ref_class->getStaticPropertyValue("_table", $ref_class->getShortName());
    }

    private function getPrimaryKey(): string
    {
        $schema = $this->getSchema();
        return  $schema->getTablePrimaryKey($this->getTableName());
    }

    /**
     * @return array<T>
     */
    public function get()
    {
        $primary_key = $this->getPrimaryKey();

        $select = $this->select;
        if (count($this->fields) > 0) {
            //if custom fields are set, primary key is required
            $this->fields[] = $primary_key;



            //load populate class fields
            foreach ($this->populate as $q) {
                $key = $q->getPrimaryKey();

                //check table has this field
                if ($this->getSchema()->hasTableColumn($this->getName(), $key)) {
                    $this->fields[] = $key;
                }
            }


            $select->columns($this->fields);
        }

        $sql = $select->getSqlString(new Mysql());

        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
        }

        $schema = Schema::Create();
        $statement = $schema->prepare($sql);
        $statement->execute();

        if (count($this->fields) > 0) {
            $statement->setFetchMode(PDO::FETCH_CLASS, stdClass::class);
        } else {
            $statement->setFetchMode(PDO::FETCH_CLASS, $this->class);
        }


        $data = [];
        foreach ($statement as $obj) {

            foreach ($this->populate as  $q) {
                $key = $q->getPrimaryKey();
                $name = $q->getName();
                if ($obj->$key === null) {
                    $obj->$name = $q->where([$primary_key => $obj->$primary_key])->get();
                } else {
                    $r = $q->where([$key => $obj->$key])->get();
                    if (count($r) > 0) {
                        $obj->$name = $r[0];
                    } else {
                        $obj->$name = null;
                    }
                }
            }

            $data[] = $obj;
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
}