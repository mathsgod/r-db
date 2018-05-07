<?
namespace DB;
class Column {

	private $_table;
	public $Field;
	public $Type;
	public $Null;
	public $Key;
	public $Default;
	public $Extra;

	public function __construct($table) {
		$this->_table = $table;
    }
    
    public function table(){
        return $this->_table;
    }

	public function rename($field) {
		$sql = "ALTER TABLE `{$this->_table}` CHANGE COLUMN `$this->Field` `$field` {$this->Type} {$this->Extra}";
		$this->Field = $field;
		return $this->_table->db()->exec($sql);
	}
}
