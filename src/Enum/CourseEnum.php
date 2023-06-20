<?php

namespace App\Enum;

class CourseEnum
{
    public const FREE = 0;
    public const RENT = 1;
    public const BUY = 2;

    public const FREE_NAME = 'free';
    public const RENT_NAME = 'rent';
    public const BUY_NAME = 'buy';

    public const NAMES = [
        self::FREE => self::FREE_NAME,
        self::RENT => self::RENT_NAME,
        self::BUY => self::BUY_NAME,
    ];

    public const VALUES = [
        self::FREE_NAME => self::FREE,
        self::RENT_NAME => self::RENT,
        self::BUY_NAME => self::BUY,
    ];
    public const COURSE_TYPE_NAMES = [
        CourseEnum::FREE => 'Бесплатный',
        CourseEnum::RENT => 'Аренда',
        CourseEnum::BUY => 'Покупка',
    ];
}