<?php
namespace R\DB;

class PDO extends \PDO
{
    private $schema;
    private $logger;
    public function __construct($database, $hostname, $username, $password, $charset = "utf8", $logger)
    {
        //\PDO::ERRMODE_EXCEPTION;
        $this->logger = $logger;

        try {
            parent::__construct("mysql:dbname={$database};host={$hostname};charset={$charset}", $username, $password, [
                \PDO::ATTR_PERSISTENT => true,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false
            ]);

            $this->schema = new Schema($this, $database);
        } catch (\PDOException $e) {
            echo $e->getMessage();
            if ($this->logger) $logger->error("SQLSTATE[HY000] [1045] Access denied");
            exit();
        }
    }

    public function update($table, $records, $where)
    {
        return $this->table($table)->where($where)->update($records)->execute();
    }

    public function insert($table, $records)
    {
        return $this->table($table)->insert($records)->execute();
    }

    public function schema()
    {
        return $this->schema;
    }

    public function table($name)
    {
        return new Table($this, $name, $this->logger);
    }

    private function _tables()
    {
        $data = [];
        $s = $this->query("SHOW TABLES");
        while ($r = $s->fetchColumn(0)) {
            $data[] = $this->table($r);
        }
        return $data;
    }

    private function _function()
    {
        $data = [];
        return $this->query("SHOW FUNCTION STATUS")->fetchAll();
        /*while($r=$s->fetchmn()){
            $data[]=
        }
        return $data;*/
    }

    private function _procedure()
    {
        $data = [];
        return $this->query("SHOW PROCEDURE STATUS")->fetchAll();
        /*while($r=$s->fetchmn()){
            $data[]=
        }
        return $data;*/
    }

    public function __get($name)
    {
        if ($name == "tables") {
            return $this->_tables();
        }
        if ($name == "function") {
            return $this->_function();
        }
        if ($name == "procedure") {
            return $this->_procedure();
        }
    }

    public function logger()
    {
        return $this->logger;
    }

    public function query()
    {
        if ($this->logger) $this->logger->debug("PDO::query", func_get_args());
        $reflector = new \ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('query');
        return $method->invokeArgs($this, func_get_args());
    }

    public function prepare($statement)
    {
        if ($this->logger) $this->logger->debug("PDO::prepare", func_get_args());
        $reflector = new \ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('prepare');
        return $method->invokeArgs($this, func_get_args());
    }

    public function from($table)
    {
        return new Query($this, $table);
    }

}
