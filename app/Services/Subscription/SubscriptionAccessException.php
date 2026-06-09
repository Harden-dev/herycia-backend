<?php

namespace App\Services\Subscription;

use RuntimeException;

class SubscriptionAccessException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $reasonCode = 'subscription_inactive',
    ) {
        parent::__construct($message);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
