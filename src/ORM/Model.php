<?php

namespace R\ORM;

use PDO;
use R\RSList;
use Exception;
use R\DataList;
use ReflectionObject;

abstract class Model
{
    const NUMERIC_DATA_TYPE = ["tinyint", "smallint", "mediumint", "int", "bigint", "float", "double", "decimal"];
    const INT_DATA_TYPE = ["tinyint", "smallint", "mediumint", "int", "bigint"];
    const FLOAT_DATA_TYPE = ["float", "double", "decimal"];

    protected $_key;

    private static $_Keys = [];
    private static $_Attributes = [];

    abstract public static function __db();

    /**
     * Get this object by id, if not found return null.
     * @return static 
     */
    public static function __(int $id)
    {
        $key = self::_key();
        return self::Query([$key => $id])->first();
    }

    public static function _table(): \R\DB\Table
    {
        $class = new \ReflectionClass(get_called_class());
        $props = $class->getStaticProperties();

        $table = $class->getShortName();
        if ($props["_table"])
            $table = $props["_table"];

        return static::__db()->table($table);
    }

    public static function _key()
    {
        $class = get_called_class();
        if (self::$_Keys[$class])
            return self::$_Keys[$class];
        foreach (static::__attribute() as $attribute) {
            if ($attribute["Key"] == "PRI") {
                self::$_Keys[$class] = $attribute["Field"];
                return $attribute["Field"];
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
        if (self::$_Attributes[$class])
            return self::$_Attributes[$class];

        self::$_Attributes[$class] = static::_table()->describe();
        return self::$_Attributes[$class];
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
                    }
                }
            }
        } else {
            $key = static::_key();
            $table = static::_table();
            $s = $table->where([$key => $id])->get();
            $s->setFetchMode(PDO::FETCH_INTO, $this);
            if ($s->fetch() === false) {
                throw new \Exception("$table:$id not found", 404);
            }
            foreach ($this->__attribute() as $a) {
                if ($a["Type"] == "json") {
                    $n = $a["Field"];
                    $this->$n = json_decode($this->$n, true);
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

        if ($this->$key) { // update
            $ret = $this->update($records);
        } else {
            $table = static::_table();
            $ret = $table->insert($records);
            $this->$key = $table->db()->lastInsertId();
        }

        if (count($generated)) {
            $table = static::_table();
            $s = $table->select($generated)->where([$key => $this->$key])->get();
            foreach ($s->fetch() as $name => $value) {
                $this->$name = $value;
            }
        }

        return $ret;
    }

    public function _id()
    {
        $key = $this->_key();
        return $this->$key;
    }

    public function update(array $records = [])
    {
        $key = static::_key();
        return self::Query([$key => $this->$key])->set($records)->update()->execute();
    }

    public function delete()
    {
        $key = static::_key();
        return self::Query([$key => $this->$key])->delete()->execute();
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

    /**
     * @deprecated Use Class:Query($filter)->orderBy($order)->limit($limit)->offset($offset)
     */
    public static function Find($where = null, $order = null, $limit = null)
    {
        $sth = static::_table()->find($where, $order, $limit);
        $sth->execute();
        $sth->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, get_called_class(), []);
        return new RSList($sth, get_called_class());
    }

    /**
     * @deprecated Use Class::Query($filter)->first()
     */
    public static function First($where = null, $order = null)
    {
        return self::Query()->where($where)->orderBy($order)->first();
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
        return $q->toList();
    }

    public static function Query(array $filter = [])
    {
        $q = new Query(get_called_class());
        $q->filter($filter);
        return $q->select();
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


    /**
     * @deprecated use Class::Query($filter)->select(["$query"])->first()
     */
    public static function Scalar($query, $where = null)
    {
        return self::_table()->where($where)->select([$query])->get()->fetchColumn(0);
    }

    /**
     * @deprecated use Class::Query($filter)->count()
     */
    public static function Count($where = null)
    {

        return self::_table()->where($where)->count();
    }

    /**
     * @deprecated use Class:Query($filter)->select(["distinct $query"]);
     */
    public static function Distinct($query, $where = null)
    {
        return self::_table()->where($where)->select(["distinct $query"])->get()->fetchAll();
    }
}
