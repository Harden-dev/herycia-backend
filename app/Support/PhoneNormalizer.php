<?php

namespace App\Support;

/**
 * @deprecated Use IvoryCoastPhone directly. Kept for backward compatibility.
 */
class PhoneNormalizer
{
    public static function toE164(string $phone): string
    {
        return IvoryCoastPhone::normalize($phone);
    }

    public static function isValidE164(string $phone): bool
    {
        return IvoryCoastPhone::isValid($phone);
    }

    /** @return list<string> */
    public static function lookupVariants(string $phone): array
    {
        $normalized = IvoryCoastPhone::normalize($phone);

        return $normalized !== '' ? [$normalized] : [];
    }
}
