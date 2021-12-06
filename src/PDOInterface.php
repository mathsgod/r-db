<?php

namespace R\DB;

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
}
