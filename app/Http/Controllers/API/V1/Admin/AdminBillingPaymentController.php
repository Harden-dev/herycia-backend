<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\Admin\AdminBillingPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AdminBillingPaymentResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminBillingPaymentController extends Controller
{
    public function __construct(
        private AdminBillingPaymentAction $adminBillingPaymentAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $payments = $this->adminBillingPaymentAction->index(
                (int) $request->input('per_page', 15),
                $request->input('from'),
                $request->input('to'),
                $request->input('plan_id'),
                $request->input('status'),
                $request->input('salon_id'),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Liste des paiements de facturation récupérée avec succès',
                'data' => AdminBillingPaymentResource::collection($payments->items()),
                'pagination' => [
                    'total_rows' => $payments->total(),
                    'per_page' => $payments->perPage(),
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste paiements facturation admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $payment = $this->adminBillingPaymentAction->show($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Paiement de facturation récupéré avec succès',
                'data' => new AdminBillingPaymentResource($payment),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur détail paiement facturation admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
