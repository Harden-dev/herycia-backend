<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Actions\Dashboard\GetDashboardActivityAction;
use App\Actions\Dashboard\GetDashboardClientsStatsAction;
use App\Actions\Dashboard\GetDashboardOverviewAction;
use App\Actions\Dashboard\GetDashboardRevenueStatsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Dashboard\GetDashboardActivityRequest;
use App\Http\Requests\V1\Dashboard\GetDashboardClientsStatsRequest;
use App\Http\Requests\V1\Dashboard\GetDashboardOverviewRequest;
use App\Http\Requests\V1\Dashboard\GetDashboardRevenueStatsRequest;
use App\Http\Resources\V1\Dashboard\DashboardActivityResource;
use App\Http\Resources\V1\Dashboard\DashboardChartStatsResource;
use App\Http\Resources\V1\Dashboard\DashboardOverviewResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Dashboard",
 *     description="Statistiques et données du tableau de bord"
 * )
 */
class DashboardController extends Controller
{
    public function __construct(
        private GetDashboardOverviewAction $getDashboardOverviewAction,
        private GetDashboardClientsStatsAction $getDashboardClientsStatsAction,
        private GetDashboardRevenueStatsAction $getDashboardRevenueStatsAction,
        private GetDashboardActivityAction $getDashboardActivityAction,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/overview",
     *     summary="Métriques KPI du dashboard",
     *     operationId="getDashboardOverview",
     *     tags={"Dashboard"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Données du tableau de bord récupérées avec succès")
     * )
     */
    public function overview(GetDashboardOverviewRequest $request): JsonResponse
    {
        try {
            $overview = $this->getDashboardOverviewAction->execute($request->input('date'));

            return new JsonResponse([
                'success' => true,
                'message' => 'Données du tableau de bord récupérées avec succès',
                'data' => new DashboardOverviewResource($overview),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur overview dashboard: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/clients-stats",
     *     summary="Évolution des nouveaux clients",
     *     operationId="getDashboardClientsStats",
     *     tags={"Dashboard"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="period", in="query", required=true, @OA\Schema(type="string", enum={"month", "year"})),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Statistiques clients récupérées avec succès")
     * )
     */
    public function clientsStats(GetDashboardClientsStatsRequest $request): JsonResponse
    {
        try {
            $stats = $this->getDashboardClientsStatsAction->execute(
                $request->string('period')->toString(),
                $request->input('date'),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Statistiques clients récupérées avec succès',
                'data' => new DashboardChartStatsResource($stats),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur stats clients dashboard: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/revenue-stats",
     *     summary="Évolution des recettes",
     *     operationId="getDashboardRevenueStats",
     *     tags={"Dashboard"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="period", in="query", required=true, @OA\Schema(type="string", enum={"day", "week", "month", "year"})),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Statistiques recettes récupérées avec succès")
     * )
     */
    public function revenueStats(GetDashboardRevenueStatsRequest $request): JsonResponse
    {
        try {
            $stats = $this->getDashboardRevenueStatsAction->execute(
                $request->string('period')->toString(),
                $request->input('date'),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Statistiques recettes récupérées avec succès',
                'data' => new DashboardChartStatsResource($stats),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur stats recettes dashboard: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/activity",
     *     summary="Activité récente du salon",
     *     operationId="getDashboardActivity",
     *     tags={"Dashboard"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20)),
     *
     *     @OA\Response(response=200, description="Activité récente récupérée avec succès")
     * )
     */
    public function activity(GetDashboardActivityRequest $request): JsonResponse
    {
        try {
            $activity = $this->getDashboardActivityAction->execute(
                (int) $request->input('limit', 5),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Activité récente récupérée avec succès',
                'data' => DashboardActivityResource::collection($activity),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur activité dashboard: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
