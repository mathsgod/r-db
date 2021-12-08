<?php

namespace R\DB;

use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;

class Stream
{
    /**
     * @var PDOInterface
     */
    private $pdo;

    /**
     * @var TableInterface
     */
    private $table;

    private $path;

    private $query = [];
    private $schema;

    private static $_pdos = [];
    private $eof = false;
    private $segment;

    public static function Register(PDOInterface $pdo, $protocol, $flags = 0)
    {
        stream_wrapper_register($protocol, __CLASS__, $flags) or die("Failed to register protocol");
        self::$_pdos[$protocol] = $pdo;
    }

    public function stream_stat()
    {
        return [];
    }

    function stream_open(string $path, string $mode): bool
    {
        $url = parse_url($path);

        $this->pdo = self::$_pdos[$url["scheme"]];
        parse_str($url["query"] ?? "", $this->query);
        $this->query = $this->query ?? [];
        $this->scheme = $url["scheme"];
        $this->table = new Table($this->pdo, $url["host"]);
        $this->path = $url["path"] ?? "";
        $this->segment = explode("/", substr($this->path, 1));


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


    public function filterParser(string $filter): Where
    {
        $filter = str_replace(
            [" eq ", " gt ", " lt ", " ge ", " le", " ne "],
            [" = ", " > ", " < ", " >= ", " <= ", " !="],
            $filter
        );

        $where = new Where();
        $where->expression($filter, []);
        return $where;
    }


    function stream_read()
    {
        if (!$this->eof) {
            $this->eof = true;

            if (is_numeric($id = $this->segment[0])) {
                $key = $this->table->getPrimaryKeys()[0];
                $ret = $this->table->select([$key => $id]);
                return json_encode(iterator_to_array($ret)[0], JSON_UNESCAPED_UNICODE);
            } else {

                $f = function (Select $select) {
                    if (isset($this->query['$filter']) && $this->query['$filter']) {
                        $select->where($this->filterParser($this->query['$filter']));
                    }

                    if (isset($this->query['$orderBy'])) {
                        $select->order($this->query['$orderBy']);
                    }

                    if (isset($this->query['$skip'])) {
                        $select->offset($this->query['$skip']);
                    }

                    if (isset($this->query['$top'])) {
                        $select->limit($this->query['$top']);
                    }
                };

                $f->bindTo($this);

                return json_encode(iterator_to_array($this->table->select($f)), JSON_UNESCAPED_UNICODE);
            }
        }
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
    }

    function stream_eof()
    {
        return $this->eof;
    }

    function stream_seek($offset, $step)
    {
        //No need to be implemented
    }
}
