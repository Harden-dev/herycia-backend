<?php

namespace App\Exceptions;

use Exception;

/**
 * La clé d'idempotence ne peut pas être rejouée : paiement déjà effectué, tentative
 * échouée/expirée, ou requête concurrente encore en cours ; ou le salon a déjà trop de
 * paiements en attente. `reason` permet au front de savoir s'il doit réessayer avec la
 * même clé, en générer une nouvelle, ou reprendre un paiement en cours.
 */
class IdempotencyConflictException extends Exception
{
    public const REASON_ALREADY_PAID = 'payment_already_completed';

    public const REASON_KEY_EXPIRED = 'idempotency_key_expired';

    public const REASON_IN_PROGRESS = 'request_in_progress';

    public const REASON_TOO_MANY_PENDING = 'too_many_pending_payments';

    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function alreadyPaid(): self
    {
        return new self('Ce paiement a déjà été effectué.', self::REASON_ALREADY_PAID);
    }

    public static function keyExpired(): self
    {
        return new self(
            'Cette tentative de paiement a échoué ou a expiré. Générez une nouvelle clé d\'idempotence pour réessayer.',
            self::REASON_KEY_EXPIRED,
        );
    }

    public static function inProgress(): self
    {
        return new self(
            'Une requête de paiement avec cette clé est en cours de traitement. Réessayez dans quelques instants.',
            self::REASON_IN_PROGRESS,
        );
    }

    public static function tooManyPending(): self
    {
        return new self(
            'Plusieurs paiements sont déjà en attente pour ce salon. Finalisez-en un ou attendez leur expiration.',
            self::REASON_TOO_MANY_PENDING,
        );
    }
}
