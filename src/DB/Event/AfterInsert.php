<?php

namespace R\DB\Event;

class AfterInsert
{
    public $target;
    public function __construct($target)
    {
        $this->target = $target;
    }
}
