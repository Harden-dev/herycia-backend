<?php

namespace App\Services\GeniusPay;

use App\Data\GeniusPay\GeniusPayPaymentResult;
use App\Data\GeniusPay\GeniusPayVerifyResult;
use App\Exceptions\GeniusPayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GeniusPayService
{
    private const DEFAULT_TIMEOUT_SECONDS = 20;

    /** Profondeur maximale du JSON décodé avant authentification du webhook (pentest F4). */
    public const WEBHOOK_JSON_MAX_DEPTH = 32;

    /**
     * Crée un paiement en mode « checkout » (sans payment_method) : le client choisit
     * Wave, Orange Money, MTN, Moov ou carte sur la page hébergée GeniusPay.
     *
     * @param  array{name?: string|null, email?: string|null, phone?: string|null}  $customer
     * @param  array<string, string|int>  $metadata
     *
     * @throws GeniusPayException
     */
    public function createPayment(
        int $amount,
        string $description,
        string $successUrl,
        string $errorUrl,
        array $customer = [],
        array $metadata = [],
        string $currency = 'XOF',
    ): GeniusPayPaymentResult {
        $body = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => mb_substr($description, 0, 500),
            'success_url' => $successUrl,
            'error_url' => $errorUrl,
            'metadata' => $metadata,
        ];

        $customer = array_filter($customer, fn ($value) => is_string($value) && $value !== '');
        if ($customer !== []) {
            $body['customer'] = $customer;
        }

        // Pas de retry sur la création : un POST rejoué créerait un second paiement.
        $response = $this->send(fn () => $this->client()->post($this->apiUrl('/payments'), $body));

        if (! $response->successful() || ! $response->json('success')) {
            throw new GeniusPayException($this->errorMessage($response, 'Échec de l\'initialisation GeniusPay.'));
        }

        $data = (array) $response->json('data');
        $checkoutUrl = $data['checkout_url'] ?? $data['payment_url'] ?? null;

        if (! is_string($checkoutUrl) || $checkoutUrl === '' || empty($data['reference'])) {
            throw new GeniusPayException('Réponse GeniusPay incomplète (URL ou référence manquante).');
        }

        return new GeniusPayPaymentResult(
            checkoutUrl: $checkoutUrl,
            reference: (string) $data['reference'],
            expiresAt: $this->optionalString($data, 'expires_at'),
        );
    }

    /**
     * Relit un paiement. Les champs indispensables (reference, status, amount, currency) sont
     * exigés : une réponse incomplète lève une exception et la transaction reste en attente.
     *
     * @param  bool  $retry  false pour un appel en ligne (retour navigateur) : pas de nouvel essai.
     *
     * @throws GeniusPayException
     */
    public function getPayment(string $reference, ?int $timeoutSeconds = null, bool $retry = true): GeniusPayVerifyResult
    {
        $client = $this->client($timeoutSeconds);

        if ($retry) {
            $client = $client->retry(2, 300, throw: false);
        }

        $response = $this->send(fn () => $client->get($this->apiUrl('/payments/'.rawurlencode($reference))));

        if (! $response->successful() || ! $response->json('success')) {
            throw new GeniusPayException($this->errorMessage($response, 'Échec de la vérification GeniusPay.'));
        }

        $data = (array) $response->json('data');

        return new GeniusPayVerifyResult(
            reference: $this->requiredString($data, 'reference'),
            status: $this->requiredString($data, 'status'),
            amount: $this->requiredAmount($data, 'amount'),
            currency: $this->requiredString($data, 'currency'),
            completedAt: $this->optionalString($data, 'completed_at'),
            paymentMethod: $this->optionalString($data, 'payment_method'),
            provider: $this->optionalString($data, 'payment_provider') ?? $this->optionalString($data, 'provider'),
            fees: $this->optionalAmount($data, 'fees'),
            netAmount: $this->optionalAmount($data, 'net_amount'),
            environment: $this->optionalString($data, 'environment'),
        );
    }

    /**
     * Signature webhook GeniusPay : HMAC-SHA256(timestamp + "." + payload, webhook_secret),
     * avec rejet hors fenêtre temporelle (anti-rejeu).
     *
     * On valide d'abord sur le corps brut ; la doc officielle signe un json_encode() du payload
     * décodé, on accepte donc aussi cette forme (les deux exigent le secret).
     *
     * @throws GeniusPayException secret absent, ou secret sandbox en production
     */
    public function isValidWebhookSignature(string $payload, ?string $signature, ?string $timestamp): bool
    {
        $secret = $this->credential('webhook_secret', 'whsec_sandbox_');

        if ($signature === null || $signature === ''
            || $timestamp === null || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > max(1, (int) config('geniuspay.webhook_tolerance', 300))) {
            return false;
        }

        $candidates = [$payload];
        $decoded = json_decode($payload, true, self::WEBHOOK_JSON_MAX_DEPTH);
        if (is_array($decoded)) {
            $candidates[] = (string) json_encode($decoded);
        }

        foreach ($candidates as $body) {
            if (hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), $signature)) {
                return true;
            }
        }

        return false;
    }

    private function client(?int $timeoutSeconds = null): PendingRequest
    {
        return Http::withHeaders([
            'X-API-Key' => $this->credential('public_key', 'pk_sandbox_'),
            'X-API-Secret' => $this->credential('secret_key', 'sk_sandbox_'),
        ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout($timeoutSeconds ?? self::DEFAULT_TIMEOUT_SECONDS);
    }

    /**
     * Une erreur réseau devient une GeniusPayException : l'appelant n'a qu'un type d'erreur à gérer.
     *
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new GeniusPayException('GeniusPay injoignable : '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws GeniusPayException
     */
    private function credential(string $name, string $sandboxPrefix): string
    {
        $value = config('geniuspay.'.$name);

        if (! is_string($value) || $value === '') {
            throw new GeniusPayException("GeniusPay : {$name} non configuré.");
        }

        if (app()->isProduction() && str_starts_with($value, $sandboxPrefix)) {
            throw new GeniusPayException("GeniusPay : {$name} sandbox refusé en production.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requiredString(array $data, string $field): string
    {
        $value = $this->optionalString($data, $field);

        if ($value === null) {
            throw new GeniusPayException("Réponse GeniusPay incomplète : champ {$field} manquant.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requiredAmount(array $data, string $field): int
    {
        $amount = $this->optionalAmount($data, $field);

        if ($amount === null) {
            throw new GeniusPayException("Réponse GeniusPay incomplète : champ {$field} manquant.");
        }

        return $amount;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalAmount(array $data, string $field): ?int
    {
        $value = $data[$field] ?? null;

        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalString(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function apiUrl(string $path): string
    {
        return config('geniuspay.base_url').$path;
    }

    private function errorMessage(Response $response, string $default): string
    {
        $message = $response->json('error.message') ?? $response->json('message');

        return is_string($message) && $message !== '' ? $message : $default;
    }
}
