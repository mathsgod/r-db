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
use League\Event\EventDispatcherAware;
use League\Event\EventDispatcherAwareBehavior;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Schema implements AdapterAwareInterface, EventDispatcherAware, PDOInterface
{

    use AdapterAwareTrait;
    use EventDispatcherAwareBehavior;


    /**
     * @var ValidatorInterface|null
     */
    private $validator;


    private $in_transaction = false;

    public function __construct(string $database, string $hostname, string $username, string $password = "", string $charset = "utf8mb4", int $port = 3306)
    {
        $this->adapter = new Adapter([
            "database" => $database,
            "hostname" => $hostname,
            "username" => $username,
            "password" => $password,
            "port" => $port,
            "charset" => $charset,
            "driver" => "Pdo_Mysql",
            "driver_options" => [
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ]);
    }

    function beginTransaction(): bool
    {
        $this->in_transaction = true;
        $this->adapter->getDriver()->getConnection()->beginTransaction();
        return true;
    }

    function commit(): bool
    {
        $this->in_transaction = false;
        $this->adapter->getDriver()->getConnection()->commit();
        return true;
    }

    function rollback(): bool
    {
        $this->in_transaction = false;
        $this->adapter->getDriver()->getConnection()->rollBack();
        return true;
    }

    function inTransaction(): bool
    {
        return $this->in_transaction;
    }

    function exec(string $statement): int|false
    {
        $statement = $this->adapter->createStatement($statement);
        $result = $statement->execute();
        return $result->getAffectedRows();
    }

    function setDefaultValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    function getDefaultValidator()
    {
        return $this->validator;
    }

    function getValidator(): ValidatorInterface
    {
        if (!$this->validator) {
            $this->validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        }
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
    public function query(string $query)
    {
        $statement = $this->adapter->getDriver()->createStatement($query);
        $statement->prepare();
        $statement->execute();
        $pdo_statement = $statement->getResource();
        return $pdo_statement;
    }

    /**
     * @return PDOStatement
     */
    public function prepare(string $query, array $options = [])
    {
        $statement = $this->adapter->createStatement($query);
        $statement->prepare();
        return $statement->getResource();
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
