<?php

namespace R\DB;

use PDO;
use PDOStatement;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Db\Adapter\Platform\Mysql;
use Laminas\Db\TableGateway\TableGateway;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class Schema implements LoggerAwareInterface, AdapterAwareInterface
{
    use AdapterAwareTrait;
    use LoggerAwareTrait;

    public function __construct(string $database, string $hostname, string $username, string $password = "", string $charset = "utf8mb4", int $port = 3306)
    {
        $adapter = new Adapter([
            "database" => $database,
            "hostname" => $hostname,
            "username" => $username,
            "password" => $password,
            "charset" => $charset,
            "driver" => "Pdo_Mysql",
            "driver_options" => [
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ]);
        $this->setDbAdapter($adapter);
    }

    public function getDbAdatpter()
    {
        return $this->adapter;
    }

    public function table(string $name)
    {
        return new Table($this, $name);
    }

    /**
     * Create statement
     *
     * @param  string $initialSql
     * @param  ParameterContainer $initialParameters
     */
    public function createStatement($initialSql = null, $initialParameters = null): PDOStatement
    {
        $statement = $this->adapter->createStatement($initialSql, $initialParameters);
        $statement->prepare();
        return $statement->getResource();
    }


    public function getPlatform()
    {
        return $this->adapter->getPlatform();
    }

    public function hasTable($name): bool
    {
        $data = [];
        $s = $this->query("SHOW TABLES");
        while ($r = $s->fetchColumn(0)) {
            $data[] = $r;
        }
        return in_array($name, $data);
    }

    private function tables(): array
    {
        $data = [];
        $s = $this->query("SHOW TABLES");
        while ($r = $s->fetchColumn(0)) {
            $data[] = $this->table($r);
        }
        return $data;
    }

    public function __get(string $name)
    {
        if ($name == "tables") {
            return $this->tables();
        }
        if ($name == "function") {
            return $this->query("SHOW FUNCTION STATUS")->fetchAll();
        }
        if ($name == "procedure") {
            return $this->query("SHOW PROCEDURE STATUS")->fetchAll();
        }
        return $this->$name;
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

    /**
     * @return PDOStatement 
     */
    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args)
    {
        $statement = $this->adapter->query($statement);
        $statement->execute();
        return $statement->getResource();
    }

    /**
     * @return PDOStatement 
     */
    public function prepare($statement, $options = null)
    {
        if ($this->logger) $this->logger->debug("PDO::prepare", func_get_args());
        $statement = $this->adapter->createStatement($statement);
        $statement->prepare();
        return $statement->getResource();
    }

    
    public function exec($statement)
    {
        $statement = $this->adapter->createStatement($statement);
        $result = $statement->execute();
        return $result->getAffectedRows();
    }
}
