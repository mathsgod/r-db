<?php

namespace R\DB;


interface TableInterface
{

    public function getName(): string;

    /**
     * @return ColumnInterface[]
     */
    public function getColumns();

    public function dropColumn(string $name);

    public function addColumn(\Laminas\Db\Sql\Ddl\Column\ColumnInterface $column);
}
