<?php

namespace R\DB;

use Laminas\Db\Sql\Where;
use Closure;

interface TableInterface
{

    public function getName(): string;

    /**
     * @return ColumnInterface[]
     */
    public function getColumns();

    public function dropColumn(string $name);
    public function addColumn(\Laminas\Db\Sql\Ddl\Column\ColumnInterface $column);

    public function getPrimaryKeys(): array;
    /**
     * @param Where|Closure|string|array $where
     */
    public function select($where = null);

    public function insert(array $data);

    /**
     * @param Where|Closure|string|array $where
     */
    public function update(array $data,  $where = null);

    /**
     * @param Where|Closure|string|array $where
     */
    public function delete($where);
}
