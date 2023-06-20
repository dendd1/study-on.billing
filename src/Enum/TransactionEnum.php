<?php

namespace App\Enum;

class TransactionEnum
{
    public const PAYMENT = 0;
    public const DEPOSIT = 1;

    public const TYPE_NAMES = [
        self::PAYMENT => 'payment',
        self::DEPOSIT => 'deposit',
    ];

    public const TYPE_CODES = [
        'payment' => self::PAYMENT,
        'deposit' => self::DEPOSIT,
    ];
}