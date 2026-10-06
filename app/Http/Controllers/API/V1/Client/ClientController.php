<?php

namespace App\Http\Controllers\API\V1\Client;

use App\Actions\Client\CreateSalonClientAction;
use App\Actions\Client\GetSalonClientAction;
use App\Actions\Client\ListSalonClientsAction;
use App\Actions\Client\UpdateSalonClientAction;
use App\Data\Client\CreateSalonClientData;
use App\Data\Client\UpdateSalonClientData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Client\CreateSalonClientRequest;
use App\Http\Requests\V1\Client\GetClientsRequest;
use App\Http\Requests\V1\Client\UpdateSalonClientRequest;
use App\Http\Resources\V1\Client\ClientDetailResource;
use App\Http\Resources\V1\Client\ClientResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Clients",
 *     description="Gestion des clients du salon"
 * )
 */
class ClientController extends Controller
{
    public function __construct(
        private ListSalonClientsAction $listSalonClientsAction,
        private GetSalonClientAction $getSalonClientAction,
        private CreateSalonClientAction $createSalonClientAction,
        private UpdateSalonClientAction $updateSalonClientAction,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/clients",
     *     summary="Liste des clients du salon",
     *     operationId="listSalonClients",
     *     tags={"Clients"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Liste récupérée avec succès")
     * )
     */
    public function index(GetClientsRequest $request): JsonResponse
    {
        try {
            $clients = $this->listSalonClientsAction->execute(
                (int) $request->input('per_page', 15),
                $request->input('search'),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Liste des clients récupérée avec succès',
                'data' => ClientResource::collection($clients->items()),
                'pagination' => [
                    'total_rows' => $clients->total(),
                    'per_page' => $clients->perPage(),
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage(),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste clients: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/clients",
     *     summary="Créer un client manuellement",
     *     operationId="createSalonClient",
     *     tags={"Clients"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "phone"},
     *
     *             @OA\Property(property="name", type="string", example="Fatou Diallo"),
     *             @OA\Property(property="phone", type="string", example="0748754918"),
     *             @OA\Property(property="whatsapp_id", type="string", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Client créé avec succès"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(CreateSalonClientRequest $request): JsonResponse
    {
        try {
            $client = $this->createSalonClientAction->execute(
                CreateSalonClientData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Client créé avec succès',
                'data' => new ClientResource($client),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Erreur création client: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/{id}",
     *     summary="Fiche client avec historique RDV et paiements",
     *     operationId="getSalonClient",
     *     tags={"Clients"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Client récupéré avec succès"),
     *     @OA\Response(response=404, description="Client introuvable")
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $detail = $this->getSalonClientAction->execute($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Client récupéré avec succès',
                'data' => new ClientDetailResource($detail),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur détail client: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/clients/{id}",
     *     summary="Modifier un client",
     *     operationId="updateSalonClient",
     *     tags={"Clients"},
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
     *             @OA\Property(property="whatsapp_id", type="string", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Client mis à jour avec succès"),
     *     @OA\Response(response=404, description="Client introuvable")
     * )
     */
    public function update(UpdateSalonClientRequest $request, string $id): JsonResponse
    {
        try {
            $client = $this->updateSalonClientAction->execute(
                $id,
                UpdateSalonClientData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Client mis à jour avec succès',
                'data' => new ClientResource($client),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour client: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
