<?php

namespace App\Http\Controllers\API\Booking;

use App\Actions\Booking\CreatePublicBookingAction;
use App\Actions\Booking\GetPublicSalonBookingAction;
use App\Data\Booking\CreatePublicBookingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CreatePublicBookingRequest;
use App\Http\Resources\Booking\PublicBookingAppointmentResource;
use App\Http\Resources\Booking\PublicSalonBookingResource;
use App\Services\Booking\PublicBookingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Réservation publique",
 *     description="Réservation en ligne via page publique Vue.js (sans authentification)"
 * )
 */
class BookingController extends Controller
{
    public function __construct(
        private GetPublicSalonBookingAction $getPublicSalonBookingAction,
        private CreatePublicBookingAction $createPublicBookingAction,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/booking/{slug}",
     *     summary="Informations publiques du salon pour réservation",
     *     description="Retourne le salon, ses services actifs et ses coiffeurs (stylists). Requiert un abonnement actif avec réservation en ligne.",
     *     operationId="getPublicSalonBooking",
     *     tags={"Réservation publique"},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Slug unique du salon",
     *
     *         @OA\Schema(type="string", example="salon-koffi-cocody")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Informations récupérées avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Informations de réservation récupérées avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="salon",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="name", type="string", example="Salon Koffi Cocody"),
     *                     @OA\Property(property="slug", type="string", example="salon-koffi-cocody"),
     *                     @OA\Property(property="city", type="string", nullable=true),
     *                     @OA\Property(property="logo_url", type="string", nullable=true),
     *                     @OA\Property(property="whatsapp_number", type="string", example="2250101020304"),
     *                     @OA\Property(property="booking_link", type="string", example="https://salono.ci/booking/salon-koffi-cocody")
     *                 ),
     *                 @OA\Property(
     *                     property="services",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="id", type="string", format="uuid"),
     *                         @OA\Property(property="name", type="string", example="Coupe homme"),
     *                         @OA\Property(property="duration_min", type="integer", example=30),
     *                         @OA\Property(property="price", type="integer", example=2000)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="employees",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="id", type="string", format="uuid"),
     *                         @OA\Property(property="name", type="string", example="Koffi")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Salon inactif ou réservation en ligne non activée"),
     *     @OA\Response(response=404, description="Salon introuvable")
     * )
     */
    public function show(string $slug): JsonResponse
    {
        try {
            $data = $this->getPublicSalonBookingAction->execute($slug);

            return new JsonResponse([
                'success' => true,
                'message' => 'Informations de réservation récupérées avec succès',
                'data' => new PublicSalonBookingResource($data),
            ], Response::HTTP_OK);
        } catch (PublicBookingException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode(),
            ], $e->statusCode());
        } catch (\Exception $e) {
            Log::error('Erreur booking public GET: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/booking/{slug}",
     *     summary="Créer un rendez-vous via réservation publique",
     *     description="Crée un client si nécessaire et un RDV au statut confirmed. Utilisé par la page publique Vue.js.",
     *     operationId="createPublicBooking",
     *     tags={"Réservation publique"},
     *
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Slug unique du salon",
     *
     *         @OA\Schema(type="string", example="salon-koffi-cocody")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"client_phone", "client_name", "service_id", "user_id", "date", "time"},
     *
     *             @OA\Property(property="client_phone", type="string", example="2250708112233"),
     *             @OA\Property(property="client_name", type="string", example="Koffi Client"),
     *             @OA\Property(property="service_id", type="string", format="uuid"),
     *             @OA\Property(property="user_id", type="string", format="uuid", description="ID du coiffeur (stylist)"),
     *             @OA\Property(property="date", type="string", format="date", example="2026-05-24", description="Date du RDV (AAAA-MM-JJ)"),
     *             @OA\Property(property="time", type="string", example="10:00", description="Heure du RDV (HH:MM)"),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true, deprecated=true, description="Alternative : datetime ISO (si date/time absents)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Rendez-vous créé avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rendez-vous créé avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *                 @OA\Property(property="status", type="string", example="confirmed"),
     *                 @OA\Property(
     *                     property="service",
     *                     type="object",
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="duration_min", type="integer"),
     *                     @OA\Property(property="price", type="integer")
     *                 ),
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     @OA\Property(property="name", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="client",
     *                     type="object",
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="phone", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Salon inactif ou réservation en ligne non activée"),
     *     @OA\Response(response=404, description="Salon introuvable"),
     *     @OA\Response(response=409, description="Créneau non disponible"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(CreatePublicBookingRequest $request, string $slug): JsonResponse
    {
        try {
            $appointment = $this->createPublicBookingAction->execute(
                $slug,
                CreatePublicBookingData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Rendez-vous créé avec succès',
                'data' => new PublicBookingAppointmentResource($appointment),
            ], Response::HTTP_CREATED);
        } catch (PublicBookingException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode(),
            ], $e->statusCode());
        } catch (\Exception $e) {
            Log::error('Erreur booking public POST: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
