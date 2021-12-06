<?php

namespace R\DB;

use Laminas\Db\Sql\Where;

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
    public function select(Where|\Closure|string|array $where = null);

    public function insert(array $data);
    public function update(array $data, Where|\Closure|string|array $where = null);
    public function delete(Where|\Closure|string|array $where);
}
