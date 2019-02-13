<?php

declare (strict_types = 1);
error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;


final class ORMQueryTest extends TestCase
{
    public function testUpdate()
    {
        $table = Testing::_table();
        $table->truncate();
        $table->insert(["name" => 1]);
        $table->insert(["name" => 1]);
        $table->insert(["name" => '3']);

        $query = Testing::Query(["name" => 1]);
        $query->set(["test3" => 3]);
        $query->update();

        $this->assertEquals(Testing::Query(["test3" => 3])->count(), 2);

        $table->truncate();

        $this->assertEquals(Testing::Query()->count(), 0);
    }

    public function testInsert()
    {
        $table = Testing::_table();
        $table->truncate();

        $query = Testing::Query();
        $query->set(["name" => '1']);
        $query->insert();

        $this->assertEquals(Testing::Query()->count(), 1);
    }
}

