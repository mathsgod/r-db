<?php

namespace  R\DB;

class PDO extends Schema implements PDOInterface
{
    public function __construct(string $dsn, ?string $username, ?string $password, ?array $options = null)
    {

        //mysql:host=localhost;dbname=testdb;charset=utf8mb4
        //parse dsn

        $dsn = explode(':', $dsn);

        $str = explode(";", $dsn[1]);

        $s = [
            "charset" => "utf8mb4"
        ];

        foreach ($str as $value) {
            $values = explode("=", $value);
            $s[$values[0]] = $values[1];
        }


        parent::__construct($s['dbname'], $s['host'], $username, $password, $s['charset'], $s["port"] ?? 3306, $options);
    }
}
