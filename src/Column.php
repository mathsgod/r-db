<?php

namespace R\DB;

use Laminas\Db\Metadata\Source\Factory;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use Laminas\Hydrator\ObjectPropertyHydrator;

class Column implements ColumnInterface
{
	protected $table;
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

	function getName()
	{
		return $this->Field;
	}

	function isNullable()
	{
		return $this->Null == 'YES';
	}

	function isPrimary()
	{
		return $this->Key == 'PRI';
	}

	function getDefault()
	{
		return $this->Default;
	}

	function getType()
	{
		return $this->Type;
	}

	public function rename(string $field)
	{
		$table_name = $this->table->getTable();

		$sql = "ALTER TABLE `{$table_name}` CHANGE COLUMN `$this->Field` `$field` {$this->Type} {$this->Extra}";

		return $this->table->getAdapter()->query($sql)->execute();
	}

	public function __debugInfo()
	{
		$hydrator = new ObjectPropertyHydrator();
		return $hydrator->extract($this);
	}

	public function getMetadata()
	{
		$meta = Factory::createSourceFromAdapter($this->table->getAdapter());
		return $meta->getColumn($this->Field, $this->table->name);
	}
}
