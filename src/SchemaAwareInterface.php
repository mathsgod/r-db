<?php

namespace R\DB;

interface SchemaAwareInterface
{
    public static function GetSchema(): Schema;
}
