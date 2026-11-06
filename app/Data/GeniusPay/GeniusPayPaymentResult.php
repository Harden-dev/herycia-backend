<?php

namespace App\Data\GeniusPay;

readonly class GeniusPayPaymentResult
{
    public function __construct(
        /** Page de paiement hébergée GeniusPay vers laquelle rediriger le client. */
        public string $checkoutUrl,
        /** Référence générée par GeniusPay (MTX-…), utilisée pour la vérification. */
        public string $reference,
        public ?string $expiresAt = null,
    ) {}
}
