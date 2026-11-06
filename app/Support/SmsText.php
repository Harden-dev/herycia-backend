<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Texte saisi par un tiers (client, salon, gérant) inséré dans un SMS envoyé avec l'expéditeur
 * de la plateforme (pentest P1) : rien de cliquable ni d'appelable, une seule ligne, longueur bornée.
 * À appliquer à TOUT texte utilisateur inséré dans un SMS, même déjà validé à la saisie
 * (données antérieures à la validation, modification directe en base).
 */
class SmsText
{
    public const DEFAULT_MAX_LENGTH = 40;

    /** Lien explicite : schéma ou « www. ». */
    private const URL_PATTERN = '#(?:\b[a-z][a-z0-9+.\-]*://|\bwww\s*[.\x{3002}\x{FF0E}])\S*#iu';

    /** Numéro de téléphone : 8 chiffres ou plus, séparés ou non par espaces, points ou tirets. */
    public const PHONE_PATTERN = '/\+?\d(?:[\s.\-]*\d){7,}/u';

    /**
     * Domaine « mot.tld », « mot .tld » ou « mot . tld » (TLD de 2 lettres ou plus).
     * « St. Pierre » (espace seulement après le point) n'est pas un domaine et reste intact.
     */
    public const DOMAIN_PATTERN = '/[\pL\pN_\-]+(?:[.\x{3002}\x{FF0E}]|\s+[.\x{3002}\x{FF0E}]\s*)\pL{2,}/u';

    /** Caractères conservés : lettres, accents, chiffres, espace et ponctuation de nom. */
    private const DISALLOWED_CHARS_PATTERN = '/[^\pL\pM\pN \-\'’.,&()+]+/u';

    private const MAX_PASSES = 3;

    public static function clean(?string $text, int $maxLength = self::DEFAULT_MAX_LENGTH): string
    {
        // Contrôles, caractères invisibles, inversions bidi, sauts et séparateurs de ligne → espace.
        $clean = preg_replace('/[\p{C}\p{Z}]+/u', ' ', (string) $text) ?? '';
        $clean = preg_replace(self::URL_PATTERN, ' ', $clean) ?? '';
        // Emojis, symboles, « / : @ » : retirés avant la détection pour ne pas masquer un domaine.
        $clean = preg_replace(self::DISALLOWED_CHARS_PATTERN, ' ', $clean) ?? '';
        $clean = preg_replace(self::PHONE_PATTERN, ' ', $clean) ?? '';

        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $next = preg_replace(self::DOMAIN_PATTERN, ' ', $clean) ?? '';

            if ($next === $clean) {
                break;
            }

            $clean = $next;
        }

        $clean = trim(preg_replace('/ {2,}/', ' ', $clean) ?? '');

        return trim(Str::limit($clean, $maxLength, ''));
    }

    /** Vrai si le texte contient un lien, un domaine ou un numéro de téléphone (refus à la saisie). */
    public static function containsLinkOrPhone(string $text): bool
    {
        return preg_match(self::URL_PATTERN, $text) === 1
            || preg_match(self::PHONE_PATTERN, $text) === 1
            || preg_match(self::DOMAIN_PATTERN, $text) === 1;
    }
}
