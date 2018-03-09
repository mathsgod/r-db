<?php
namespace DB;

abstract class Model
{
    protected $_key;

    private static $_Keys = [];
    private static $_Attributes = [];

    abstract public static function __db();

    public static function __table()
    {
        $class = new \ReflectionClass(get_called_class());
        $props = $class->getStaticProperties();

        $table = $class->getShortName();
        if ($props["_table"])
            $table = $props["_table"];

        return static::__db()->table($table);
    }

    public static function __key()
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

        self::$_Attributes[$class] = static::__table()->describe();
        return self::$_Attributes[$class];
    }

    public function __construct($id = null)
    {
        if (is_null($id)) {
            foreach (static::__attribute() as $attribute) {
                $this->{$attribute["Field"]} = $attribute["Default"];
            }
        } else {
            $key = static::__key();
            $rs = static::__table()->where("$key=:$key", [$key => $id])->select();
            if (count($rs)) {
                foreach ($rs[0] as $n => $v) {
                    $this->$n = $v;
                }
            } else {
                $table = static::__table();
                throw new \Exception("$table:$id not found", 404);
            }
        }
    }

    public function save()
    {
        $key = static::__key();
        $records = array();
        foreach (get_object_vars($this) as $name => $value) {
            if (is_null($value) || $name[0] == "_" || $name == $key)
                continue;
            $records[$name] = ($value === "") ? null : $value;

            if ($attribue = self::__attribute($name)) {
                if ($attribue["Null"] == "YES" && $records[$name] === "") {
                    $records[$name] = null;
                } elseif ($attribue["Null"] == "NO" && $records[$name] === null) {
                    unset($records[$name]);
                }
            }
        }
        if ($id = $this->id()) { // update
            return static::__table()->where("$key=:$key", [$key => $id])->update($records);
        } else {
            $table = static::__table();
            $stm = $table->insert($records);
            $this->$key = $table->db()->lastInsertId();
            return $stm;
        }
    }

    public function update($records = [])
    {
        $key = static::__key();
        return static::__table()->where("`$key`=:$key", [$key => $this->$key])->update($records);
    }

    public function delete()
    {
        $key = static::__key();
        return static::__table()->where("`$key`=:$key", [$key => $this->$key])->delete();
    }
}
