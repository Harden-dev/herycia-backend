<?php

namespace App\Support;

class IvoryCoastPhone
{
    /** Canonical storage format: 225 + 10 digits (e.g. 2250748754918) */
    public const PATTERN = '/^225[0-9]{10}$/';

    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', trim($phone)) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '225') && strlen($digits) >= 13) {
            return substr($digits, 0, 13);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '225'.$digits;
        }

        if (strlen($digits) === 9) {
            return '2250'.$digits;
        }

        if (strlen($digits) === 10 && ! str_starts_with($digits, '0')) {
            return '2250'.$digits;
        }

        return $digits;
    }

    public static function isValid(string $phone): bool
    {
        return (bool) preg_match(self::PATTERN, self::normalize($phone));
    }
}
