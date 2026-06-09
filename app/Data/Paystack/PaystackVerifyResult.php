<?php

namespace App\Data\Paystack;

readonly class PaystackVerifyResult
{
    public function __construct(
        public bool $success,
        public string $reference,
        public int $amount,
        public string $currency,
        public ?string $paidAt,
        public ?string $channel,
    ) {}
}
