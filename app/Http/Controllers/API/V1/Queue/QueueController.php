<?php

namespace App\Http\Controllers\API\V1\Queue;

use App\Actions\Queue\ListSalonQueueAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Queue\GetQueueRequest;
use App\Http\Resources\V1\Queue\QueueEntryResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="File d'attente",
 *     description="File d'attente du salon"
 * )
 */
class QueueController extends Controller
{
    public function __construct(
        private ListSalonQueueAction $listSalonQueueAction,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/queue",
     *     summary="Liste la file d'attente du salon",
     *     operationId="listSalonQueue",
     *     tags={"File d'attente"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=5)),
     *
     *     @OA\Response(response=200, description="File d'attente récupérée avec succès")
     * )
     */
    public function index(GetQueueRequest $request): JsonResponse
    {
        try {
            $entries = $this->listSalonQueueAction->execute(
                (int) $request->input('limit', 5),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'File d\'attente récupérée avec succès',
                'data' => QueueEntryResource::collection($entries),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur file d\'attente: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
