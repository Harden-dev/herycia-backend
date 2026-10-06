<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Actions\Subscription\HandlePaystackCallbackAction;
use App\Http\Controllers\Controller;
use App\Services\Paystack\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook Paystack (audit H4) : source de vérité pour l'activation des abonnements,
 * indépendante du navigateur du client. À déclarer dans le dashboard Paystack :
 * {APP_URL}/api/v1/payment/webhook
 */
class PaystackWebhookController extends Controller
{
    public function __construct(
        private PaystackService $paystackService,
        private HandlePaystackCallbackAction $handlePaystackCallbackAction,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! $this->paystackService->isValidWebhookSignature($payload, $request->header('x-paystack-signature'))) {
            Log::warning('Paystack webhook: signature invalide.', ['ip' => $request->ip()]);

            return new JsonResponse(['success' => false], Response::HTTP_UNAUTHORIZED);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return new JsonResponse(['success' => false], Response::HTTP_BAD_REQUEST);
        }

        if (($event['event'] ?? null) === 'charge.success') {
            $reference = $event['data']['reference'] ?? null;

            // La référence est revérifiée auprès de l'API Paystack (montant, devise, statut)
            // avant toute activation : le corps du webhook n'est jamais cru tel quel.
            $this->handlePaystackCallbackAction->execute(is_string($reference) ? $reference : null);
        }

        // Toujours 200 pour un webhook authentique : Paystack réessaie sinon.
        return new JsonResponse(['success' => true], Response::HTTP_OK);
    }
}
