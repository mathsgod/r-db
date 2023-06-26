<?php

namespace R\DB;

use Laminas\Db\Sql\Where;
use Symfony\Component\Validator\Validator\ValidatorInterface;

interface ModelInterface
{
    /**
     * Create model from array
     * @return static
     */
    public static function Create(?array $data);


    /**
     * Get single object
     * @return ?static
     * @param Where|string|int|array $where
     */
    public static function Get($where);

    /**
     * Load model by id
     * @return static
     */
    public static function Load($id);

    public function getValidator(): ValidatorInterface;
    public function setValidator(ValidatorInterface $validator);


    public function save();
    public function delete();

    public function isDirty(string $name = null): bool;
    public function wasChanged(string $name = null): bool;
    public function __isset(string $name);
    public function __fields(): array;
}
