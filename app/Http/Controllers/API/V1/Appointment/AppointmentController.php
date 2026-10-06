<?php

namespace App\Http\Controllers\API\V1\Appointment;

use App\Actions\Appointment\CancelSalonAppointmentAction;
use App\Actions\Appointment\CreateSalonAppointmentAction;
use App\Actions\Appointment\GetSalonAppointmentAction;
use App\Actions\Appointment\ListSalonAppointmentsAction;
use App\Actions\Appointment\UpdateSalonAppointmentStatusAction;
use App\Data\Appointment\CreateSalonAppointmentData;
use App\Data\Appointment\UpdateSalonAppointmentStatusData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Appointment\CreateSalonAppointmentRequest;
use App\Http\Requests\V1\Appointment\GetAppointmentsRequest;
use App\Http\Requests\V1\Appointment\UpdateSalonAppointmentStatusRequest;
use App\Http\Resources\V1\Appointment\AppointmentDetailResource;
use App\Http\Resources\V1\Appointment\AppointmentResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Rendez-vous",
 *     description="Gestion des rendez-vous du salon"
 * )
 */
class AppointmentController extends Controller
{
    public function __construct(
        private ListSalonAppointmentsAction $listSalonAppointmentsAction,
        private GetSalonAppointmentAction $getSalonAppointmentAction,
        private CreateSalonAppointmentAction $createSalonAppointmentAction,
        private UpdateSalonAppointmentStatusAction $updateSalonAppointmentStatusAction,
        private CancelSalonAppointmentAction $cancelSalonAppointmentAction,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/appointments",
     *     summary="Liste des rendez-vous (jour ou semaine)",
     *     operationId="listSalonAppointments",
     *     tags={"Rendez-vous"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="date", in="query", description="Jour cible (défaut: aujourd'hui)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="from", in="query", description="Début de période", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", description="Fin de période", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="user_id", in="query", description="Filtrer par coiffeur", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Liste récupérée avec succès")
     * )
     */
    public function index(GetAppointmentsRequest $request): JsonResponse
    {
        try {
            $appointments = $this->listSalonAppointmentsAction->execute(
                $request->input('date'),
                $request->input('from'),
                $request->input('to'),
                $request->input('user_id'),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Liste des rendez-vous récupérée avec succès',
                'data' => AppointmentResource::collection($appointments),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur liste rendez-vous: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/appointments",
     *     summary="Créer un rendez-vous manuellement",
     *     operationId="createSalonAppointment",
     *     tags={"Rendez-vous"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"client_id", "user_id", "service_id", "scheduled_at"},
     *
     *             @OA\Property(property="client_id", type="string", format="uuid"),
     *             @OA\Property(property="user_id", type="string", format="uuid"),
     *             @OA\Property(property="service_id", type="string", format="uuid"),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Rendez-vous créé avec succès"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(CreateSalonAppointmentRequest $request): JsonResponse
    {
        try {
            $appointment = $this->createSalonAppointmentAction->execute(
                CreateSalonAppointmentData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Rendez-vous créé avec succès',
                'data' => new AppointmentResource($appointment->load(['client', 'staff', 'service'])),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Erreur création rendez-vous: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/appointments/{id}",
     *     summary="Détail d'un rendez-vous",
     *     operationId="getSalonAppointment",
     *     tags={"Rendez-vous"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Rendez-vous récupéré avec succès"),
     *     @OA\Response(response=404, description="Rendez-vous introuvable")
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $appointment = $this->getSalonAppointmentAction->execute($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Rendez-vous récupéré avec succès',
                'data' => new AppointmentDetailResource($appointment),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur détail rendez-vous: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/appointments/{id}/status",
     *     summary="Changer le statut d'un rendez-vous",
     *     operationId="updateSalonAppointmentStatus",
     *     tags={"Rendez-vous"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"status"},
     *
     *             @OA\Property(property="status", type="string", enum={"pending", "confirmed", "in_progress", "completed", "cancelled", "no_show"})
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Statut mis à jour avec succès"),
     *     @OA\Response(response=404, description="Rendez-vous introuvable")
     * )
     */
    public function updateStatus(UpdateSalonAppointmentStatusRequest $request, string $id): JsonResponse
    {
        try {
            $appointment = $this->updateSalonAppointmentStatusAction->execute(
                $id,
                UpdateSalonAppointmentStatusData::fromRequest($request),
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Statut du rendez-vous mis à jour avec succès',
                'data' => new AppointmentResource($appointment),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour statut rendez-vous: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/appointments/{id}",
     *     summary="Annuler un rendez-vous",
     *     operationId="cancelSalonAppointment",
     *     tags={"Rendez-vous"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="Rendez-vous annulé avec succès"),
     *     @OA\Response(response=404, description="Rendez-vous introuvable")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $appointment = $this->cancelSalonAppointmentAction->execute($id);

            return new JsonResponse([
                'success' => true,
                'message' => 'Rendez-vous annulé avec succès',
                'data' => new AppointmentResource($appointment),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur annulation rendez-vous: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
