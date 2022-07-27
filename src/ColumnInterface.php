<?php

namespace R\DB;
interface ColumnInterface
{

    /**
     * @return string
     */
    public function getName();

    /**
     * @return bool
     */
    public function isNullable();

    /**
     * @return null|string|int
     */
    public function getDefault();

    public function getType();

    /**
     * @return bool
     */
    public function isPrimary();
}
