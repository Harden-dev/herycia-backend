<?php

namespace App\Listeners;

use App\Events\SalonRegistered;
use App\Services\PublicLinkService;
use App\Services\Sms\TwilioSmsService;
use App\Support\IvoryCoastPhone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendSalonWelcomeSms implements ShouldQueue
{
    public function __construct(
        private PublicLinkService $linkService,
        private TwilioSmsService $smsService,
    ) {}

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

        $this->smsService->send(
            '+'.$phone,
            "Bonjour {$event->user->name} !\n\n".
            "Votre salon {$event->salon->name} est prêt sur Salono.\n\n".
            "Partagez ce lien à vos clients pour qu'ils réservent en ligne :\n{$bookingLink}\n\n".
            'Bonne continuation !',
        );
    }
}
