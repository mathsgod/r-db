<?php

namespace R\DB;

use ArrayIterator;
use ArrayObject;
use PDO;
use Exception;
use IteratorAggregate;
use JsonSerializable;
use ReflectionObject;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Traversable;

abstract class Model implements ModelInterface, IteratorAggregate, JsonSerializable
{
    const NUMERIC_DATA_TYPE = ["tinyint", "smallint", "mediumint", "int", "bigint", "float", "double", "decimal"];
    const INT_DATA_TYPE = ["tinyint", "smallint", "mediumint", "int", "bigint"];
    const FLOAT_DATA_TYPE = ["float", "double", "decimal"];

    private static $_keys = [];
    private static $_attributes = [];

    protected $_original = [];
    protected $_fields = [];
    protected $_changed = [];

    /**
     * @var ValidatorInterface|null
     */
    private $_validator;

    /**
     * @var Schema
     */
    static $_schema;

    static function SetSchema(Schema $schema)
    {
        self::$_schema = $schema;
    }

    static function GetSchema(): Schema
    {
        if (self::$_schema == null) {
            self::$_schema = Schema::Create();
        }
        return self::$_schema;
    }

    function __construct($id = null)
    {
        $key = self::_key();
        if (is_null($id)) {
            if ($this->$key) { //already fetch from pdo
                foreach ($this->__fields() as $field) {
                    if (property_exists($this, $field)) {
                        $this->_original[$field] = $this->$field;
                    }
                }

                foreach ($this->_fields as $name => $value) {
                    $this->_original[$name] = $value;
                }

                foreach ($this->_original as $name => $value) {
                    $attribute = $this->__attribute($name);
                    switch ($attribute["Type"]) {
                        case "json":
                            $this->_original[$name] = json_decode($value, true);
                            break;
                        case "tinyint(1)":
                            $this->_original[$name] = (bool)$value;
                            break;
                        default:
                            $this->_original[$name] = $value;
                            break;
                    }
                }
                $this->_fields = [];

                return;
            }
        } else {
            if (is_null($key)) {
                throw new Exception("Key not found");
            }

            $table = static::_table()->name;
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
                throw new Exception("Record not found. Table: $table, Key: $key, ID: $id");
            }

            foreach ($this->__fields() as $field) {
                if (property_exists($this, $field)) {
                    $this->_original[$field] = $this->$field;
                }
            }

            foreach ($this->_fields as $name => $value) {
                $attribute = $this->__attribute($name);
                switch ($attribute["Type"]) {
                    case "json":
                        $this->_original[$name] = json_decode($value, true);
                        break;
                    case "tinyint(1)":
                        $this->_original[$name] = (bool)$value;
                        break;
                    default:
                        $this->_original[$name] = $value;
                        break;
                }
            }

            $this->_fields = [];
        }
    }


    function jsonSerialize()
    {
        $fields = $this->__fields();;
        $data = [];
        foreach ($this->__fields() as $field) {
            $data[$field] = $this->$field;
        }

        foreach ($this->_fields as $field => $value) {
            if (in_array($field, $fields)) {
                continue;
            }
            $data[$field] = $value;
        }

        return $data;
    }

    static function Create(?array $data = []): static
    {

        $obj = new static;

        $fields = $obj->__fields();
        foreach ($data as $field => $value) {
            if (in_array($field, $fields)) {
                $obj->_fields[$field] = $value;
                if (property_exists($obj, $field)) {
                    $obj->$field = $value;
                }
            }
        }

        return $obj;
    }

    function getIterator(): Traversable
    {
        return new ArrayIterator($this->jsonSerialize());
    }

    function setValidator(ValidatorInterface $validator)
    {
        $this->_validator = $validator;
    }

    function getValidator(): ValidatorInterface
    {
        return $this->_validator ?? self::GetSchema()->getValidator();
    }

    static function Get(Where|string|int|array $where): ?static
    {
        if (is_numeric($where) || is_string($where)) {
            $key = self::_key();
            $q = self::Query([$key => $where]);
        } else {
            $q = self::Query($where);
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
        foreach (static::__attributes() as $attribute) {
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
            foreach (self::__attributes() as $attribute) {
                if ($attribute["Field"] == $name) {
                    return $attribute;
                }
            }
            return null;
        }

        return self::__attributes();
    }

    static function __attributes(): array
    {
        $class = get_called_class();
        if (!isset(self::$_attributes[$class])) {
            self::$_attributes[$class] = static::_table()->describe();
        }
        return self::$_attributes[$class];
    }

    private function getDBSet(): array
    {
        $set = $this->getDirty();

        foreach ($this->__fields() as $field) {
            if (property_exists($this, $field)) {
                $set[$field] = $this->$field;
            }
        }

        foreach ($set as $field => $value) {

            $attribute = $this->__attribute($field);
            $extra = $attribute["Extra"];

            if ($extra == "STORED GENERATED" || $extra == "VIRTUAL GENERATED") {
                $generated[] = $field;
                continue;
            }

            if ($value instanceof UploadedFileInterface) {
                $value = $value->getStream()->getContents();
            }

            $type = explode("(", $attribute["Type"])[0];

            if ($attribute["Type"] == "json") {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($value === false) {
                    $value = null;
                }
            }

            if (($attribute["Type"] == "longtext" || $attribute["Type"] == "text") && is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($value === false) {
                    $value = null;
                }
            }

            if ($attribute["Null"] == "NO" && $value === null) {
                $value = "";
            }

            //如果是uni 而value是"",直接set null, 因為uniqie key 不會check null value
            if ($attribute["Key"] == "UNI" && $value === "") {
                $value = null;
            }

            if ($value === "") {
                if ($type == "date" || $type == "datetime" || $type == "time" || $type == "enum") {
                    $value = null;
                }
            }

            if (in_array($type, self::NUMERIC_DATA_TYPE) && $attribute["Null"] == "YES" && $value === "") {
                $value = null;
            }

            if (is_array($value)) {
                $value = implode(",", $value);
            }

            if ($value === false) {
                $value = 0;
            } elseif ($value === true) {
                $value = 1;
            }

            $set[$field] = $value;
        }
        return $set;
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

        if ($this->$key) { // update
            $mode = "update";
        } else { // insert
            $mode = "insert";
        }


        if ($mode == "insert") {

            $dispatcher->dispatch(new Event\BeforeInsert($this));
            $records = $this->getDBSet();
            $records[$key] = null; // key is auto increment
            $ret = $gateway->insert($records);

            $this->$key = $gateway->getLastInsertValue(); //save the id

            //move the data to original
            $this->_original = $this->_fields;
            foreach ($this->__fields() as $field) {
                if (property_exists($this, $field)) {
                    $this->_original[$field] = $this->$field;
                }
            }

            $this->_fields = [];

            $dispatcher->dispatch(new Event\AfterInsert($this));
        } else { //update
            $dispatcher->dispatch(new Event\BeforeUpdate($this));

            $records = [];
            $records = $this->getDBSet();
            $records[$key] = $this->$key;


            $ret = $gateway->update($records, [$key => $this->$key]);
            $dispatcher->dispatch(new Event\AfterUpdate($this));

            //move the data to original
            foreach ($this->_fields as $field => $value) {
                $this->_original[$field] = $value;
            }
            foreach ($this->__fields() as $field) {
                if (property_exists($this, $field)) {
                    $this->_original[$field] = $this->$field;
                }
            }
            $this->_changed = $this->_fields;
            $this->_fields = [];
        }

        $dispatcher->dispatch(new Event\BeforeUpdate($this));


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
        foreach (array_column(self::__attributes(), "Field") as $field) {
            if (is_object($rs)) {
                if (property_exists($rs, $field)) {
                    $this->$field = $rs->$field;
                }
            } else {
                if (array_key_exists($field, $rs)) {
                    $this->$field = $rs[$field];
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
        if (self::__attribute($key)) {
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


    function &__get(string $name)
    {
        if (array_key_exists($name, $this->_fields)) {
            return $this->_fields[$name];
        }

        if ($attribute = self::__attribute($name)) {
            if (array_key_exists($name, $this->_original)) {
                if ($attribute["Type"] == "json") { //should be assign to _fields and return pointer of array value in fields
                    $this->_fields[$name] = $this->_original[$name];
                    return $this->_fields[$name];
                }
                return $this->_original[$name];
            }

            $default = $attribute["Default"];
            if ($default === null && $attribute["Null"] == "YES") {
                return null;
            }

            $type = explode("(", $attribute["Type"])[0];
            if ($attribute["Type"] == "tinyint(1)") { //bool
                return (bool)$default;
            } elseif (in_array($type, self::INT_DATA_TYPE)) {
                return (int)$default;
            } elseif (in_array($type, self::FLOAT_DATA_TYPE)) {
                return (float)$default;
            } else {
                return (string)$default;
            }
        }

        //relation
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

        $key = static::_key();
        return $class::Query([$key => $this->$key]);
    }


    function getDirty(): array
    {
        //get current fields
        $fields = $this->_fields;

        foreach ($this->__fields() as $field) {
            if (property_exists($this, $field)) {
                $fields[$field] = $this->$field;
            }
        }

        $dirty = [];
        foreach ($fields as $field => $value) {
            if ($this->_original[$field] !== $value) {
                $dirty[$field] = $this->$field;
            }
        }
        return $dirty;
    }

    function isDirty(string $name = null): bool
    {
        if (is_null($name)) {
            return count($this->getDirty()) > 0;
        }
        return $this->$name !== $this->_original[$name];
    }

    function wasChanged(string $name = null): bool
    {
        if (is_null($name)) {
            return count($this->_changed) > 0;
        }
        return array_key_exists($name, $this->_changed);
    }

    function getOriginal(string $name = null)
    {
        if ($name === null) {
            return $this->_original;
        }

        return $this->_original[$name];
    }

    function __set($name, $value)
    {
        $this->_fields[$name] = $value;
    }

    function __isset($name)
    {
        return array_key_exists($name, $this->_original) || array_key_exists($name, $this->_fields);
    }

    function __fields(): array
    {
        return array_column(self::__attributes(), "Field");
    }
}
