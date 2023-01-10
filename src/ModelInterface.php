<?php

namespace R\DB;

use Laminas\Db\Sql\Where;
use Symfony\Component\Validator\Validator\ValidatorInterface;

interface ModelInterface
{
    /**
     * Create model from array
     */
    public static function Create(?array $data): static;


    /**
     * Get single object
     */
    public static function Get(Where|string|int|array $where): ?static;

    /**
     * Load model by id
     */
    public static function Load($id): static;

    public function getValidator(): ValidatorInterface;
    public function setValidator(ValidatorInterface $validator);


    public function save();
    public function delete();

    public function isDirty(string $name = null): bool;
    public function wasChanged(string $name = null): bool;
    public function __isset(string $name);
    public function __fields(): array;
}
