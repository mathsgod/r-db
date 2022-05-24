<?php

namespace R\DB\Event;

use R\DB\ModelInterface;

class AfterUpdate
{
    public ModelInterface $target;
    public $source;
    public function __construct(ModelInterface $target, $source)
    {
        $this->target = $target;
        $this->source = $source;
    }
}
