<?php

namespace R\DB;

use ArrayObject;
use PDO;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Laminas\Db\Sql\Predicate;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

abstract class Model
{
    const NUMERIC_DATA_TYPE = ["tinyint", "smallint", "mediumint", "int", "bigint", "float", "double", "decimal"];
    const INT_DATA_TYPE = ["tinyint", "smallint", "mediumint", "int", "bigint"];
    const FLOAT_DATA_TYPE = ["float", "double", "decimal"];

    protected $_key;

    private static $_Keys = [];
    private static $_Attributes = [];


    static $schema;
    public static function SetSchema(Schema $schema)
    {
        self::$schema = $schema;
    }

    public static function GetSchema()
    {
        return self::$schema;
    }

    public static function Create(): static
    {
        return new static;
    }

    public static function Load(int $id): ?static
    {
        $key = self::_key();
        return self::Query([$key => $id])->first();
    }

    public static function _table(): \R\DB\Table
    {
        $class = new \ReflectionClass(get_called_class());
        $props = $class->getStaticProperties();

        $table = $class->getShortName();
        if (isset($props["_table"]))
            $table = $props["_table"];

        return static::$schema->table($table);
    }

    public static function _key()
    {
        $class = get_called_class();
        if (isset(self::$_Keys[$class])) return self::$_Keys[$class];
        foreach (static::__attribute() as $attribute) {
            if ($attribute["Key"] == "PRI") {
                return self::$_Keys[$class] = $attribute["Field"];
            }
        }
    }

    public static function __attribute(string $name = null)
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
        if (isset(self::$_Attributes[$class])) return self::$_Attributes[$class];

        return self::$_Attributes[$class] = static::_table()->describe();
    }

    public function __construct($id = null)
    {
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
            $select = new Select($table);
            $select->where([$key => $id]);
            $sql = $select->getSqlString(static::$schema->getPlatform());
            $s = static::$schema->prepare($sql);
            $s->execute();
            $s->setFetchMode(PDO::FETCH_INTO, $this);

            if ($s->fetch() === false) {
                throw new \Exception("$table:$id", 404);
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

    public function save()
    {
        $key = static::_key();

        $records = [];
        $generated = [];


        foreach (get_object_vars($this) as $name => $value) {
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


        //exists before insert
        $class = new ReflectionClass(get_called_class());
        $methods = $class->getMethods(ReflectionMethod::IS_STATIC);
        $methods = array_column($methods, "name");

        if ($this->$key) { // update

            if (in_array("BeforeUpdate", $methods)) {
                $method = $class->getMethod("BeforeUpdate");
                $old = new static($this->$key);
                $method->invoke(null, $this, $old);
            }

            if (in_array("AfterUpdate", $methods)) {
                $old = new static($this->$key);
            }

            $gateway = static::__table_gateway();
            $ret = $gateway->update($records, [$key => $this->$key]);

            if (in_array("AfterUpdate", $methods)) {
                $method = $class->getMethod("AfterUpdate");
                $method->invoke(null, new static($this->$key), $old);
            }
        } else {


            if (in_array("BeforeInsert", $methods)) {
                $method = $class->getMethod("BeforeInsert");
                $method->invoke(null, $this);
            }

            $gateway = static::__table_gateway();
            $ret = $gateway->insert($records);

            $this->$key = $gateway->getLastInsertValue();

            if (in_array("AfterInsert", $methods)) {
                $method = $class->getMethod("AfterInsert");
                $method->invoke(null, new static($this->$key));
            }
        }

        if (count($generated)) {
            /*             $table = static::_table();
            $s = $table->select($generated)->where([$key => $this->$key])->get();
            foreach ($s->fetch() as $name => $value) {
                $this->$name = $value;
            }
 */
        }

        return $ret;
    }

    public static function __table_gateway()
    {
        return new TableGateway(self::_table()->name, static::$schema->getDbAdatpter());
    }

    public function _id()
    {
        $key = $this->_key();
        return $this->$key;
    }

    public function delete()
    {
        $key = static::_key();
        $gateway = new TableGateway(self::_table()->name, static::$schema->getDbAdatpter());
        return $gateway->delete([$key => $this->$key]);
    }

    public function bind($rs)
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

    public function __call($class_name, $args)
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
    public static function Query(Where|\Closure|string|array|Predicate\PredicateInterface $predicate = null, $combination = Predicate\PredicateSet::OP_AND)
    {
        $query = new Query(static::class);
        if ($predicate) {
            $query->where($predicate, $combination);
        }
        return $query;
    }


    public function __get(string $name)
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
}
