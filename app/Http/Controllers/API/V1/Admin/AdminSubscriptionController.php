<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\Admin\AdminSubscriptionAction;
use App\Data\Admin\UpdateAdminSubscriptionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\UpdateAdminSubscriptionRequest;
use App\Http\Resources\V1\Admin\AdminSubscriptionResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        private AdminSubscriptionAction $adminSubscriptionAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $subscriptions = $this->adminSubscriptionAction->index(
                (int) $request->input('per_page', 15),
                $request->input('search'),
                $request->input('plan_id'),
                $request->input('status'),
                $request->input('salon_id'),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Liste des abonnements récupérée avec succès',
                'data' => AdminSubscriptionResource::collection($subscriptions->items()),
                'pagination' => [
                    'total_rows' => $subscriptions->total(),
                    'per_page' => $subscriptions->perPage(),
                    'current_page' => $subscriptions->currentPage(),
                    'last_page' => $subscriptions->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste abonnements admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $subscription = $this->adminSubscriptionAction->show($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Abonnement récupéré avec succès',
                'data' => new AdminSubscriptionResource($subscription),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur détail abonnement admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(UpdateAdminSubscriptionRequest $request, string $id): JsonResponse
    {
        try {
            $subscription = $this->adminSubscriptionAction->update($id, UpdateAdminSubscriptionData::fromRequest($request));

            return new JsonResponse([
                'success' => true,
                'message' => 'Abonnement mis à jour avec succès',
                'data' => new AdminSubscriptionResource($subscription),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour abonnement admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function cancel(string $id): JsonResponse
    {
        try {
            $subscription = $this->adminSubscriptionAction->cancel($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Abonnement annulé avec succès',
                'data' => new AdminSubscriptionResource($subscription),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur annulation abonnement admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
