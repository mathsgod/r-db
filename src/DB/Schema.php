<?php

namespace R\DB;

use PDO;
use PDOException;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class Schema extends PDO implements LoggerAwareInterface
{
    private $logger;
    public function __construct(string $database, string $hostname, string $username, string $password = "", string $charset = "utf8", LoggerInterface $logger = null)
    {
        //PDO::ERRMODE_EXCEPTION;
        $this->logger = $logger;

        try {
            parent::__construct("mysql:dbname={$database};host={$hostname};charset={$charset}", $username, $password, [
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            echo $e->getMessage();
            if ($this->logger) $logger->error("SQLSTATE[HY000] [1045] Access denied");
            exit();
        }
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function table(string $name)
    {
        return new Table($this, $name);
    }

    public function hasTable($name)
    {
        $data = [];
        $s = $this->query("SHOW TABLES");
        while ($r = $s->fetchColumn(0)) {
            $data[] = $r;
        }
        return in_array($name, $data);
    }

    private function tables()
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
        return $this->query("SHOW FUNCTION STATUS")->fetchAll();
    }

    private function _procedure()
    {
        return $this->query("SHOW PROCEDURE STATUS")->fetchAll();
    }

    public function __get(string $name)
    {
        if ($name == "tables") {
            return $this->tables();
        }
        if ($name == "function") {
            return $this->_function();
        }
        if ($name == "procedure") {
            return $this->_procedure();
        }
        return $this->$name;
    }

    public function logger()
    {
        return $this->logger;
    }

    public function createTable(string $name, array $columns = [])
    {
        $sql = [];
        foreach ($columns as $column) {
            $sql[] = "`{$column['name']}` {$column['type']} {$column['constrain']}";
        }
        $s = implode(",", $sql);

        return $this->exec("CREATE TABLE `$name` ( $s )");
    }

    public function dropTable(string $name)
    {
        return $this->exec("DROP TABLE `$name`");
    }

    public function query(string $sql)
    {
        if ($this->logger) $this->logger->debug("PDO::query", func_get_args());
        $reflector = new \ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('query');
        return $method->invokeArgs($this, func_get_args());
    }

    public function prepare(string $statement, $options = null)
    {
        if ($this->logger) $this->logger->debug("PDO::prepare", func_get_args());
        $reflector = new \ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('prepare');
        return $method->invokeArgs($this, func_get_args());
    }

    public function from(string $table)
    {
        return new Query($this, $table);
    }

    public function exec(string $query)
    {
        if ($this->logger) $this->logger->debug("PDO::exec", func_get_args());
        $reflector = new \ReflectionClass(get_class($this));
        $parent = $reflector->getParentClass();
        $method = $parent->getMethod('exec');
        $ret = $method->invokeArgs($this, func_get_args());
        if ($ret === false) {
            $error = $this->errorInfo();
            throw new Exception($error[2], $error[1]);
        }
        return $ret;
    }
}
