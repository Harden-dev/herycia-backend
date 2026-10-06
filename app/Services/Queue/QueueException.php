<?php

namespace App\Services\Queue;

use RuntimeException;

/** Erreur métier de la file d'attente, avec code HTTP et code lisible par le front. */
class QueueException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }
}
