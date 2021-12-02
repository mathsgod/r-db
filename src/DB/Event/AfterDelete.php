<?php

namespace R\DB\Event;

class AfterDelete
{
    public $target;
    public function __construct($target)
    {
        $this->target = $target;
    }
}
