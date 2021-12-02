<?php

namespace R\DB\Event;

class BeforeUpdate
{
    public $target;
    public function __construct($target)
    {
        $this->target = $target;
    }
}
