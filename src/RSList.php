<?php
namespace R;

class RSList extends DataList
{
    public $class = null;
    public $rs = null;

    public function __construct($rs = null, $class = null)
    {
        $this->rs = $rs;
        
        if(!$rs){
            parent::__construct([]);
            return;
        }

        if ($class && $rs) {
            $this->class = $class;

            $rs->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $class, []);
        }

        parent::__construct(iterator_to_array($rs));

        if ($class) {
            $attr = $class::__attribute();
            $json_fields = array_filter($attr, function ($o) {
                return $o["Type"] == "json";
            });

            $json_fields = array_map(function ($o) {
                return $o["Field"];
            }, $json_fields);


            foreach ($this as $obj) {
                foreach ($json_fields as $field) {
                    $obj->$field = json_decode($obj->$field, true);
                }
            }
        }
    }
}