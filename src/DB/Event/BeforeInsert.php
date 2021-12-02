<?php

namespace R\DB\Event;

class BeforeInsert
{
    public $target;
    public function __construct($target)
    {
        $this->target = $target;
    }
}
