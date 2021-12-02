<?php

namespace R\DB\Event;

class AfterUpdate
{
    public $target;
    public function __construct($target)
    {
        $this->target = $target;
    }
}
