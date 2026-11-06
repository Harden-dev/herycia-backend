<?php

namespace App\Support;

/**
 * Masquage des données personnelles avant journalisation (pentest P5) :
 * assez pour recouper un incident, pas assez pour réidentifier la personne.
 */
class Pii
{
    private const MASK = '*****';

    private const CI_COUNTRY_CODE = '225';

    private const PHONE_VISIBLE_SUFFIX = 4;

    private const PHONE_MIN_MASKABLE_LENGTH = 8;

    /** Suite de chiffres assez longue pour être un numéro (8 chiffres ou plus, séparateurs admis). */
    private const PHONE_IN_TEXT_PATTERN = '/\+?\d(?:[\s.\-]?\d){7,}/';

    private const EMAIL_IN_TEXT_PATTERN = '/[^\s@<>"\']+@[^\s@<>"\']+/';

    /** +2250707070707 → +225*****0707 */
    public static function maskPhone(?string $phone): string
    {
        $phone = (string) $phone;
        $plus = str_starts_with($phone, '+') ? '+' : '';
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < self::PHONE_MIN_MASKABLE_LENGTH) {
            return $digits === '' ? '' : self::MASK;
        }

        // Seul l'indicatif pays reste visible au début.
        $prefix = str_starts_with($digits, self::CI_COUNTRY_CODE) ? self::CI_COUNTRY_CODE : '';

        return $plus.$prefix.self::MASK.substr($digits, -self::PHONE_VISIBLE_SUFFIX);
    }

    /** kouame@example.com → k***@example.com */
    public static function maskEmail(?string $email): string
    {
        $email = (string) $email;
        $at = strrpos($email, '@');

        if ($at === false || $at === 0) {
            return $email === '' ? '' : self::MASK;
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }

    /** Identifiant de connexion (email ou téléphone). */
    public static function maskLogin(?string $login): string
    {
        return str_contains((string) $login, '@') ? self::maskEmail($login) : self::maskPhone($login);
    }

    /** Texte libre (message d'erreur tiers) : masque numéros et emails qu'il contient. */
    public static function redact(string $text): string
    {
        $text = preg_replace_callback(self::EMAIL_IN_TEXT_PATTERN, fn (array $m): string => self::maskEmail($m[0]), $text) ?? '';

        return preg_replace_callback(self::PHONE_IN_TEXT_PATTERN, fn (array $m): string => self::maskPhone($m[0]), $text) ?? '';
    }
}
