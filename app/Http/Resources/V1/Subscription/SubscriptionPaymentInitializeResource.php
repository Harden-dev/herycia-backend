<?php

namespace App\Http\Resources\V1\Subscription;

use App\Data\GeniusPay\GeniusPayPaymentResult;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPaymentInitializeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array{transaction: PaymentTransaction, payment: GeniusPayPaymentResult, replayed: bool} $payload */
        $payload = $this->resource;
        $transaction = $payload['transaction'];
        $payment = $payload['payment'];

        return [
            // authorization_url conservé pour compatibilité avec le front (ex-Paystack).
            'authorization_url' => $payment->checkoutUrl,
            'checkout_url' => $payment->checkoutUrl,
            'reference' => $transaction->reference,
            'gateway_reference' => $payment->reference,
            'expires_at' => $payment->expiresAt,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'plan' => [
                'code' => $transaction->plan?->code,
                'name' => $transaction->plan?->name,
                'price_fcfa' => $transaction->plan?->price_fcfa,
            ],
            // true si la réponse provient d'un rejeu de la même clé d'idempotence (aucun nouveau paiement créé).
            'idempotent_replay' => $payload['replayed'],
        ];
    }
}
