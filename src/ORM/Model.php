<?php
namespace R\ORM;

use PDO;
use R\RSList;
use Exception;
use R\DataList;

abstract class Model
{
    protected $_key;

    private static $_Keys = [];
    private static $_Attributes = [];

    abstract public static function __db();

    public static function _table()
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

    public static function __attribute($name = null)
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

        self::$_Attributes[$class] = static::_table()->d7escribe();
        return self::$_Attributes[$class];
    }

    public function __construct($id = null)
    {
        if (is_null($id)) {
            foreach (static::__attribute() as $attribute) {
                $this->{$attribute["Field"]} = $attribute["Default"];
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

        foreach (get_object_vars($this) as $name => $value) {
            if (is_null($value) || $name[0] == "_" || $name == $key)
                continue;
            $records[$name] = ($value === "") ? null : $value;

            if ($attribue = self::__attribute($name)) {
                if ($attribue["Type"] == "json") {
                    $records[$name] = json_encode($records[$name], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if ($attribue["Null"] == "YES" && $records[$name] === "") {
                    $records[$name] = null;
                } elseif ($attribue["Null"] == "NO" && $records[$name] === null) {
                    unset($records[$name]);
                }
            }
        }
        if ($id = $this->$key) { // update
            return $this->update($records);
        } else {
            $table = static::_table();
            unset($records[$key]);
            $ret = $table->insert($records);
            $this->$key = $table->db()->lastInsertId();
            return $ret;
        }
    }

    public function _id()
    {
        $key = $this->_key();
        return $this->$key;
    }

    public function update($records = [])
    {
        $key = static::_key();
        return static::_table()->where([$key => $this->$key])->update($records);
    }

    public function delete()
    {
        $key = static::_key();
        return static::_table()->where([$key => $this->$key])->delete();
    }

    public static function Find($where = null, $order = null, $limit = null)
    {
        $sth = static::_table()->find($where, $order, $limit);
        $sth->execute();
        $sth->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, get_called_class(), []);
        return new RSList($sth);
    }

    public static function First($where = null, $order = null)
    {
        return self::Find($where, $order, 1)->first();
    }

    public function __call($class_name, $args)
    {
        $ro = new \ReflectionObject($this);

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
            throw new \Exception($class . " class not found");
        }

        $key = forward_static_call(array($class, "_key"));

        if (in_array($key, array_keys(get_object_vars($this)))) {
            $id = $this->$key;
            if (!$id) return null;
            return new $class($this->$key);
        }

        if (!$this->_id()) {
            return new DataList();
        }
        $key = static::_key();
        if (is_array($args[0])) {
            $args[0][] = "{$key}={$this->_id()}";
        } else {
            if ($args[0] != "") {
                $args[0] = "({$key}={$this->_id()}) AND ($args[0])";
            } else {
                $args[0] = "{$key}={$this->_id()}";
            }
        }

        return forward_static_call_array(array($class, "find"), $args);
    }

    public static function Query($where = [])
    {
        $q = new Query(get_called_class());
        $q->where($where);
        return $q->select();
    }

    public function __get($name)
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

        $key = forward_static_call(array($class, "_key"));
        if (in_array($key, array_keys(get_object_vars($this)))) {
            $id = $this->$key;
            if (!$id) return null;
            return new $class($this->$key);
        }

        $key = static::_key();
        return $class::Query([$key => $this->$key]);
    }


    public static function Scalar($query, $where = null)
    {
        return self::_table()->where($where)->select([$query])->get()->fetchColumn(0);
    }

    public static function Count($where = null)
    {
        return self::_table()->where($where)->count();
    }

    public static function Distinct($query, $where = null)
    {
        return self::_table()->where($where)->select(["distinct $query"])->get()->fetchAll();
    }
}
