<?php
namespace R\DB;

class Stream
{
    private $_pdo;
    private $_ps;
    private $_table;
    private $_path;

    private static $_pdos=[];

    public static function Register($pdo, $protocol, $flags = 0)
    {
        stream_wrapper_register($protocol, __CLASS__, $flags) or die("Failed to register protocol");
        self::$_pdos[$protocol]=$pdo;
    }

    function stream_open($path, $mode, $options, &$opath)
    {
        $url = parse_url($path);
        
        $this->_pdo=self::$_pdos[$url["scheme"]];
        parse_str($url["query"], $this->query);
        $this->_scheme=$url["scheme"];
        $this->_table=new Table($this->_pdo, $url["host"]);
        $this->_path=$url["path"];
        $this->_segment=explode("/", substr($this->_path, 1));

        
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

    function stream_read()
    {
        if (!$this->_eof) {
            $this->_eof=true;

            if (is_numeric($id = $this->_segment[0])) {
                $key=$this->_table->keys()[0];
                $ret=$this->_table->where("$key=?",[$id])->select();
                return json_encode($ret[0], JSON_UNESCAPED_UNICODE);
            } else {
                if ($this->query["filter"]) {
                    foreach ($this->query["filter"] as $k => $v) {
                        if(is_array($v)){
                            foreach($v as $a=>$b){
                                if($a=="lt"){
                                    $this->_table->where("$k>?", [$b]);
                                }
                            }
                        }else{
                            $this->_table->where("$k=?", [$v]);
                        }
                        
                    }
                }

                return json_encode($this->_table->select());
            }
        }
    }

    function stream_write($data)
    {
        $data=json_decode($data, true);
        $strlen=strlen($data);

        if (is_numeric($id = $this->_segment[0])) {
            //update
            $key=$this->_table->keys()[0];
            $ret=$this->_table->where("$key=$id")->update($data);
        } else {
            //insert

            if (count($data) == count($data, COUNT_RECURSIVE)) {
                //sigle insert
                $this->_table->insert($data);
            } else {
                foreach ($data as $records) {
                    $this->_table->insert($records);
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
        return $this->_eof;
    }

    function stream_seek($offset, $step)
    {
        //No need to be implemented
    }
}
