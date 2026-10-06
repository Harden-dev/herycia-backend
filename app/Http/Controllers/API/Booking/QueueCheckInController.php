<?php

namespace App\Http\Controllers\API\Booking;

use App\Http\Controllers\Controller;
use App\Models\QueueEntry;
use App\Models\Salon;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Queue\QueueCheckInService;
use App\Services\Queue\QueueException;
use App\Services\Subscription\SubscriptionAccessService;
use App\Support\Queue\QueuePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * File d'attente côté client (public) :
 * - POST /api/checkin/{slug}              « Je suis arrivé » (QR affiché au salon)
 * - POST /api/checkin/{slug}/late-choice  choix du retardataire
 * - GET  /api/file/{token}                suivi de la position
 */
class QueueCheckInController extends Controller
{
    public function __construct(
        private QueueCheckInService $checkInService,
        private QueuePresenter $presenter,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SubscriptionAccessService $subscriptionAccess,
    ) {}

    public function store(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'tracking_token' => ['nullable', 'string', 'max:64', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:tracking_token'],
        ]);

        return $this->handle(function () use ($slug, $data) {
            $salon = $this->resolveSalon($slug, $data['key']);
            $appointment = $this->checkInService->findTodayAppointment($salon, $data['tracking_token'] ?? null, $data['phone'] ?? null);

            return $this->presenter->checkInResult($this->checkInService->checkIn($appointment, $salon), $salon);
        });
    }

    public function lateChoice(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'tracking_token' => ['required', 'string', 'max:64'],
            'choice' => ['required', 'string', Rule::in([QueueCheckInService::CHOICE_RESCHEDULE, QueueCheckInService::CHOICE_QUEUE])],
        ]);

        return $this->handle(function () use ($slug, $data) {
            $salon = $this->resolveSalon($slug, $data['key']);
            $appointment = $this->checkInService->findTodayAppointment($salon, $data['tracking_token'], null);

            return $this->presenter->checkInResult(
                $this->checkInService->chooseLate($appointment, $salon, $data['choice']),
                $salon,
            );
        });
    }

    public function show(string $token): JsonResponse
    {
        return $this->handle(function () use ($token) {
            $entry = QueueEntry::query()
                ->where('tracking_token', $token)
                ->with(['client', 'service', 'staff', 'salon'])
                ->first();

            if ($entry === null) {
                throw new QueueException('Suivi introuvable.', 404, 'queue_entry_not_found');
            }

            return $this->presenter->publicEntry($entry);
        });
    }

    /** Salon actif avec abonnement valide, et clé du QR d'arrivée correcte (preuve de présence). */
    private function resolveSalon(string $slug, string $key): Salon
    {
        $salon = Salon::query()->where('slug', $slug)->first();

        if ($salon === null || ! $salon->isOperational()) {
            throw new QueueException('Salon introuvable.', 404, 'salon_not_found');
        }

        if ($salon->checkin_key === null || ! hash_equals($salon->checkin_key, $key)) {
            throw new QueueException(
                'QR code d\'arrivée invalide. Scannez le QR code affiché à l\'accueil du salon.',
                403,
                'invalid_checkin_key',
            );
        }

        $subscription = $this->subscriptionRepository->findLatestBySalonId($salon->id);

        if ($subscription === null || ! $this->subscriptionAccess->isUsable($subscription)) {
            throw new QueueException('Ce salon n\'accepte pas les enregistrements en ligne.', 403, 'subscription_inactive');
        }

        return $salon;
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
        } catch (\Exception $e) {
            Log::error('Erreur file d\'attente publique: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
