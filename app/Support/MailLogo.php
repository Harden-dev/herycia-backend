<?php

namespace App\Support;

/**
 * URL absolue du logo affiché dans les e-mails (évite embed(), qui l'envoie en pièce jointe).
 * MAIL_LOGO_URL si défini, sinon {APP_URL}/logo.png.
 */
class MailLogo
{
    public static function url(): string
    {
        $configured = config('mail.logo_url');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/logo.png';
    }
}
