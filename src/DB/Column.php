<?php

namespace R\DB;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Adapter\AdapterAwareTrait;
use Laminas\Db\Metadata\Source\Factory;
use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Hydrator\ObjectPropertyHydrator;

class Column implements AdapterAwareInterface
{
	use AdapterAwareTrait;
	protected $table;
	public $Field;
	public $Type;
	public $Null;
	public $Key;
	public $Default;
	public $Extra;

	public function __construct(Table $table, Adapter $adapter)
	{
		$this->table = $table;
		$this->setDbAdapter($adapter);
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
