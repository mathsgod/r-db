<?php

namespace R\DB;

use ArrayObject;
use PDO;
use Exception;
use ReflectionObject;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\ObjectPropertyHydrator;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\VarExporter\Internal\Hydrator;

abstract class Model implements ModelInterface
{
    const NUMERIC_DATA_TYPE = ["tinyint", "smallint", "mediumint", "int", "bigint", "float", "double", "decimal"];
    const INT_DATA_TYPE = ["tinyint", "smallint", "mediumint", "int", "bigint"];
    const FLOAT_DATA_TYPE = ["float", "double", "decimal"];

    protected $_key;
    /**
     * @var Schema
     */
    protected $_schema;

    private static $_keys = [];
    private static $_attributes = [];


    /**
     * @var ValidatorInterface|null
     */
    private $_validator;

    /**
     * @var Schema
     */
    static $schema;

    static function SetSchema(Schema $schema)
    {
        self::$schema = $schema;
    }

    static function GetSchema()
    {
        return self::$schema;
    }

    function __construct($id = null)
    {
        $this->_schema = self::$schema;

        if (is_null($id)) {
            foreach (static::__attribute() as $attribute) {
                $this->{$attribute["Field"]} = null;
                if ($attribute["Default"] != null) {
                    $type = explode("(", $attribute["Type"])[0];
                    if ($attribute["Type"] == "tinyint(1)") { //bool
                        $this->{$attribute["Field"]} = (bool)$attribute["Default"];
                    } elseif (in_array($type, self::INT_DATA_TYPE)) {
                        $this->{$attribute["Field"]} = (int)$attribute["Default"];
                    } elseif (in_array($type, self::FLOAT_DATA_TYPE)) {
                        $this->{$attribute["Field"]} = (float)$attribute["Default"];
                    } else {
                        $this->{$attribute["Field"]} = (string)$attribute["Default"];
                    }
                }
            }
        } else {
            $table = static::_table()->name;
            $key = static::_key();
            if (is_null($key)) {
                throw new Exception("Key not found");
            }

            $select = new Select($table);

            if (is_array($id)) {
                foreach ($id as $k => $v) {
                    if (in_array($k, $key)) {
                        $select->where([$k => $v]);
                    }
                }
            } else {
                $select->where([$key => $id]);
            }

            $sql = $select->getSqlString(static::GetSchema()->getPlatform());
            $s = static::GetSchema()->prepare($sql);
            $s->execute();
            $s->setFetchMode(PDO::FETCH_INTO, $this);

            if ($s->fetch() === false) {
                throw new Exception("$table:$id", 404);
            }
            foreach ($this->__attribute() as $a) {
                $n = $a["Field"];
                if ($a["Type"] == "json") {
                    $this->$n = json_decode($this->$n, true);
                }
                if ($a["Type"] == "tinyint(1)") {
                    $this->$n = (bool)$this->$n;
                }
            }
        }
    }


    static function Create(?array $data = []): static
    {
        $obj = new static;
        foreach (get_object_vars($obj) as $key => $val) {
            if ($key[0] == "_") continue;

            if (array_key_exists($key, $data)) {
                $obj->$key = $data[$key];
            }
        }
        return $obj;
    }

    function setValidator(ValidatorInterface $validator)
    {
        $this->_validator = $validator;
    }

    function getValidator(): ValidatorInterface
    {
        return $this->_validator ?? self::GetSchema()->getValidator();
    }

    /**
     * direct get the data from database
     * @param int|string|array $id
     */
    static function Get($id): ?static
    {
        $key = self::_key();
        if (is_array($key)) {
            $q = self::Query($id);
        } else {
            $q = self::Query([$key => $id]);
        }

        return $q->first();
    }

    // change to proxy object later
    static function Load($id): static
    {
        $key = self::_key();
        return self::Query([$key => $id])->first();
    }

    static function _table(): \R\DB\Table
    {
        $class = new \ReflectionClass(get_called_class());
        $props = $class->getStaticProperties();

        $table = $class->getShortName();
        if (isset($props["_table"]))
            $table = $props["_table"];

        return static::GetSchema()->table($table);
    }

    /**
     * get the primary key of the model,
     * if the model has no primary key, return null,
     * if the model has multiple primary keys, return an array
     * @return string|array|null
     */
    static function _key()
    {
        $class = get_called_class();
        if (isset(self::$_keys[$class])) return self::$_keys[$class];
        $keys = [];
        foreach (static::__attribute() as $attribute) {
            if ($attribute["Key"] == "PRI") {
                $keys[] = $attribute["Field"];
            }
        }

        if (count($keys) == 0) {
            self::$_keys[$class] = null;
        } elseif (count($keys) == 1) {
            self::$_keys[$class] = $keys[0];
        } else {
            self::$_keys[$class] = $keys;
        }
        return self::$_keys[$class];
    }

    // get the attributes of the model
    static function __attribute(string $name = null)
    {
        if ($name) {
            foreach (self::__attribute() as $attribute) {
                if ($attribute["Field"] == $name) {
                    return $attribute;
                }
            }
            return null;
        }
        $class = get_called_class();
        if (isset(self::$_attributes[$class])) return self::$_attributes[$class];

        return self::$_attributes[$class] = static::_table()->describe();
    }


