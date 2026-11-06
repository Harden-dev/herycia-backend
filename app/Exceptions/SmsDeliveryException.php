<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Échec d'envoi SMS.
 *
 * retryable = true uniquement quand on sait que le SMS n'est PAS parti (Twilio a refusé
 * temporairement : 429 / 5xx, ou serveur injoignable). Dans le doute (délai dépassé après
 * envoi de la requête, réponse illisible), retryable = false : mieux vaut un SMS manqué
 * qu'un SMS reçu deux fois.
 */
class SmsDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
