<?php

namespace App\Listeners;

use App\Events\SalonRegistered;
use App\Jobs\Sms\Concerns\SendsSmsOnce;
use App\Services\PublicLinkService;
use App\Support\IvoryCoastPhone;
use App\Support\SmsQuota;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Traité en file après le commit de la transaction qui émet l'événement (jamais sur rollback).
 *
 * Le numéro du gérant n'est pas vérifié (inscription ouverte, OTP retiré — audit C2) : le texte
 * est donc fixe, sans aucun champ saisi (pentest P1), au plus un SMS par numéro, et un plafond
 * global horaire limite l'usage de l'inscription comme relais de SMS.
 */
class SendSalonWelcomeSms implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    use InteractsWithQueue;
    use SendsSmsOnce;

    private const ONCE_PER_NUMBER_TTL_SECONDS = 30 * 86400;

    public int $timeout = 30;

    public function __construct(
        private PublicLinkService $linkService,
    ) {}

    /**
     * Évalué à la mise en file, pas aux réessais. Une seule mise en file par numéro, même si le
     * listener est déclenché plusieurs fois pour le même événement : le plafond global n'est
     * décompté qu'une fois par numéro.
     */
    public function shouldQueue(SalonRegistered $event): bool
    {
        $phone = IvoryCoastPhone::normalize((string) $event->user->phone);

        return IvoryCoastPhone::isValid($phone)
            && Cache::add('sms:welcome-queued:'.$phone, true, self::ONCE_PER_NUMBER_TTL_SECONDS)
            && SmsQuota::consumeWelcome();
    }

    public function handle(SalonRegistered $event): void
    {
        $phone = IvoryCoastPhone::normalize((string) $event->user->phone);

        if (! IvoryCoastPhone::isValid($phone)) {
            Log::debug('Welcome SMS skipped: invalid user phone', [
                'salon_id' => $event->salon->id,
            ]);

            return;
        }

        $bookingLink = $this->linkService->buildBookingLink($event->salon->slug);

        // Clé par numéro : jamais deux SMS de bienvenue au même numéro, quel que soit le salon.
        $this->sendSmsOnce(
            'salon-welcome:'.$phone,
            null,
            '+'.$phone,
            "Bienvenue sur Salono ! Votre salon est prêt.\n\n".
            "Partagez ce lien à vos clients pour qu'ils réservent en ligne :\n{$bookingLink}\n\n".
            'Bonne continuation !',
        );
    }
}
