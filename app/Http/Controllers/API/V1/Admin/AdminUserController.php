<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\Admin\AdminUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ResetAdminUserPasswordRequest;
use App\Http\Resources\V1\Admin\AdminUserResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public function __construct(
        private AdminUserAction $adminUserAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $users = $this->adminUserAction->index(
                (int) $request->input('per_page', 15),
                $request->input('search'),
                $request->has('is_active') ? $request->boolean('is_active') : null,
                $request->input('user_type'),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Liste des utilisateurs récupérée avec succès',
                'data' => AdminUserResource::collection($users->items()),
                'pagination' => [
                    'total_rows' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste utilisateurs admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $user = $this->adminUserAction->show($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Utilisateur récupéré avec succès',
                'data' => new AdminUserResource($user),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur détail utilisateur admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function block(string $id): JsonResponse
    {
        try {
            $user = $this->adminUserAction->block($id);

            return new JsonResponse([
                'success' => true,
                'message' => $user->is_active
                    ? 'Utilisateur débloqué avec succès'
                    : 'Utilisateur bloqué avec succès',
                'data' => new AdminUserResource($user),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur blocage utilisateur admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function resetPassword(ResetAdminUserPasswordRequest $request, string $id): JsonResponse
    {
        try {
            $result = $this->adminUserAction->resetPassword($id, $request->input('new_password'));

            return new JsonResponse([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès',
                'data' => [
                    'user' => new AdminUserResource($result['user']),
                    'generated_password' => $result['password'],
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur réinitialisation mot de passe admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
