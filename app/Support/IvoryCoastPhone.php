<?php

namespace App\Support;

class IvoryCoastPhone
{
    /**
     * Canonical storage format: 225 + 10 digits (e.g. 2250748754918).
     * Plan de numérotation CI : mobiles 01 / 05 / 07, fixes 21 / 25 / 27.
     */
    public const PATTERN = '/^225(?:01|05|07|21|25|27)[0-9]{8}$/';

    /**
     * Forme canonique, sans jamais tronquer : un numéro avec des chiffres en trop reste
     * invalide (pentest P2) au lieu d'être confondu avec un autre numéro.
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', trim($phone)) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '225') && strlen($digits) === 13) {
            return $digits;
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
