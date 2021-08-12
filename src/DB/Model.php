<?php

namespace R\DB;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\ObjectPropertyHydrator;
use Laminas\Hydrator\Strategy\SerializableStrategy;
use R\DB\Query;
use ReflectionClass;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\Feature\EventFeature;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\Hydrator\AbstractHydrator;
use Laminas\Hydrator\Strategy\ScalarTypeStrategy;
use ReflectionObject;

abstract class Model implements AdapterAwareInterface
{
    private static $_hydrator;
    private static $_columns;
    private static $_key;
    protected static $_adapter = null;

    public function setDbAdapter(Adapter $adapter)
    {
        \Laminas\Json\Json::$useBuiltinEncoderDecoder = true;
        self::$_adapter = $adapter;
    }

    public function __get(string $name)
    {
        $ro = new ReflectionObject($this);
        $namespace = $ro->getNamespaceName();
        if ($namespace == "") {
            $class = $name;
        } else {
            $class = $namespace . "\\" . $name;
            if (!class_exists($class)) {
                $class = $name;
            }
        }

        if (!class_exists($class)) {
            return null;
        }

        $key = forward_static_call([$class, "__key"]);
        if (in_array($key, array_keys(get_object_vars($this)))) {
            $id = $this->$key;
            if (!$id) return null;
            return $class::Load($this->$key);
        }

        $key = static::__key();
        return $class::Query([$key => $this->$key]);
    }

    public function save()
    {
        $a = self::__hydrator()->extract($this);
        $key = self::__key();
        $gateway = new TableGateway(self::__table_name(), self::$_adapter);
        if ($a[self::__key()]) {
            return $gateway->update($a, [self::__key() => $a[self::__key()]]);
        } else {
            $result = $gateway->insert($a);
            $this->$key = $gateway->getLastInsertValue();
            return $result;
        }
    }

    public function delete()
    {
        $key = self::__key();
        $features = [];
        if ($this instanceof EventManagerAwareInterface) {
            $features[] = new EventFeature($this->getEventManager());
        }

        $gateway = new TableGateway(self::__table_name(), self::$_adapter, $features);
        return $gateway->delete([$key => $this->$key]);
    }


    public static function Create(): static
    {
        $set = [];
        foreach (self::__columns() as $column) {
            $set[$column->getName()] = $column->getColumnDefault();
        }

        $hydrator = self::__hydrator();
        return $hydrator->hydrate($set, new static);
    }

    public static function Load(int $id): ?static
    {

        $query = self::Query([self::__key() => $id]);
        $rs = $query->getIterator();
        if ($rs->count() == 0) {
            return null;
        }
        foreach ($rs as $r) {
            return $r;
        }
    }


    /**
     * Create where clause
     *
     * @param  Where|\Closure|string|array|Predicate\PredicateInterface $predicate
     * @param  string $combination One of the OP_* constants from Predicate\PredicateSet
     * @throws Exception\InvalidArgumentException
     */
    public static function Query($predicate = null, $combination = Predicate\PredicateSet::OP_AND)
    {
        $query = new Query(self::__table_name(), new static, self::__hydrator());
        $query->setDbAdapter(self::$_adapter);
        if ($predicate) {
            $query->where($predicate, $combination);
        }

        return $query;
    }

    public static function __hydrator(): AbstractHydrator
    {
        if (self::$_hydrator) {
            return self::$_hydrator;
        }
        $columns = self::__columns();
        $hydrator = new ObjectPropertyHydrator();

        foreach ($columns as $column) {
            $datatype = $column->getDataType();

            $name = $column->getName();
            if ($datatype == "json") {
                $hydrator->addStrategy($name, new SerializableStrategy(new \Laminas\Serializer\Adapter\Json()));
            } elseif ($datatype == "int") {
                $hydrator->addStrategy($name, ScalarTypeStrategy::createToInt());
            } elseif ($datatype == "float") {
                $hydrator->addStrategy($name, ScalarTypeStrategy::createToFloat());
            } elseif ($datatype == "tinyint") {
                $hydrator->addStrategy($name, ScalarTypeStrategy::createToBoolean());
            }
        }

        return self::$_hydrator = $hydrator;
    }

    public static function __key(): ?string
    {

        if (self::$_key) return self::$_key;
        $constraints = \Laminas\Db\Metadata\Source\Factory::createSourceFromAdapter(self::$_adapter)->getConstraints(self::__table_name());

        foreach ($constraints as $constraint) {
            if ($constraint->getType() == "PRIMARY KEY") {
                return self::$_key = $constraint->getColumns()[0];
            }
        }
        return null;
    }

    /**
     * @return \Laminas\Db\Metadata\Object\ColumnObject[]
     */
    public static function __columns()
    {
        if (self::$_columns) return self::$_columns;
        $metadata = \Laminas\Db\Metadata\Source\Factory::createSourceFromAdapter(self::$_adapter);
        return self::$_columns = $metadata->getColumns(self::__table_name());
    }

    public static function __table_name()
    {
        $rc = new ReflectionClass(static::class);
        return $rc->getShortName();
    }

    public static function __table_gateway()
    {
        $rc = new ReflectionClass(static::class);
        return new TableGateway($rc->getShortName(), self::$_adapter);
    }

    public static function __table(?array $features = [])
    {

        $model = new static;
        if ($model instanceof EventManagerAwareInterface) {
            $features[] = new EventFeature($model->getEventManager());
        }

        return new TableGateway(self::__table_name(), self::$_adapter, $features);
    }

    public function __call($name, $arguments)
    {
        $ro = new ReflectionObject($this);

        $namespace = $ro->getNamespaceName();
        if ($namespace == "") {
            $class = $name;
        } else {
            $class = $namespace . "\\" . $name;
            if (!class_exists($class)) {
                $class = $name;
            }
        }

        if (!class_exists($class)) {
            return null;
        }

        $key = forward_static_call(array($class, "_key"));

        if (in_array($key, array_keys(get_object_vars($this)))) {
            $id = $this->$key;
            if (!$id) return null;
            return new $class($this->$key);
        }

        $key = static::__key();
        $q = $class::Query([$key => $this->$key]);
        $q->where($arguments);
        return iterator_to_array($q);
    }
}
