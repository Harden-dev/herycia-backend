<?php

namespace App\Http\Controllers\API\V1\Payment;

use App\Actions\Payment\CreateSalonPaymentAction;
use App\Actions\Payment\GetSalonPaymentSummaryAction;
use App\Actions\Payment\ListSalonPaymentsAction;
use App\Data\Payment\CreateSalonPaymentData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Payment\CreateSalonPaymentRequest;
use App\Http\Requests\V1\Payment\GetPaymentsRequest;
use App\Http\Resources\V1\Payment\PaymentResource;
use App\Http\Resources\V1\Payment\PaymentSummaryResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Paiements",
 *     description="Gestion des paiements du salon"
 * )
 */
class PaymentController extends Controller
{
    public function __construct(
        private ListSalonPaymentsAction $listSalonPaymentsAction,
        private CreateSalonPaymentAction $createSalonPaymentAction,
        private GetSalonPaymentSummaryAction $getSalonPaymentSummaryAction,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/payments",
     *     summary="Historique des paiements",
     *     operationId="listSalonPayments",
     *     tags={"Paiements"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="method", in="query", @OA\Schema(type="string", enum={"cash", "mobile_money", "card"})),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending", "paid", "failed", "refunded"})),
     *
     *     @OA\Response(response=200, description="Historique récupéré avec succès"),
     *     @OA\Response(response=403, description="Accès refusé")
     * )
     */
    public function index(GetPaymentsRequest $request): JsonResponse
    {
        try {
            $payments = $this->listSalonPaymentsAction->execute(
                (int) $request->input('per_page', 15),
                $request->input('date'),
                $request->input('from'),
                $request->input('to'),
                $request->filled('method') ? PaymentMethod::from($request->string('method')->toString()) : null,
                $request->filled('status') ? PaymentStatus::from($request->string('status')->toString()) : null,
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Historique des paiements récupéré avec succès',
                'data' => PaymentResource::collection($payments->items()),
                'pagination' => [
                    'total_rows' => $payments->total(),
                    'per_page' => $payments->perPage(),
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste paiements: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments",
     *     summary="Enregistrer un paiement",
     *     operationId="createSalonPayment",
     *     tags={"Paiements"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"appointment_id", "amount", "method"},
     *
     *             @OA\Property(property="appointment_id", type="string", format="uuid"),
     *             @OA\Property(property="amount", type="integer", example=5000),
     *             @OA\Property(property="method", type="string", enum={"cash", "mobile_money", "card"}),
     *             @OA\Property(property="mobile_money_ref", type="string", nullable=true),
     *             @OA\Property(property="paid_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Paiement enregistré avec succès"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(CreateSalonPaymentRequest $request): JsonResponse
    {
        try {
            $payment = $this->createSalonPaymentAction->execute(
                CreateSalonPaymentData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'data' => new PaymentResource($payment),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Erreur enregistrement paiement: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/summary",
     *     summary="Résumé des recettes (jour, semaine, mois)",
     *     operationId="getSalonPaymentSummary",
     *     tags={"Paiements"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Résumé récupéré avec succès"),
     *     @OA\Response(response=403, description="Accès réservé aux administrateurs")
     * )
     */
    public function summary(): JsonResponse
    {
        try {
            $summary = $this->getSalonPaymentSummaryAction->execute();

            return new JsonResponse([
                'success' => true,
                'message' => 'Résumé des recettes récupéré avec succès',
                'data' => new PaymentSummaryResource($summary),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur résumé paiements: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
