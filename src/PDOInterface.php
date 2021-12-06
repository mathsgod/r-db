<?php

namespace R\DB;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\TableGatewayInterface;
use PDOStatement;

interface PDOInterface
{
    function beginTransaction(): bool;
    function commit(): bool;
    function rollBack(): bool;
    function inTransaction(): bool;
    function exec(string $statement): int|false;

    /**
     * @return PDOStatement|false
     */
    function prepare(string $query, array $options = []);


    /**
     * @return PDOStatement|false
     */
    function query(string $query);

    function getTableGateway(
        $name,
        $features = null,
        ?ResultSetInterface $resultSetPrototype = null,
        ?Sql $sql = null
    ): TableGatewayInterface;

    function getAdapter(): AdapterInterface;
}
