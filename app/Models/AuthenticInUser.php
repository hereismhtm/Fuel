<?php

namespace App\Models;

use AuthenticIn\UserBaseModel;
use Medoo\Medoo;

class AuthenticInUser extends UserBaseModel
{
    public string $mobile = '';
    public ?string $email = '';
    public ?string $name = '';
    public int $credit = 0;

    public function __construct(?int $id, Medoo $database)
    {
        $this->init(
            $id,
            $database,
            'users',
            [
                'mobile',
                'email',
                'name',
                'credit [Int]',
            ]
        );
    }

    public function guardedDataset(): string
    {
        return $this->mobile
            . $this->email
            . $this->name
            . $this->credit;
    }

    public function setCredit(string $operator, int $value): bool
    {
        $this->credit = $this->applyMathOnInt('credit', $operator, $value);
        return $this->changesNotify();
    }

    public function setEmail(string $value): bool
    {
        $this->email = strtolower($value);
        return $this->changesNotify(['email']);
    }
}
