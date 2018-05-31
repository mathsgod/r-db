<?php

namespace R;

class DataList extends \ArrayIterator implements \JsonSerializable
{
    public function asArray()
    {
        return $this->getArrayCopy();
    }

    public function jsonSerialize()
    {
        return $this->getArrayCopy();
    }
    // -- RList
    public function first()
    {
        return $this[0];
    }

    public function top($num)
    {
        return new DataList(array_slice($this->getArrayCopy(), 0, $num));
    }

    public function sum($f)
    {
        return array_sum($this->map($f)->getArrayCopy());
    }

    public function shuffle()
    {
        $data = $this->getArrayCopy();
        shuffle($data);
        return new DataList($data);
    }

    public function reverse()
    {
        return new DataList(array_reverse($this->getArrayCopy()));
    }

    public function slice($offset, $length = null)
    {
        return new DataList(array_slice($this->getArrayCopy(), $offset, $length));
    }

    public function map($callback)
    {
        return new DataList(array_map($callback, $this->getArrayCopy()));
    }

    public function page($page, $page_size)
    {
        return $this->slice(($page - 1) * $page_size, $page_size);
    }

    public function usort($callback)
    {
        $data = $this->getArrayCopy();
        usort($data, $callback);
        return new DataList($data);
    }

    public function filter($callback)
    {
        return new DataList(array_values(array_filter($this->getArrayCopy(), $callback)));
    }

    public function single()
    {
        $first = $this->first();
        return array_shift(array_slice($first, 0, 1));
    }

    public function implode($glue)
    {
        return implode($glue, $this->getArrayCopy());
    }

    public function diff($array)
    {
        return new DataList(array_diff($this->getArrayCopy(), (array )$array));
    }

    public function udiff($array, $callback)
    {
        return new DataList(array_udiff($this->getArrayCopy(), (array )$array, $callback));
    }

    public function substract($array, $callback)
    {
        $data = $this->filter(function ($o) use ($array, $callback) {
            foreach ($array as $a) {
                if ($callback($o, $a)) {
                    return false;
                }
            }
            return true;
        });
        return $data;
    }

    public function pop()
    {
        $last = $this->count() - 1;
        $ret = $this->offsetGet($last);
        $this->offsetUnset($last);
        return $ret;
    }

}
