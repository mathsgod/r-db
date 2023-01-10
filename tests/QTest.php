<?php

declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING);

use PHPUnit\Framework\TestCase;

use function R\DB\Q;

final class QTest extends TestCase
{

    public function test_get()
    {
        $count = count(Q(User::class)->get());
        $this->assertEquals(User::Query()->count(), $count);
    }
}
