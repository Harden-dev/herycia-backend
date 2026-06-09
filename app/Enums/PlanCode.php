<?php

namespace App\Enums;

enum PlanCode: string
{
    case Basic = 'basic';
    case Pro = 'pro';

    /** @return list<string> */
    public static function registerableValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
