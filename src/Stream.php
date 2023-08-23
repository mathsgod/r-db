<?php

namespace R\DB;

class Stream
{

    /**
     * @var TableInterface
     */
    private $table;

    /**
     * @var PDOInterface[]
     */
    private static $Stream = [];

    protected $mode;
    protected $class;
    protected $query;
    protected $path;

    public static function Register(PDOInterface $pdo, $protocol, $flags = 0)
    {
        stream_wrapper_register($protocol, __CLASS__, $flags) or die("Failed to register protocol");
        self::$Stream[$protocol] = $pdo;
    }

    public function stream_stat()
    {
        return [];
    }

    function stream_open(string $path, string $mode): bool
    {

        $url = parse_url($path);
        $pdo = self::$Stream[$url["scheme"]];

        $this->query = $url["query"] ?? "";

        $this->class = $url["host"];
        $this->path = $url["path"] ?? "";

        return true;


        switch ($mode) {
            case 'r':
                break;
            case 'wb':
                break;
            case 'rb':
                break;
            default:
                return false;
        }
        return true;
    }



    protected $content;
    protected $offset = 0;

    function stream_read($count)
    {
        if (!$this->content) {

            $query = [];
            parse_str($this->query, $query);

            $q = Q($this->class, $query);

            if ($this->path) {
                $id = substr($this->path, 1);
                $q->where([$q->getPrimaryKey() => $id]);

                $this->content = json_encode($q->get()[0], JSON_UNESCAPED_UNICODE);
            } else {
                $data = $q->get();

                if ($query["meta"]) {
                    $meta = $q->getMeta();
                    $data = [
                        "data" => $data,
                        "meta" => $meta
                    ];
                }
                $this->content = json_encode($data, JSON_UNESCAPED_UNICODE);
            }
        }

        $ret = substr($this->content, $this->offset, $count);
        $this->offset += $count;
        return $ret;
    }

    function stream_write($data)
    {


        $data = json_decode($data, true);
        $strlen = strlen($data);

        if (is_numeric($id = $this->_segment[0])) {
            //update
            $key = $this->table->getPrimaryKeys()[0];
            $ret = $this->table->update($data, [$key => $id]);
        } else {
            //insert

            if (count($data) == count($data, COUNT_RECURSIVE)) {
                //sigle insert
                $this->table->insert($data);
            } else {
                foreach ($data as $records) {
                    $this->table->insert($records);
                }
            }
        }
        return $strlen;
    }

    function stream_tell()
    {
        return $this->offset;
    }

    function stream_eof()
    {
        return $this->offset >= strlen($this->content);
    }

    function stream_seek($offset, $step)
    {
        //No need to be implemented
    }
}
