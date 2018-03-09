<?php

namespace DB;

class Schema
{
    protected $db;
    public $name;

    public function __construct($db, $name)
    {
        $this->db = $db;
        $this->name = $name;
    }

    public function tables()
    {
        foreach ($this->db->query("SHOW TABLES") as $table) {
            $tables[] = new Table($this->db, array_values($table)[0]);
        }
        return $tables;
    }
}
