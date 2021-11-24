<?php

use Symfony\Component\Validator\Constraints as Assert;

class UserGroup extends Model
{
    #[Assert\NotBlank]
    public $name;

    #[Assert\NotBlank]
    public $remark;
}
