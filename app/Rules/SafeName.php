<?php

namespace App\Rules;

use App\Support\SmsText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nom affiché ou envoyé par SMS (pentest P1) : jeu de caractères restreint, une seule ligne,
 * ni lien, ni domaine, ni numéro de téléphone.
 * - person() : nom de personne (gérant, coiffeur, client) — lettres, espaces, - ' ’ .
 * - label()  : nom de salon ou de prestation — en plus : chiffres et , & ( ) +
 */
final class SafeName implements ValidationRule
{
    private const PERSON_PATTERN = '/^[\pL\pM][\pL\pM \-\'’.]*$/u';

    private const LABEL_PATTERN = '/^[\pL\pM\pN][\pL\pM\pN \-\'’.,&()+]*$/u';

    private function __construct(
        private readonly string $pattern,
        private readonly string $charsetMessage,
    ) {}

    public static function person(): self
    {
        return new self(
            self::PERSON_PATTERN,
            'Ce nom contient des caractères non autorisés : lettres, espaces, tiret, apostrophe et point uniquement.',
        );
    }

    public static function label(): self
    {
        return new self(
            self::LABEL_PATTERN,
            'Ce nom contient des caractères non autorisés : lettres, chiffres, espaces et - \' . , & ( ) + uniquement.',
        );
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match($this->pattern, $value) !== 1) {
            $fail($this->charsetMessage);

            return;
        }

        if (SmsText::containsLinkOrPhone($value)) {
            $fail('Ce nom ne doit contenir ni lien, ni adresse web, ni numéro de téléphone.');
        }
    }
}
