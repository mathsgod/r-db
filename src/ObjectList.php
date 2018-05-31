<?php
namespace R;

class ObjectList extends DataList
{
    public $class = null;
    public $rs = null;

    public function __construct($rs = null, $class = null)
    {
        $this->rs = $rs;

        $ds = [];
        if ($class) {
            $this->class = $class;
            $rc = new \ReflectionClass($class);
            foreach ($rs as $r) {
                $obj = $rc->newInstanceWithoutConstructor();
                foreach ($r as $k => $v) {
                    $obj->$k = $v;
                }
                $ds[] = $obj;
            }
        }

        parent::__construct($ds);
    }
}