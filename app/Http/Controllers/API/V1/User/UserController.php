<?php

namespace App\Http\Controllers\API\V1\User;

use App\Actions\User\CreateSalonEmployeeAction;
use App\Actions\User\DeactivateSalonEmployeeAction;
use App\Actions\User\GetSalonEmployeeAction;
use App\Actions\User\ListSalonEmployeesAction;
use App\Actions\User\UpdateSalonEmployeeAction;
use App\Data\User\CreateSalonEmployeeData;
use App\Data\User\UpdateSalonEmployeeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\User\CreateSalonEmployeeRequest;
use App\Http\Requests\V1\User\GetUsersRequest;
use App\Http\Requests\V1\User\UpdateSalonEmployeeRequest;
use App\Http\Resources\V1\UserResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Employés",
 *     description="Gestion des employés du salon"
 * )
 */
class UserController extends Controller
{
    public function __construct(
        private ListSalonEmployeesAction $listSalonEmployeesAction,
        private GetSalonEmployeeAction $getSalonEmployeeAction,
        private CreateSalonEmployeeAction $createSalonEmployeeAction,
        private UpdateSalonEmployeeAction $updateSalonEmployeeAction,
        private DeactivateSalonEmployeeAction $deactivateSalonEmployeeAction,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     summary="Liste des employés du salon",
     *     operationId="listSalonEmployees",
     *     tags={"Employés"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *
     *     @OA\Response(response=200, description="Liste récupérée avec succès"),
     *     @OA\Response(response=403, description="Accès réservé aux administrateurs")
     * )
     */
    public function index(GetUsersRequest $request): JsonResponse
    {
        try {
            $employees = $this->listSalonEmployeesAction->execute(
                (int) $request->input('per_page', 15),
                $request->input('search'),
                $request->has('is_active') ? $request->boolean('is_active') : null,
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Liste des employés récupérée avec succès',
                'data' => UserResource::collection($employees->items()),
                'pagination' => [
                    'total_rows' => $employees->total(),
                    'per_page' => $employees->perPage(),
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste employés: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     summary="Créer un employé",
     *     operationId="createSalonEmployee",
     *     tags={"Employés"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "phone", "password", "password_confirmation", "role"},
     *
     *             @OA\Property(property="name", type="string", example="Aya Koné"),
     *             @OA\Property(property="phone", type="string", example="2250708112233"),
     *             @OA\Property(property="email", type="string", nullable=true, example="aya@salon.ci"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="role", type="string", enum={"manager", "stylist", "receptionist"})
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Employé créé avec succès"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(CreateSalonEmployeeRequest $request): JsonResponse
    {
        try {
            $employee = $this->createSalonEmployeeAction->execute(
                CreateSalonEmployeeData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Employé créé avec succès',
                'data' => new UserResource($employee),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Erreur création employé: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     summary="Détail d'un employé",
     *     operationId="getSalonEmployee",
     *     tags={"Employés"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Employé récupéré avec succès"),
     *     @OA\Response(response=404, description="Employé introuvable")
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $employee = $this->getSalonEmployeeAction->execute($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Employé récupéré avec succès',
                'data' => new UserResource($employee),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur détail employé: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     summary="Modifier un employé",
     *     operationId="updateSalonEmployee",
     *     tags={"Employés"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="email", type="string", nullable=true),
     *             @OA\Property(property="role", type="string", enum={"manager", "stylist", "receptionist"}),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Employé mis à jour avec succès"),
     *     @OA\Response(response=404, description="Employé introuvable")
     * )
     */
    public function update(UpdateSalonEmployeeRequest $request, string $id): JsonResponse
    {
        try {
            $employee = $this->updateSalonEmployeeAction->execute(
                $id,
                UpdateSalonEmployeeData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Employé mis à jour avec succès',
                'data' => new UserResource($employee),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour employé: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     summary="Activer ou désactiver un employé",
     *     description="Bascule is_active (pas de suppression physique)",
     *     operationId="toggleSalonEmployeeStatus",
     *     tags={"Employés"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Statut employé mis à jour avec succès"),
     *     @OA\Response(response=404, description="Employé introuvable")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $employee = $this->deactivateSalonEmployeeAction->execute($id);

            return new JsonResponse([
                'success' => true,
                'message' => $employee->is_active
                    ? 'Employé activé avec succès'
                    : 'Employé désactivé avec succès',
                'data' => new UserResource($employee),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur changement statut employé: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
