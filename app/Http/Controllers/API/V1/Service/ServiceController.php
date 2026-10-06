<?php

namespace App\Http\Controllers\API\V1\Service;

use App\Actions\Service\CreateSalonServiceAction;
use App\Actions\Service\DeactivateSalonServiceAction;
use App\Actions\Service\ListSalonServicesAction;
use App\Actions\Service\UpdateSalonServiceAction;
use App\Data\Service\CreateSalonServiceData;
use App\Data\Service\UpdateSalonServiceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Service\CreateSalonServiceRequest;
use App\Http\Requests\V1\Service\GetServicesRequest;
use App\Http\Requests\V1\Service\UpdateSalonServiceRequest;
use App\Http\Resources\V1\Service\ServiceResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Prestations",
 *     description="Gestion des prestations du salon"
 * )
 */
class ServiceController extends Controller
{
    public function __construct(
        private ListSalonServicesAction $listSalonServicesAction,
        private CreateSalonServiceAction $createSalonServiceAction,
        private UpdateSalonServiceAction $updateSalonServiceAction,
        private DeactivateSalonServiceAction $deactivateSalonServiceAction,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/services",
     *     summary="Liste des prestations du salon",
     *     operationId="listSalonServices",
     *     tags={"Prestations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Liste récupérée avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="name", type="string", example="Coupe homme"),
     *                     @OA\Property(property="duration_min", type="integer", example=30),
     *                     @OA\Property(property="price", type="integer", example=2000),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total_rows", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(GetServicesRequest $request): JsonResponse
    {
        try {
            $services = $this->listSalonServicesAction->execute(
                (int) $request->input('per_page', 15),
                $request->input('search'),
                $request->has('is_active') ? $request->boolean('is_active') : null,
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Liste des prestations récupérée avec succès',
                'data' => ServiceResource::collection($services->items()),
                'pagination' => [
                    'total_rows' => $services->total(),
                    'per_page' => $services->perPage(),
                    'current_page' => $services->currentPage(),
                    'last_page' => $services->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste prestations: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/services",
     *     summary="Créer une prestation",
     *     operationId="createSalonService",
     *     tags={"Prestations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "duration_min", "price"},
     *
     *             @OA\Property(property="name", type="string", example="Coupe homme"),
     *             @OA\Property(property="duration_min", type="integer", example=30),
     *             @OA\Property(property="price", type="integer", example=2000, description="Prix en FCFA")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Prestation créée avec succès"),
     *     @OA\Response(response=403, description="Accès réservé aux administrateurs"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(CreateSalonServiceRequest $request): JsonResponse
    {
        try {
            $service = $this->createSalonServiceAction->execute(
                CreateSalonServiceData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Prestation créée avec succès',
                'data' => new ServiceResource($service),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Erreur création prestation: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/services/{id}",
     *     summary="Modifier une prestation",
     *     operationId="updateSalonService",
     *     tags={"Prestations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="Coupe + barbe"),
     *             @OA\Property(property="duration_min", type="integer", example=45),
     *             @OA\Property(property="price", type="integer", example=3500)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Prestation mise à jour avec succès"),
     *     @OA\Response(response=403, description="Accès réservé aux administrateurs"),
     *     @OA\Response(response=404, description="Prestation introuvable")
     * )
     */
    public function update(UpdateSalonServiceRequest $request, string $id): JsonResponse
    {
        try {
            $service = $this->updateSalonServiceAction->execute(
                $id,
                UpdateSalonServiceData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Prestation mise à jour avec succès',
                'data' => new ServiceResource($service),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour prestation: '.$e->getMessage());

            $status = str_contains($e->getMessage(), 'introuvable')
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_BAD_REQUEST;

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], $status);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/services/{id}",
     *     summary="Désactiver une prestation",
     *     description="Met is_active à false (pas de suppression physique)",
     *     operationId="deactivateSalonService",
     *     tags={"Prestations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Prestation désactivée avec succès"),
     *     @OA\Response(response=403, description="Accès réservé aux administrateurs"),
     *     @OA\Response(response=404, description="Prestation introuvable")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $service = $this->deactivateSalonServiceAction->execute($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Prestation désactivée avec succès',
                'data' => new ServiceResource($service),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur désactivation prestation: '.$e->getMessage());

            $status = str_contains($e->getMessage(), 'introuvable')
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_BAD_REQUEST;

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], $status);
        }
    }
}
