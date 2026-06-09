<?php

namespace App\Services\Booking;

use RuntimeException;

class PublicBookingException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 403,
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
