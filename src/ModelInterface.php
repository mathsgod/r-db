<?php

namespace R\DB;

use Symfony\Component\Validator\Validator\ValidatorInterface;

interface ModelInterface
{
    /**
     * Create model from array
     */
    public static function Create(?array $data): static;


    /**
     * Get model by id
     * @param int|string|array $id
     */
    public static function Get($id): ?static;

    /**
     * Load model by id
     */
    public static function Load($id): static;

    public function getValidator(): ValidatorInterface;
    public function setValidator(ValidatorInterface $validator);


    public function save();
    public function delete();
}
