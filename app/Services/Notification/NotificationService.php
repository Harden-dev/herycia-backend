<?php

namespace App\Services\Notification;

use App\Mail\Notification\NotificationMail;
use App\Models\Agent;
use App\Models\AppNotification;
use App\Models\Communaute;
use App\Models\CommunauteMembre;
use App\Models\DeviceToken;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\Mission;
use App\Models\Review;
use App\Models\User;
use App\Services\Firebase\FirebaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(
        private Firebase $firebaseService,
    ) {}

    /** Envoie une notification à un utilisateur (enregistrement + push optionnel). */
    public function send(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $relatedId = null,
        ?string $relatedType = null,
        bool $sendPush = true
    ): AppNotification {
        DB::beginTransaction();

        try {
            $notification = AppNotification::create([
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'user_id' => $user->id,
                'related_id' => $relatedId,
                'related_type' => $relatedType,
                'is_read' => false,
            ]);

            if ($sendPush) {
                $this->sendPushNotification($user, $title, $message, [
                    'type' => $type,
                    'notification_id' => $notification->id,
                    'related_id' => $relatedId,
                    'related_type' => $relatedType,
                ]);
            }

            DB::commit();

            return $notification;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error sending notification: ' . $e->getMessage());
            throw $e;
        }
    }

    /** Envoie la même notification à plusieurs utilisateurs. */
    public function sendToMultiple(
        iterable $users,
        string $type,
        string $title,
        string $message,
        ?string $relatedId = null,
        ?string $relatedType = null,
        bool $sendPush = true
    ): array {
        $notifications = [];

        foreach ($users as $user) {
            try {
                if (!$user instanceof User) {
                    Log::warning('NotificationService.sendToMultiple received non-User item');
                    continue;
                }
                $notifications[] = $this->send($user, $type, $title, $message, $relatedId, $relatedType, $sendPush);
            } catch (\Throwable $e) {
                $userId = $user instanceof User ? $user->id : null;
                Log::error('Error sending notification to user', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $notifications;
    }

    /** Envoie la push FCM à tous les appareils actifs de l'utilisateur et désactive les tokens invalides. */
    private function sendPushNotification(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_unique(
            DeviceToken::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('token')
                ->toArray()
        ));

        if (empty($tokens)) {
            return;
        }

        $result = $this->firebaseService->sendToTokens($tokens, $title, $body, $data);

        if (!empty($result['invalid_tokens'])) {
            DeviceToken::whereIn('token', $result['invalid_tokens'])
                ->update(['is_active' => false]);
        }
    }

    /** Notifie client et agent qu'une mission a été créée. */
    public function notifyMissionCreated(Mission $mission): void
    {
        // Idempotence : évite doublons en cas de retry du job listener
        if (AppNotification::where('related_id', $mission->id)
            ->where('related_type', 'mission')
            ->where('type', 'mission_created')
            ->exists()) {
            return;
        }

        $mission->load(['user', 'agent.user']);
        $ref = $mission->reference ?? $mission->id;

        if ($mission->user) {
            $this->send(
                $mission->user,
                'mission_created',
                'Demande de mission créée',
                "Votre demande de mission {$ref} a été créée et est en attente de confirmation par l'agent.",
                $mission->id,
                'mission',
                true
            );
        }

        if ($mission->agent && $mission->agent->user) {
            $this->send(
                $mission->agent->user,
                'mission_created',
                'Nouvelle mission assignée',
                "Une nouvelle mission {$ref} vous a été assignée. Acceptez ou refusez depuis l'application.",
                $mission->id,
                'mission',
                true
            );
        }
    }

    /** Notifie le client que l'agent a accepté la mission. */
    public function notifyMissionAccepted(Mission $mission): void
    {
        if (!$mission->user) {
            return;
        }
        $ref = $mission->reference ?? $mission->id;
        $this->send(
            $mission->user,
            'mission_accepted',
            'Mission acceptée',
            "L'agent a accepté votre mission {$ref}. La mission peut démarrer à la date prévue.",
            $mission->id,
            'mission',
            true
        );
    }

    /** Notifie le client que l'agent a démarré la mission. */
    public function notifyMissionStarted(Mission $mission): void
    {
        if (!$mission->user) {
            return;
        }
        $ref = $mission->reference ?? $mission->id;
        $this->send(
            $mission->user,
            'mission_started',
            'Mission en cours',
            "L'agent a démarré la mission {$ref}.",
            $mission->id,
            'mission',
            true
        );
    }

    /** Notifie client et agent que la mission est terminée. */
    public function notifyMissionCompleted(Mission $mission): void
    {
        $mission->load(['user', 'agent.user']);
        $ref = $mission->reference ?? $mission->id;

        if ($mission->user) {
            $this->send(
                $mission->user,
                'mission_completed',
                'Mission terminée',
                "La mission {$ref} est terminée. Vous pouvez laisser un avis à l'agent.",
                $mission->id,
                'mission',
                true
            );
        }

        if ($mission->agent && $mission->agent->user) {
            $this->send(
                $mission->agent->user,
                'mission_completed',
                'Mission terminée',
                "Vous avez marqué la mission {$ref} comme terminée.",
                $mission->id,
                'mission',
                true
            );
        }
    }

    /** Notifie l'autre partie qu'une mission a été annulée. */
    public function notifyMissionCancelled(Mission $mission, string $cancelledByUserId): void
    {
        $mission->load(['user', 'agent.user']);
        $ref = $mission->reference ?? $mission->id;
        $isClient = $mission->user_id === $cancelledByUserId;

        if ($isClient && $mission->agent && $mission->agent->user) {
            $this->send(
                $mission->agent->user,
                'mission_cancelled',
                'Mission annulée',
                "Le client a annulé la mission {$ref}.",
                $mission->id,
                'mission',
                true
            );
        }

        if (!$isClient && $mission->user) {
            $this->send(
                $mission->user,
                'mission_cancelled',
                'Mission annulée',
                "L'agent a annulé la mission {$ref}.",
                $mission->id,
                'mission',
                true
            );
        }
    }

    /** Notifie tous les membres de la communauté qu'un nouvel incident a été signalé. */
    public function notifyNewIncident(Incident $incident): void
    {
        $incident->load('communaute');
        $communaute = $incident->communaute;
        if (!$communaute) {
            return;
        }

        $users = CommunauteMembre::where('communaute_id', $communaute->id)
            ->where('status', 'approved')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->when($incident->user_id, fn ($c) => $c->reject(fn ($u) => $u->id === $incident->user_id));

        $communauteName = $communaute->name ?? 'la communauté';
        $title = 'Nouvel incident signalé';
        $message = "Un nouvel incident a été signalé dans {$communauteName}.";

        $this->sendToMultiple($users, 'incident_reported', $title, $message, $incident->id, 'incident', true);
    }

    /** Notifie l'auteur de l'incident (si non anonyme) qu'une confirmation a été ajoutée. */
    public function notifyIncidentConfirmed(Incident $incident, int $confirmationsCount): void
    {
        if (!$incident->user_id) {
            return;
        }
        $user = $incident->user;
        if (!$user) {
            return;
        }
        $this->send(
            $user,
            'incident_confirmed',
            'Incident confirmé',
            "Votre incident a été confirmé par {$confirmationsCount} personne(s).",
            $incident->id,
            'incident',
            true
        );
    }

    /** Notifie l'auteur de l'incident et les autres commentateurs (sauf l'auteur du commentaire). */
    public function notifyIncidentComment(Incident $incident, string $commentAuthorUserId): void
    {
        $userIds = IncidentComment::where('incident_id', $incident->id)
            ->pluck('user_id')
            ->unique()
            ->filter()
            ->diff(collect([$commentAuthorUserId]))
            ->values();
        if ($incident->user_id && $incident->user_id !== $commentAuthorUserId) {
            $userIds = $userIds->push($incident->user_id)->unique()->values();
        }
        $users = User::whereIn('id', $userIds)->get();
        if ($users->isEmpty()) {
            return;
        }
        $title = 'Nouveau commentaire';
        $message = 'Un nouveau commentaire a été ajouté sur un incident que vous suivez.';
        $this->sendToMultiple($users, 'incident_comment', $title, $message, $incident->id, 'incident', true);
    }

    /** Notifie l'auteur de l'incident (si non anonyme) qu'il est résolu. */
    public function notifyIncidentResolved(Incident $incident): void
    {
        if (!$incident->user_id) {
            return;
        }
        $user = $incident->user;
        if (!$user) {
            return;
        }
        $this->send(
            $user,
            'incident_resolved',
            'Incident résolu',
            'Votre incident a été marqué comme résolu.',
            $incident->id,
            'incident',
            true
        );
    }

    /** Notifie l'agent qu'il a reçu un nouvel avis. */
    public function notifyNewReview(Review $review): void
    {
        $review->load('agent.user');
        if (!$review->agent || !$review->agent->user) {
            return;
        }
        $this->send(
            $review->agent->user,
            'new_review',
            'Nouvel avis',
            "Vous avez reçu un nouvel avis ({$review->rating}/5).",
            $review->id,
            'review',
            true
        );
    }

    /** Notifie le créateur qu'un agent sera attribué à sa communauté. */
    public function notifyCommunauteCreated(Communaute $communaute, User $creator): void
    {
        $this->send(
            $creator,
            'communaute_created',
            'Communauté créée',
            "Votre communauté {$communaute->name} a été créée. Un agent sera attribué par l'administration.",
            $communaute->id,
            'communaute',
            true
        );
    }

    /** Notifie l'utilisateur que son compte agent a été approuvé. */
    public function notifyAgentApproved(Agent $agent): void
    {
        if (!$agent->user) {
            return;
        }
        $this->send(
            $agent->user,
            'agent_approved',
            'Compte agent approuvé',
            'Votre demande d\'agent a été approuvée. Vous pouvez maintenant recevoir des missions.',
            $agent->id,
            'agent',
            true
        );
    }

    /** Notifie l'utilisateur que son compte agent a été rejeté. */
    public function notifyAgentRejected(Agent $agent): void
    {
        if (!$agent->user) {
            return;
        }
        $reason = $agent->rejection_reason ? " Motif : {$agent->rejection_reason}" : '';
        $this->send(
            $agent->user,
            'agent_rejected',
            'Demande agent refusée',
            "Votre demande d'agent a été refusée.{$reason}",
            $agent->id,
            'agent',
            true
        );
    }

    /** Notifie l'agent qu'un document a été traité (approuvé ou rejeté). */
    public function notifyAgentDocumentProcessed(Agent $agent, bool $approved): void
    {
        if (!$agent->user) {
            return;
        }
        $title = $approved ? 'Document approuvé' : 'Document refusé';
        $message = $approved
            ? 'Un de vos documents a été approuvé.'
            : 'Un de vos documents a été refusé. Consultez les détails dans l\'application.';
        $this->send(
            $agent->user,
            $approved ? 'agent_document_approved' : 'agent_document_rejected',
            $title,
            $message,
            $agent->id,
            'agent',
            true
        );
    }

    /** Notifie l'utilisateur du résultat de sa vérification d'identité. */
    public function notifyIdentityVerificationProcessed(User $user, bool $approved): void
    {
        $title = $approved ? 'Identité vérifiée' : 'Vérification rejetée';
        $message = $approved
            ? 'Votre identité a été vérifiée avec succès.'
            : 'Votre demande de vérification d\'identité a été rejetée. Consultez les détails dans l\'application.';
        $this->send(
            $user,
            $approved ? 'identity_verified' : 'identity_rejected',
            $title,
            $message,
            null,
            null,
            true
        );
    }

    /** Envoie un email de notification générique (file d'attente). */
    private function queueEmail(
        User $user,
        string $subject,
        string $badge,
        string $title,
        string $message,
        ?string $ctaUrl = null,
        ?string $ctaText = null,
    ): void {
        if (empty($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->queue(new NotificationMail(
                user: $user,
                mailSubject: $subject,
                badge: $badge,
                title: $title,
                message: $message,
                ctaUrl: $ctaUrl,
                ctaText: $ctaText,
            ));
        } catch (\Throwable $e) {
            Log::error('Error queueing notification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
