<?php

namespace App\Data\GeniusPay;

readonly class GeniusPayVerifyResult
{
    public function __construct(
        public string $reference,
        /** Statut brut GeniusPay : pending, processing, completed, failed, cancelled, expired, refunded. */
        public string $status,
        /** Montant en unités entières (XOF). Brut ou net de frais : à confirmer en sandbox. */
        public int $amount,
        public string $currency,
        public ?string $completedAt,
        public ?string $paymentMethod,
        public ?string $provider,
        /** Frais prélevés côté marchand, si fournis. */
        public ?int $fees = null,
        /** Montant net crédité au marchand, si fourni. */
        public ?int $netAmount = null,
        /** sandbox | live, si fourni. */
        public ?string $environment = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /** Échec définitif (par opposition à pending / processing, qui peuvent encore aboutir). */
    public function isDefinitiveFailure(): bool
    {
        return in_array($this->status, ['failed', 'cancelled', 'expired'], true);
    }
}
