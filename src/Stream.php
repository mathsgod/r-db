<?php

namespace R\DB;

class ParensParser
{
    // something to keep track of parens nesting
    protected $stack = null;
    // current level
    protected $current = null;

    // input string to parse
    protected $string = null;
    // current character offset in string
    protected $position = null;
    // start of text-buffer
    protected $buffer_start = null;

    public function parse($string)
    {
        if (!$string) {
            // no string, no data
            return array();
        }

        if ($string[0] == '(') {
            // killer outer parens, as they're unnecessary
            $string = substr($string, 1, -1);
        }

        $this->current = array();
        $this->stack = array();

        $this->string = $string;
        $this->length = strlen($this->string);
        // look at each character
        for ($this->position = 0; $this->position < $this->length; $this->position++) {
            switch ($this->string[$this->position]) {
                case '(':
                    $this->push();
                    // push current scope to the stack an begin a new scope
                    array_push($this->stack, $this->current);
                    $this->current = array();
                    break;

                case ')':
                    $this->push();
                    // save current scope
                    $t = $this->current;
                    // get the last scope from stack
                    $this->current = array_pop($this->stack);
                    // add just saved scope to current scope
                    $this->current[] = $t;
                    break;
                    /* 
                case ' ':
                    // make each word its own token
                    $this->push();
                    break;
                */
                default:
                    // remember the offset to do a string capture later
                    // could've also done $buffer .= $string[$position]
                    // but that would just be wasting resources…
                    if ($this->buffer_start === null) {
                        $this->buffer_start = $this->position;
                    }
            }
        }

        return $this->current;
    }

    protected function push()
    {
        if ($this->buffer_start !== null) {
            // extract string from buffer start to current position
            $buffer = substr($this->string, $this->buffer_start, $this->position - $this->buffer_start);
            // clean buffer
            $this->buffer_start = null;
            // throw token into current scope
            $this->current[] = $buffer;
        }
    }
}
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


    public function filterParser(string $filter)
    {
        $parser = new ParensParser();
        $result = $parser->parse($filter);
        return $result;
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

                if (isset($this->query['$filter']) && $this->query['$filter']) {

                    foreach ($this->query["filter"] as $k => $v) {
                        if (is_array($v)) {
                            foreach ($v as $a => $b) {
                                if ($a == "lt") {
                                    $this->_table->where("$k>?", [$b]);
                                }
                            }
                        } else {
                            $this->table->where("$k=?", [$v]);
                        }
                    }
                }


                return json_encode(iterator_to_array($this->table->select()));
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
