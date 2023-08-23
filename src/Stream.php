<?php

namespace R\DB;

use Laminas\Db\Sql\Ddl\Column;
use Laminas\Db\Sql\Ddl\CreateTable;

class Stream
{

    /**
     * @var TableInterface
     */
    private $table;

    /**
     * @var Schema[]
     */
    private static $Schema = [];

    protected $mode;
    protected $class;
    protected $query;
    protected $path;

    public static function Register(Schema $schema, $protocol, $flags = 0)
    {
        stream_wrapper_register($protocol, __CLASS__, $flags) or die("Failed to register protocol");
        self::$Schema[$protocol] = $schema;
    }

    public function stream_stat()
    {
        return [];
    }

    function stream_open(string $path, string $mode): bool
    {

        $url = parse_url($path);

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
        $strlen = strlen($data);
        $data = json_decode($data, true);


        $path = substr($this->path, 1);

        if (is_numeric($path)) {
            $q = Q($this->class);
            $data[$q->getPrimaryKey()] = $path;
            $q->update($data);
        } else {
            //insert
            Q($this->class)->insert($data);
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

    function unlink($path)
    {
        if ($this->url_stat($path, 0) === false) {
            return false;
        }

        $url = parse_url($path);
        $this->query = $url["query"] ?? "";
        $this->class = $url["host"];
        $this->path = $url["path"] ?? "";

        $query = [];
        parse_str($this->query, $query);
        $q = Q($this->class, $query);


        $id = substr($this->path, 1);
        if ($id) {
            $q->where([$q->getPrimaryKey() => $id]);
        }

        return $q->delete();
    }

    public function rmdir($path)
    {
        $url = parse_url($path);
        $table = $url["host"];

        $schema = self::$Schema[$url["scheme"]];
        $schema->dropTable($table);
        return true;
    }

    public function mkdir($path)
    {
        //create table
        $url = parse_url($path);
        $table = $url["host"];

        $query = [];
        parse_str($url["query"], $query);

        $schema = self::$Schema[$url["scheme"]];

        $columns = $query["columns"];
        $schema->createTable($table, function (CreateTable $table) use ($columns) {

            foreach ($columns as $name => $column) {

                switch ($column["type"]) {

                    case "int":
                        $table->addColumn(new Column\Integer($name));
                        break;

                    case "varchar":
                        $table->addColumn(new Column\Varchar($name, $column["length"]));
                        break;
                }
            }
        });

        return true;
    }

    public function rename(string $from, string $to)
    {
        $url = parse_url($from);
        $table = $url["host"];

        $url = parse_url($to);
        $newTable = $url["host"];

        $schema = self::$Schema[$url["scheme"]];
        $schema->renameTable($table, $newTable);
        return true;
    }


    public function url_stat(string $path, int $flags)
    {
        $url = parse_url($path);

        $schema = self::$Schema[$url["scheme"]];
        $table = $url["host"];


        if ($schema->hasTable($table)) {
            return [
                "dev" => 0,
                "ino" => 0,
                "mode" => 0,
                "nlink" => 0,
                "uid" => 0,
                "gid" => 0,
                "rdev" => 0,
                "size" => 0,
                "atime" => 0,
                "mtime" => 0,
                "ctime" => 0,
                "blksize" => 0,
                "blocks" => 0
            ];
        }

        return false;
    }
}