    function save()
    {
        $error = $this->getValidator()->validate($this);
        if ($error->count() !== 0) {
            throw new Exception($error->get(0)->getMessage());
        }

        $dispatcher = self::GetSchema()->eventDispatcher();
        $gateway = static::__table_gateway();

        $key = static::_key();


        $hydrator = new ObjectPropertyHydrator();
        $source = $hydrator->extract($this);

        if ($this->$key) { // update
            $mode = "update";
            $dispatcher->dispatch(new Event\BeforeUpdate($this));
        } else { // insert
            $mode = "insert";
            $dispatcher->dispatch(new Event\BeforeInsert($this));
        }

        // generate record
        $records = [];
        $generated = [];

        foreach ($source as $name => $value) {
            if ($name[0] == "_" || $name == $key)
                continue;

            $attribute = self::__attribute($name);

            $extra = $attribute["Extra"];

            if ($extra == "STORED GENERATED" || $extra == "VIRTUAL GENERATED") {
                $generated[] = $name;
                continue;
            }

            $records[$name] = $value;

            $type = explode("(", $attribute["Type"])[0];

            if ($attribute["Type"] == "json") {
                $records[$name] = json_encode($records[$name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($records[$name] === false) {
                    $records[$name] = null;
                }
            }

            if (($attribute["Type"] == "longtext" || $attribute["Type"] == "text") && is_object($records[$name])) {
                $records[$name] = json_encode($records[$name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($records[$name] === false) {
                    $records[$name] = null;
                }
            }


            if ($attribute["Null"] == "NO" && $records[$name] === null) {
                $records[$name] = "";
            }

            //如果是uni 而value是"",直接set null, 因為uniqie key 不會check null value
            if ($attribute["Key"] == "UNI" && $records[$name] === "") {
                $records[$name] = null;
            }

            if ($records[$name] === "") {
                if ($type == "date" || $type == "datetime" || $type == "time" || $type == "enum") {
                    $records[$name] = null;
                }
            }

            if (in_array($type, self::NUMERIC_DATA_TYPE) && $attribute["Null"] == "YES" && $records[$name] === "") {
                $records[$name] = null;
            }

            if (is_array($records[$name])) {
                $records[$name] = implode(",", $records[$name]);
            }

            if ($records[$name] === false) {
                $records[$name] = 0;
            } elseif ($records[$name] === true) {
                $records[$name] = 1;
            }
        }

        if ($mode == "update") { // update
            $ret = $gateway->update($records, [$key => $this->$key]);
            $dispatcher->dispatch(new Event\AfterUpdate($this, $source));
        } else {
            $ret = $gateway->insert($records);
            $this->$key = $gateway->getLastInsertValue(); //save the id
            $dispatcher->dispatch(new Event\AfterInsert($this));
        }

        //   if (count($generated)) {
        /*             $table = static::_table();
            $s = $table->select($generated)->where([$key => $this->$key])->get();
            foreach ($s->fetch() as $name => $value) {
                $this->$name = $value;
            }
 */
        // }

        return $ret;
    }

    static function __table_gateway()
    {
        return new TableGateway(self::_table()->name, static::GetSchema()->getDbAdatpter());
    }

    function _id()
    {
        $key = $this->_key();
        if (is_array($key)) {
            $id = [];
            foreach ($key as $k) {
                $id[$k] = $this->$k;
            }
            return $id;
        }
        return $this->$key;
    }

    function delete()
    {
        $key = static::_key();
        $gateway = static::__table_gateway();
        $dispatcher = self::GetSchema()->eventDispatcher();
        $dispatcher->dispatch(new Event\BeforeDelete($this));
        if (is_array($key)) {
            $result = $gateway->delete($this->_id());
        } else {
            $result = $gateway->delete([$key => $this->$key]);
        }
        $dispatcher->dispatch(new Event\AfterDelete($this));
        return $result;
    }

    function bind($rs)
    {
        foreach (get_object_vars($this) as $key => $val) {
            if ($key[0] == "_") continue;

            if (is_object($rs)) {
                if (property_exists($rs, $key)) {
                    $this->$key = $rs->$key;
                }
            } else {
                if (array_key_exists($key, $rs)) {
                    $this->$key = $rs[$key];
                }
            }
        }
        return $this;
    }

    function __call($class_name, $args)
    {
        $ro = new ReflectionObject($this);

        $namespace = $ro->getNamespaceName();
        if ($namespace == "") {
            $class = $class_name;
        } else {
            $class = $namespace . "\\" . $class_name;
            if (!class_exists($class)) {
                $class = $class_name;
            }
        }

        if (!class_exists($class)) {
            throw new Exception($class . " class not found");
        }

        $key = forward_static_call(array($class, "_key"));

        if (in_array($key, array_keys(get_object_vars($this)))) {
            $id = $this->$key;
            if (!$id) return null;
            return new $class($this->$key);
        }

        $key = static::_key();
        $q = $class::Query([$key => $this->$key]);
        $q->where($args);
        return new ArrayObject($q->toArray());
    }


    /**
     * @return Query<static>
     */
    static function Query(Where|\Closure|string|array|Predicate\PredicateInterface $predicate = null, $combination = Predicate\PredicateSet::OP_AND)
    {
        $query = new Query(static::class);
        if ($predicate) {
            $query->where($predicate, $combination);
        }
        return $query;
    }


    function  __get(string $name)
    {
        $ro = new \ReflectionObject($this);

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

        $key = forward_static_call([$class, "_key"]);
        if (in_array($key, array_keys(get_object_vars($this)))) {
            $id = $this->$key;
            if (!$id) return null;
            return new $class($this->$key);
        }

        $key = static::_key();
        return $class::Query([$key => $this->$key]);
    }

    function __debugInfo()
    {
        $hydrator = new \Laminas\Hydrator\ObjectPropertyHydrator();
        return $hydrator->extract($this);
    }
}
