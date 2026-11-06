<?php

namespace App\Http\Controllers\API\V1\Subscription;

use App\Actions\Subscription\GetSalonSubscriptionAction;
use App\Actions\Subscription\InitializeSubscriptionPaymentAction;
use App\Actions\Subscription\SimulateSubscriptionPaymentAction;
use App\Enums\BillingPaymentMethod;
use App\Exceptions\GeniusPayException;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyKeyMismatchException;
use App\Exceptions\PlanNotFoundException;
use App\Exceptions\SubscriptionAlreadyActiveException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Subscription\InitializeSubscriptionPaymentRequest;
use App\Http\Requests\V1\Subscription\SimulateSubscriptionPaymentRequest;
use App\Http\Resources\V1\Salon\SubscriptionResource;
use App\Http\Resources\V1\Subscription\SalonSubscriptionResource;
use App\Http\Resources\V1\Subscription\SubscriptionPaymentInitializeResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    private const PAYMENT_UNAVAILABLE_MESSAGE = 'Service de paiement momentanément indisponible.';

    public function __construct(
        private GetSalonSubscriptionAction $getSalonSubscriptionAction,
        private InitializeSubscriptionPaymentAction $initializeSubscriptionPaymentAction,
        private SimulateSubscriptionPaymentAction $simulateSubscriptionPaymentAction,
    ) {}

    public function initialize(InitializeSubscriptionPaymentRequest $request): JsonResponse
    {
        try {
            $payload = $this->initializeSubscriptionPaymentAction->execute(
                $request->string('plan_code')->toString(),
                $request->string('idempotency_key')->toString(),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Paiement initialisé avec succès.',
                'data' => new SubscriptionPaymentInitializeResource($payload),
            ], Response::HTTP_OK, [
                'Idempotent-Replayed' => $payload['replayed'] ? 'true' : 'false',
            ]);
        } catch (SubscriptionAlreadyActiveException|PlanNotFoundException|IdempotencyKeyMismatchException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (IdempotencyConflictException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
                'code' => $e->reason,
            ], Response::HTTP_CONFLICT);
        } catch (GeniusPayException $e) {
            // Détail (message prestataire, configuration) uniquement dans les logs.
            Log::error('Erreur GeniusPay initialize: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => self::PAYMENT_UNAVAILABLE_MESSAGE,
            ], Response::HTTP_BAD_GATEWAY);
        } catch (Exception $e) {
            Log::error('Erreur initialize abonnement: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(): JsonResponse
    {
        try {
            $payload = $this->getSalonSubscriptionAction->execute();

            return new JsonResponse([
                'success' => true,
                'message' => 'Abonnement récupéré avec succès.',
                'data' => new SalonSubscriptionResource($payload),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur abonnement salon: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function simulatePayment(SimulateSubscriptionPaymentRequest $request): JsonResponse
    {
        try {
            $subscription = $this->simulateSubscriptionPaymentAction->execute(
                BillingPaymentMethod::from($request->string('method')->toString()),
                $request->input('plan_code'),
            );

            $message = $request->filled('plan_code')
                ? 'Abonnement mis à jour avec succès.'
                : 'Paiement simulé avec succès. Abonnement activé.';

            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'data' => new SubscriptionResource($subscription),
            ], Response::HTTP_OK);
        } catch (SubscriptionAlreadyActiveException|PlanNotFoundException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Erreur simulation paiement abonnement: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
