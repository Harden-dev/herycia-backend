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
        /** Statut brut Paystack : success, failed, abandoned, ongoing, pending, reversed… */
        public string $status = '',
    ) {}

    /** Échec définitif (par opposition à abandonné / en cours, qui peuvent encore aboutir). */
    public function isDefinitiveFailure(): bool
    {
        return in_array($this->status, ['failed', 'reversed'], true);
    }
}
