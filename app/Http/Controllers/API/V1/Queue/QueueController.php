<?php

namespace App\Http\Controllers\API\V1\Queue;

use App\Actions\Queue\ListSalonQueueAction;
use App\Enums\AppointmentStatus;
use App\Enums\SalonStaffRole;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Queue\QueueActionService;
use App\Services\Queue\QueueCheckInService;
use App\Services\Queue\QueueException;
use App\Services\Queue\QueueService;
use App\Services\Salon\SalonContextService;
use App\Support\Queue\QueuePresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        private SalonContextService $salonContext,
        private QueueService $queueService,
        private QueueCheckInService $checkInService,
        private QueueActionService $actionService,
        private QueuePresenter $presenter,
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

    /**
     * Tableau de la file du jour : par coiffeur, les clients en file (ordre et heure estimée)
     * et les rendez-vous du jour pas encore arrivés.
     */
    public function board(): JsonResponse
    {
        return $this->handle(function () {
            $salon = $this->salonContext->resolveAuthenticatedSalon();

            $stylists = User::query()
                ->where('salon_id', $salon->id)
                ->where('role', SalonStaffRole::Stylist)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $expected = Appointment::query()
                ->where('salon_id', $salon->id)
                ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
                ->whereNull('checked_in_at')
                ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
                ->with(['client', 'service'])
                ->orderBy('scheduled_at')
                ->get()
                ->groupBy('user_id');

            return [
                'late_tolerance_minutes' => $salon->late_tolerance_minutes ?? 15,
                'stylists' => $stylists->map(fn (User $stylist) => [
                    'id' => $stylist->id,
                    'name' => $stylist->name,
                    'queue' => array_map(
                        fn (array $row) => $this->presenter->boardRow($row),
                        $this->queueService->boardForStylist($salon->id, $stylist->id),
                    ),
                    'expected' => ($expected->get($stylist->id) ?? collect())
                        ->map(fn (Appointment $a) => $this->presenter->expectedAppointment($a, $salon))
                        ->values()
                        ->all(),
                ])->values()->all(),
            ];
        });
    }

    /** Enregistre l'arrivée d'un client depuis l'accueil (sans QR). */
    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate(['appointment_id' => ['required', 'uuid']]);

        return $this->handle(function () use ($data) {
            $salon = $this->salonContext->resolveAuthenticatedSalon();
            $appointment = $this->salonContext->resolveSalonAppointment($data['appointment_id'])->load(['service', 'client', 'staff']);

            return $this->presenter->checkInResult($this->checkInService->checkIn($appointment, $salon), $salon);
        });
    }

    /** Choix du retardataire saisi par l'accueil. */
    public function lateChoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'appointment_id' => ['required', 'uuid'],
            'choice' => ['required', 'string', Rule::in([QueueCheckInService::CHOICE_RESCHEDULE, QueueCheckInService::CHOICE_QUEUE])],
        ]);

        return $this->handle(function () use ($data) {
            $salon = $this->salonContext->resolveAuthenticatedSalon();
            $appointment = $this->salonContext->resolveSalonAppointment($data['appointment_id'])->load(['service', 'client', 'staff']);

            return $this->presenter->checkInResult(
                $this->checkInService->chooseLate($appointment, $salon, $data['choice']),
                $salon,
            );
        });
    }

    /** Appeler, démarrer, terminer, absent, retirer. */
    public function action(string $id, string $action): JsonResponse
    {
        return $this->handle(function () use ($id, $action) {
            $salon = $this->salonContext->resolveAuthenticatedSalon();
            $entry = $this->actionService->apply($salon->id, $id, $action);
            $estimate = $this->queueService->estimateForEntry($entry);

            return $this->presenter->boardRow([
                'entry' => $entry,
                'position' => $estimate['position'],
                'estimated_start_at' => $estimate['estimated_start_at'],
            ]);
        });
    }

    private function handle(callable $callback): JsonResponse
    {
        try {
            return new JsonResponse(['success' => true, 'data' => $callback()], Response::HTTP_OK);
        } catch (QueueException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode(),
            ], $e->statusCode());
        } catch (Exception $e) {
            Log::error('Erreur file d\'attente: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], str_contains($e->getMessage(), 'introuvable') ? Response::HTTP_NOT_FOUND : Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
