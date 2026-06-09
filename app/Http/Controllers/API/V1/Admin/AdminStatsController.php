<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\Admin\AdminStatsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AdminStatsOverviewResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminStatsController extends Controller
{
    public function __construct(
        private AdminStatsAction $adminStatsAction,
    ) {}

    public function overview(): JsonResponse
    {
        try {
            $overview = $this->adminStatsAction->overview();

            return new JsonResponse([
                'success' => true,
                'message' => 'Vue d\'ensemble récupérée avec succès',
                'data' => new AdminStatsOverviewResource($overview),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur récupération statistiques admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function salonsByCity(): JsonResponse
    {
        try {
            $salonsByCity = $this->adminStatsAction->salonsByCity();

            return new JsonResponse([
                'success' => true,
                'message' => 'Répartition des salons par ville récupérée avec succès',
                'data' => $salonsByCity,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur récupération salons par ville: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
