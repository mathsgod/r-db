<?php

namespace R\DB;

use PDO;
use PDOStatement;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Db\Sql\Ddl\CreateTable;
use Laminas\Db\Sql\Ddl\DropTable;
use Laminas\Db\Sql\Sql;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Schema implements AdapterAwareInterface
{
    use AdapterAwareTrait;

    /**
     * @var ValidatorInterface|null
     */
    private $validator;

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

    function setDefaultValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    function getDefaultValidator()
    {
        return $this->validator;
    }

    public function getDbAdatpter()
    {
        return $this->adapter;
    }

    public function table(string $name)
    {
        $table = new Table($this, $name);
        $table->setDbAdapter($this->adapter);
        return $table;
    }

    /**
     * Create statement
     *
     * @param  string $initialSql
     * @param  ParameterContainer $initialParameters
     * @return PDOStatement
     */
    public function createStatement($initialSql = null, $initialParameters = null)
    {
        $statement = $this->adapter->createStatement($initialSql, $initialParameters);
        $statement->prepare();
        return $statement->getResource();
    }

    public function getPlatform()
    {
        return $this->adapter->getPlatform();
    }

    public function hasTable(string $name): bool
    {
        $tables = $this->getMetadata()->getTableNames();
        return in_array($name, $tables);
    }

    public function getTable(string $name): ?Table
    {
        if ($this->hasTable($name)) {
            $t = new Table($this, $name);
            $t->setDbAdapter($this->adapter);
            return $t;
        }
        return null;
    }

    /**
     * @return Table[]
     */
    public function getTables(): array
    {
        $data = [];
        foreach ($this->getMetadata()->getTableNames() as $name) {
            $data[] = $this->table($name);
        }
        return $data;
    }

    public function __get(string $name)
    {
        if ($name == "function") {
            return $this->query("SHOW FUNCTION STATUS")->fetchAll();
        }
        if ($name == "procedure") {
            return $this->query("SHOW PROCEDURE STATUS")->fetchAll();
        }
        return $this->$name;
    }

    public function getMetadata()
    {
        return \Laminas\Db\Metadata\Source\Factory::createSourceFromAdapter($this->adapter);
    }

    public function dropTable(string $name)
    {
        $drop = new DropTable($name);
        $sql = new Sql($this->adapter);
        return $this->adapter->query($sql->buildSqlString($drop), Adapter::QUERY_MODE_EXECUTE);
    }

    /**
     * @return PDOStatement 
     */
    public function query(string $statement)
    {
        $statement = $this->adapter->getDriver()->createStatement($statement);
        $statement->prepare();
        $statement->execute();
        $pdo_statement = $statement->getResource();
        return $pdo_statement;
    }

    /**
     * @return PDOStatement 
     */
    public function prepare($statement, $options = null)
    {
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

    public function alterTable(string $name, callable $call)
    {
        $alter = new AlterTable($name);
        $call($alter);

        $sql = new Sql($this->adapter);
        return $this->adapter->query($sql->buildSqlString($alter), Adapter::QUERY_MODE_EXECUTE);
    }


    public function createTable(string $name, callable $call)
    {
        $create = new CreateTable($name);
        $call($create);
        $sql = new Sql($this->adapter);
        return $this->adapter->query($sql->buildSqlString($create), Adapter::QUERY_MODE_EXECUTE);
    }
}
