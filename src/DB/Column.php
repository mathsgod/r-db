<?
namespace R\DB;

use Exception;

class Column
{
	private $table;
	public $Field;
	public $Type;
	public $Null;
	public $Key;
	public $Default;
	public $Extra;

	public function __construct(Table $table)
	{
		$this->table = $table;
	}

	public function table()
	{
		return $this->table;
	}

	public function rename($field)
	{
		$sql = "ALTER TABLE `{$this->table}` CHANGE COLUMN `$this->Field` `$field` {$this->Type} {$this->Extra}";
		$this->Field = $field;
		$db = $this->table->db();
		return $this->table->db()->exec($sql);
	}
}
