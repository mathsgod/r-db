<?php

namespace R\DB;

use PDO;
use PDOStatement;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Platform\Mysql;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class Schema implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    public $adatper;

    public function __construct(string $database, string $hostname, string $username, string $password = "", string $charset = "utf8mb4", int $port = 3306)
    {
        $adatper = new Adapter([
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
        $this->adatper = $adatper;
    }

    public function table(string $name)
    {
        return new Table($this, $name);
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
        $statement = $this->adatper->query($statement);
        $statement->execute();
        return $statement->getResource();
    }

    /**
     * @return PDOStatement 
     */
    public function prepare($statement, $options = null)
    {
        if ($this->logger) $this->logger->debug("PDO::prepare", func_get_args());
        $statement = $this->adatper->createStatement($statement);
        $statement->prepare();
        return $statement->getResource();
    }

    public function from(string $table)
    {
        return new Query($this, $table);
    }

    public function exec($statement)
    {
        $statement = $this->adatper->createStatement($statement);
        $result = $statement->execute();
        return $result->getAffectedRows();
    }
}
