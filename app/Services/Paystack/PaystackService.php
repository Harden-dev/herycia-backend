<?php

namespace App\Services\Paystack;

use App\Data\Paystack\PaystackInitializeResult;
use App\Data\Paystack\PaystackVerifyResult;
use App\Exceptions\PaystackException;
use App\Support\Paystack\PaystackAmount;
use Illuminate\Http\Client\PendingRequest;
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
        $response = $this->client()
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
        $response = $this->client()
            ->retry(2, 300, throw: false)
            ->get($this->apiUrl('/transaction/verify/'.rawurlencode($reference)));

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
            status: (string) ($data['status'] ?? ''),
        );
    }

    /**
     * Vérifie la signature d'un webhook Paystack (HMAC SHA-512 du corps brut avec la clé secrète).
     */
    public function isValidWebhookSignature(string $payload, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        try {
            $expected = hash_hmac('sha512', $payload, $this->secretKey());
        } catch (PaystackException) {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->secretKey())
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);
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
