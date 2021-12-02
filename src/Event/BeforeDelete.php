<?php

namespace R\DB\Event;

class BeforeDelete
{
    public $target;
    public function __construct($target)
    {
        $this->target = $target;
    }
}
