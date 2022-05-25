<?php

namespace R\DB\Event;

use R\DB\ModelInterface;

class BeforeInsert
{
    public $target;
    public function __construct(ModelInterface $target)
    {
        $this->target = $target;
    }
}
