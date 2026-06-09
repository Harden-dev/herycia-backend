<?php

namespace App\Data\Paystack;

readonly class PaystackInitializeResult
{
    public function __construct(
        public string $authorizationUrl,
        public string $accessCode,
        public string $reference,
    ) {}
}
