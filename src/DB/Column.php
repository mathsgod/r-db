<?php

namespace R\DB;

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

	public function table(): Table
	{
		return $this->table;
	}

	public function rename(string $field)
	{
		$sql = "ALTER TABLE `{$this->table}` CHANGE COLUMN `$this->Field` `$field` {$this->Type} {$this->Extra}";
		$this->Field = $field;
		return $this->table->db()->exec($sql);
	}
}
