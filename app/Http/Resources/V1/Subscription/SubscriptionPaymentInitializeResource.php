<?php

namespace App\Http\Resources\V1\Subscription;

use App\Data\Paystack\PaystackInitializeResult;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPaymentInitializeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array{transaction: PaymentTransaction, paystack: PaystackInitializeResult} $payload */
        $payload = $this->resource;
        $transaction = $payload['transaction'];
        $paystack = $payload['paystack'];

        return [
            'authorization_url' => $paystack->authorizationUrl,
            'access_code' => $paystack->accessCode,
            'reference' => $paystack->reference,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'plan' => [
                'code' => $transaction->plan?->code,
                'name' => $transaction->plan?->name,
                'price_fcfa' => $transaction->plan?->price_fcfa,
            ],
        ];
    }
}
