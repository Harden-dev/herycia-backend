<?php

namespace App\Services\Paystack;

use App\Data\Paystack\PaystackInitializeResult;
use App\Data\Paystack\PaystackVerifyResult;
use App\Exceptions\PaystackException;
use App\Support\Paystack\PaystackAmount;
use Illuminate\Support\Facades\Http;

class PaystackService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function initialize(
        string $email,
        int $amount,
        string $reference,
        string $callbackUrl,
        array $metadata = [],
    ): PaystackInitializeResult {
        $response = Http::withToken($this->secretKey())
            ->acceptJson()
            ->post($this->apiUrl('/transaction/initialize'), [
                'email' => $email,
                'amount' => PaystackAmount::toPaystack($amount),
                'currency' => 'XOF',
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => $metadata,
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            throw new PaystackException(
                $response->json('message') ?? 'Échec de l\'initialisation Paystack.',
            );
        }

        $data = $response->json('data');

        return new PaystackInitializeResult(
            authorizationUrl: (string) $data['authorization_url'],
            accessCode: (string) $data['access_code'],
            reference: (string) $data['reference'],
        );
    }

    public function verify(string $reference): PaystackVerifyResult
    {
        $response = Http::withToken($this->secretKey())
            ->acceptJson()
            ->get($this->apiUrl('/transaction/verify/'.$reference));

        if (! $response->successful() || ! $response->json('status')) {
            throw new PaystackException(
                $response->json('message') ?? 'Échec de la vérification Paystack.',
            );
        }

        $data = $response->json('data');

        return new PaystackVerifyResult(
            success: ($data['status'] ?? '') === 'success',
            reference: (string) $data['reference'],
            amount: (int) $data['amount'],
            currency: (string) ($data['currency'] ?? 'XOF'),
            paidAt: $data['paid_at'] ?? null,
            channel: $data['channel'] ?? null,
        );
    }

    private function secretKey(): string
    {
        $key = config('paystack.secret_key');

        if (! is_string($key) || $key === '') {
            throw new PaystackException('Clé secrète Paystack non configurée.');
        }

        return $key;
    }

    private function apiUrl(string $path): string
    {
        return config('paystack.payment_url').$path;
    }
}
