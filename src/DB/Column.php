<?
namespace R\DB;

use Exception;

class Column
{

	private $_table;
	public $Field;
	public $Type;
	public $Null;
	public $Key;
	public $Default;
	public $Extra;

	public function __construct($table)
	{
		$this->_table = $table;
	}

	public function table()
	{
		return $this->_table;
	}

	public function rename($field)
	{
		$sql = "ALTER TABLE `{$this->_table}` CHANGE COLUMN `$this->Field` `$field` {$this->Type} {$this->Extra}";
		$this->Field = $field;
		$db = $this->_table->db();
		$ret = $this->_table->db()->exec($sql);
		if ($ret === false) {
			$error = $db->errorInfo();
			throw new Exception($error[2], $error[1]);
		}
		return $ret;
	}
}
