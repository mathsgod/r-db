<?php

namespace R\DB;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Db\Metadata\Source\Factory;
use Laminas\Db\Sql\Ddl\AlterTable;
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
		$sql = "ALTER TABLE `{$this->table}` CHANGE COLUMN `$this->Field` `$field` {$this->Type} {$this->Extra}";
		$this->Field = $field;

		return $this->table->getPDO()->exec($sql);
	}

	public function __debugInfo()
	{
		$hydrator = new ObjectPropertyHydrator();
		return $hydrator->extract($this);
	}

	public function getMetadata()
	{
		$meta = Factory::createSourceFromAdapter($this->adapter);
		return $meta->getColumn($this->Field, $this->table->name);
	}
}
