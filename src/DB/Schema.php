<?php

namespace R\DB;

class Schema
{
    protected $db;
    public $name;
    public $logger;

    public function __construct(PDO $db, $name, $logger)
    {
        $this->db = $db;
        $this->name = $name;
        $this->logger = $logger;
    }

    public function tables()
    {
        foreach ($this->db->query("SHOW TABLES") as $table) {
            $tables[] = new Table($this->db, array_values($table)[0], $this->logger);
        }
        return $tables;
    }
}
