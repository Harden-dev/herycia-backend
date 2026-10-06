<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\Admin\AdminSalonAction;
use App\Data\Admin\UpdateAdminSalonData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\UpdateAdminSalonRequest;
use App\Http\Resources\V1\Admin\AdminSalonResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminSalonController extends Controller
{
    public function __construct(
        private AdminSalonAction $adminSalonAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $salons = $this->adminSalonAction->index(
                (int) $request->input('per_page', 15),
                $request->input('search'),
                $request->input('city'),
                $request->input('plan_id'),
                $request->has('is_active') ? $request->boolean('is_active') : null,
                $request->has('is_suspended') ? $request->boolean('is_suspended') : null,
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Liste des salons récupérée avec succès',
                'data' => AdminSalonResource::collection($salons->items()),
                'pagination' => [
                    'total_rows' => $salons->total(),
                    'per_page' => $salons->perPage(),
                    'current_page' => $salons->currentPage(),
                    'last_page' => $salons->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste salons admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $salon = $this->adminSalonAction->show($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Salon récupéré avec succès',
                'data' => new AdminSalonResource($salon),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur détail salon admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(UpdateAdminSalonRequest $request, string $id): JsonResponse
    {
        try {
            $salon = $this->adminSalonAction->update($id, UpdateAdminSalonData::fromRequest($request));

            return new JsonResponse([
                'success' => true,
                'message' => 'Salon mis à jour avec succès',
                'data' => new AdminSalonResource($salon),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour salon admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function suspend(string $id): JsonResponse
    {
        try {
            $salon = $this->adminSalonAction->suspend($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Salon suspendu avec succès',
                'data' => new AdminSalonResource($salon),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur suspension salon admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function deactivate(string $id): JsonResponse
    {
        try {
            $salon = $this->adminSalonAction->deactivate($id);

            return new JsonResponse([
                'success' => true,
                'message' => $salon->is_active
                    ? 'Salon activé avec succès'
                    : 'Salon désactivé avec succès',
                'data' => new AdminSalonResource($salon),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur activation/désactivation salon admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->adminSalonAction->destroy($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Salon supprimé avec succès',
                'data' => null,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur suppression salon admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
