<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Exceptions\GeniusPayException;
use App\Http\Controllers\Controller;
use App\Jobs\Payment\ProcessGeniusPayWebhookJob;
use App\Services\GeniusPay\GeniusPayService;
use App\Support\ThrottledLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook GeniusPay (audit H4) : source de vérité pour l'activation des abonnements,
 * indépendante du navigateur du client. À déclarer dans le dashboard GeniusPay :
 * {APP_URL}/api/v1/payment/webhook — événements payment.success, payment.failed,
 * payment.cancelled, payment.expired, payment.refunded. Le secret whsec_… va dans
 * GENIUSPAY_WEBHOOK_SECRET.
 *
 * Le contrôleur authentifie, déduplique et met en file : le traitement (relecture du statut
 * via l'API, activation) est fait par ProcessGeniusPayWebhookJob.
 *
 * Seules les données couvertes par la signature (le corps) sont utilisées : les en-têtes
 * X-Webhook-Event et X-Webhook-Delivery sont ignorés (pentest F1).
 */
class GeniusPayWebhookController extends Controller
{
    /** Taille maximale du corps accepté, vérifiée avant tout décodage ou HMAC (pentest F4). */
    private const MAX_PAYLOAD_BYTES = 65536;

    /** Le log critique « secret inutilisable » est émis au plus une fois par période (pentest F5). */
    private const SECRET_ALERT_INTERVAL_SECONDS = 600;

    public function __construct(
        private GeniusPayService $geniusPayService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            return new JsonResponse(['success' => false], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $signature = $request->header('X-Webhook-Signature');

        try {
            $isValid = $this->geniusPayService->isValidWebhookSignature(
                $payload,
                $signature,
                $request->header('X-Webhook-Timestamp'),
            );
        } catch (GeniusPayException $e) {
            // Configuration du secret absente ou invalide : aucun webhook ne peut être accepté.
            if (Cache::add('geniuspay-webhook:secret-alert', true, self::SECRET_ALERT_INTERVAL_SECONDS)) {
                Log::critical('GeniusPay webhook: secret webhook inutilisable, webhooks refusés. '.$e->getMessage());
            }

            return new JsonResponse(['success' => false], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! $isValid) {
            // Agrégé par IP et par minute (pentest F5) : un émetteur non authentifié ne remplit pas les logs.
            ThrottledLog::warning('geniuspay-webhook-invalid-signature:'.$request->ip(), 'GeniusPay webhook: signature invalide.', ['ip' => $request->ip()]);

            return new JsonResponse(['success' => false], Response::HTTP_UNAUTHORIZED);
        }

        $event = json_decode($payload, true, GeniusPayService::WEBHOOK_JSON_MAX_DEPTH);

        if (! is_array($event)) {
            return new JsonResponse(['success' => false], Response::HTTP_BAD_REQUEST);
        }

        $name = $event['event'] ?? null;

        if (! is_string($name) || ! str_starts_with($name, 'payment.')) {
            return new JsonResponse(['success' => true], Response::HTTP_OK);
        }

        return $this->enqueue($event, $name);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function enqueue(array $event, string $name): JsonResponse
    {
        $dedupKey = 'geniuspay-webhook:'.hash('sha256', $this->deliveryId($event, $name));

        // Rejeu d'une livraison déjà acceptée pendant la fenêtre de tolérance : déjà en file.
        if (! Cache::add($dedupKey, true, max(60, 2 * (int) config('geniuspay.webhook_tolerance', 300)))) {
            return new JsonResponse(['success' => true], Response::HTTP_OK);
        }

        $data = is_array($event['data'] ?? null) ? $event['data'] : [];

        try {
            ProcessGeniusPayWebhookJob::dispatch(
                $name,
                $this->stringOrNull($data['reference'] ?? null),
                $this->stringOrNull($data['metadata']['reference'] ?? null),
                $dedupKey,
            );
        } catch (\Throwable $e) {
            // File indisponible : on libère la déduplication pour que GeniusPay puisse réessayer.
            Cache::forget($dedupKey);
            Log::error('GeniusPay webhook: mise en file impossible: '.$e->getMessage());

            return new JsonResponse(['success' => false], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // 200 immédiat pour un webhook authentique : GeniusPay réessaie sinon.
        return new JsonResponse(['success' => true], Response::HTTP_OK);
    }

    /**
     * Identifiant de livraison signé : type d'événement + id du corps (un payment.refunded portant
     * le même id qu'un payment.success n'est pas écarté, pentest F2), sinon hash de la forme
     * canonique json_encode(json_decode(corps)) : celle que la signature accepte, donc invariante
     * aux variantes d'espaces d'une livraison rejouée (contre-audit N6).
     *
     * @param  array<string, mixed>  $event
     */
    private function deliveryId(array $event, string $name): string
    {
        $id = $event['id'] ?? null;

        return is_scalar($id) && (string) $id !== ''
            ? 'id:'.$name.':'.$id
            : 'body:'.hash('sha256', (string) json_encode($event));
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
