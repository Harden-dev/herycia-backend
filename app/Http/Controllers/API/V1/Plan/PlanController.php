<?php

namespace App\Http\Controllers\API\V1\Plan;

use App\Actions\Plan\ListPublicPlansAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Plan\PublicPlanResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PlanController extends Controller
{
    public function __construct(
        private ListPublicPlansAction $listPublicPlansAction,
    ) {}

    public function index(): JsonResponse
    {
        try {
            $plans = $this->listPublicPlansAction->execute();

            return new JsonResponse([
                'success' => true,
                'message' => 'Plans récupérés avec succès.',
                'data' => PublicPlanResource::collection($plans),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste plans publics: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des plans.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
