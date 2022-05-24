<?php

namespace R\DB\Event;

use R\DB\ModelInterface;

class BeforeDelete
{
    public ModelInterface $target;
    public function __construct(ModelInterface $target)
    {
        $this->target = $target;
    }
}
