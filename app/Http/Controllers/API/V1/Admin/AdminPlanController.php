<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\Admin\AdminPlanAction;
use App\Data\Admin\CreateAdminPlanData;
use App\Data\Admin\UpdateAdminPlanData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\CreateAdminPlanRequest;
use App\Http\Requests\V1\Admin\UpdateAdminPlanRequest;
use App\Http\Resources\V1\Admin\AdminPlanResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminPlanController extends Controller
{
    public function __construct(
        private AdminPlanAction $adminPlanAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $plans = $this->adminPlanAction->index(
                (int) $request->input('per_page', 15),
                $request->has('include_archived') ? $request->boolean('include_archived') : null,
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Liste des plans récupérée avec succès',
                'data' => AdminPlanResource::collection($plans->items()),
                'pagination' => [
                    'total_rows' => $plans->total(),
                    'per_page' => $plans->perPage(),
                    'current_page' => $plans->currentPage(),
                    'last_page' => $plans->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste plans admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(CreateAdminPlanRequest $request): JsonResponse
    {
        try {
            $plan = $this->adminPlanAction->store(CreateAdminPlanData::fromRequest($request));

            return new JsonResponse([
                'success' => true,
                'message' => 'Plan créé avec succès',
                'data' => new AdminPlanResource($plan),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Erreur création plan admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $plan = $this->adminPlanAction->show($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Plan récupéré avec succès',
                'data' => new AdminPlanResource($plan),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur détail plan admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(UpdateAdminPlanRequest $request, string $id): JsonResponse
    {
        try {
            $plan = $this->adminPlanAction->update($id, UpdateAdminPlanData::fromRequest($request));

            return new JsonResponse([
                'success' => true,
                'message' => 'Plan mis à jour avec succès',
                'data' => new AdminPlanResource($plan),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour plan admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function archive(string $id): JsonResponse
    {
        try {
            $plan = $this->adminPlanAction->archive($id);

            return new JsonResponse([
                'success' => true,
                'message' => $plan->is_archived
                    ? 'Plan archivé avec succès'
                    : 'Plan restauré avec succès',
                'data' => new AdminPlanResource($plan),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur archivage plan admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
