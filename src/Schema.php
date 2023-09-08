<?php

namespace R\DB;

use PDO;
use PDOStatement;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Db\Sql\Ddl\CreateTable;
use Laminas\Db\Sql\Ddl\DropTable;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Db\TableGateway\TableGatewayInterface;
use League\Event\EventDispatcherAware;
use League\Event\EventDispatcherAwareBehavior;
use Psr\Container\ContainerInterface;
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
    private $database;
    private $hostname;
    private $username;
    private $password;
    private $charset;
    private $port;
    private $options;

    protected $container;

    public function __construct(string $database, string $hostname, string $username, string $password = "", string $charset = "utf8mb4", int $port = 3306, ?array $options = null)
    {
        $this->database = $database;
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->charset = $charset;
        $this->port = $port;
        $this->options = $options;

        $driver_options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        if ($options !== null) {
            $driver_options = $driver_options + $options;
        }

        $this->setDbAdapter(new Adapter([
            "database" => $database,
            "hostname" => $hostname,
            "username" => $username,
            "password" => $password,
            "port" => $port,
            "charset" => $charset,
            "driver" => "Pdo_Mysql",
            "driver_options" => $driver_options
        ]));
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    public function backup(string $filename): bool
    {

        $command = "mysqldump -u {$this->username} -p{$this->password} -h {$this->hostname} -P {$this->port} {$this->database} > {$filename}";
        @exec($command, $output, $return_var);
        return $return_var === 0;
    }


    public function restore(string $filename): bool
    {
        $command = "mysql -u {$this->username} -p{$this->password} -h {$this->hostname} -P {$this->port} {$this->database} < {$filename}";
        @exec($command, $output, $return_var);
        return $return_var === 0;
    }

    protected static $Instance;

    static function Create(): Schema
    {
        if (self::$Instance) {
            return self::$Instance;
        }

        //load from .env
        $dotenv = \Dotenv\Dotenv::createImmutable(getcwd());
        $dotenv->load();

        $host = $_ENV["DATABASE_HOSTNAME"];
        $name = $_ENV["DATABASE_DATABASE"];
        $port = $_ENV["DATABASE_PORT"] ?? 3306;
        $username = $_ENV["DATABASE_USERNAME"];
        $password = $_ENV["DATABASE_PASSWORD"];
        $charset = $_ENV["DATABASE_CHARSET"] ?? "utf8mb4";

        if(!$host) throw new \Exception("DATABASE_HOSTNAME not found in .env");
        if(!$name) throw new \Exception("DATABASE_DATABASE not found in .env");
        if(!$username) throw new \Exception("DATABASE_USERNAME not found in .env");
        

        self::$Instance = new Schema($name, $host, $username, $password, $charset, $port);
        return self::$Instance;
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

    /**
     * @param string $statement
     * @return int|false
     */
    function exec(string $statement)
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

    function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    public function table(string $name)
    {
        $table = new Table($this, $name);
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

    public function hasTableColumn(string $table, string $column): bool
    {
        $columns = $this->getMetadata()->getColumnNames($table);
        return in_array($column, $columns);
    }

    public function getTable(string $name): ?Table
    {
        if ($this->hasTable($name)) {
            $t = new Table($this, $name);
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

    public function getTablePrimaryKey(string $table)
    {
        $data = $this->query("DESCRIBE $table")->fetchAll();
        $fields = array_filter($data, function ($item) {
            return $item["Key"] == "PRI";
        });
        return $fields[0]["Field"];
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
        $statement = $this->adapter->createStatement($query, $options);
        $statement->prepare();
        return $statement->getResource();
    }


    public function renameTable(string $old_name, string $new_name)
    {
        return $this->adapter->query("ALTER TABLE $old_name RENAME TO $new_name", Adapter::QUERY_MODE_EXECUTE);
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

    public function getTableGateway($name, $features = null, ?ResultSetInterface $resultSetPrototype = null, ?Sql $sql = null): TableGatewayInterface
    {
        $table = new TableGateway($name, $this->adapter, $features, $resultSetPrototype, $sql);
        return $table;
    }
}
