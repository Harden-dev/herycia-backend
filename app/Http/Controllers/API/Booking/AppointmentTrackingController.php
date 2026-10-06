<?php

namespace App\Http\Controllers\API\Booking;

use App\Actions\Booking\GetPublicAppointmentTrackingAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Booking\PublicAppointmentTrackingResource;
use App\Services\Booking\PublicBookingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Suivi RDV public",
 *     description="Consultation publique d'un rendez-vous via token de suivi (sans authentification)"
 * )
 */
class AppointmentTrackingController extends Controller
{
    public function __construct(
        private GetPublicAppointmentTrackingAction $getPublicAppointmentTrackingAction,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/rdv/{tracking_token}",
     *     summary="Suivi public d'un rendez-vous",
     *     description="Retourne les détails et le statut d'un rendez-vous via son token de suivi.",
     *     operationId="getPublicAppointmentTracking",
     *     tags={"Suivi RDV public"},
     *
     *     @OA\Parameter(
     *         name="tracking_token",
     *         in="path",
     *         required=true,
     *         description="Token de suivi unique du rendez-vous",
     *
     *         @OA\Schema(type="string", example="aBcDeFgHiJ")
     *     ),
     *
     *     @OA\Response(response=200, description="Rendez-vous récupéré avec succès"),
     *     @OA\Response(response=404, description="Rendez-vous introuvable")
     * )
     */
    public function show(string $trackingToken): JsonResponse
    {
        try {
            $appointment = $this->getPublicAppointmentTrackingAction->execute($trackingToken);

            return new JsonResponse([
                'success' => true,
                'message' => 'Rendez-vous récupéré avec succès',
                'data' => new PublicAppointmentTrackingResource($appointment),
            ], Response::HTTP_OK);
        } catch (PublicBookingException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
                'code' => $e->errorCode(),
            ], $e->statusCode());
        } catch (\Exception $e) {
            Log::error('Erreur suivi RDV public GET: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
