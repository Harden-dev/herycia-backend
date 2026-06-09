<?php

namespace App\Services\Booking;

use App\Models\Salon;
use App\Models\Subscription;
use App\Repositories\Contracts\SalonRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Subscription\SubscriptionAccessException;
use App\Services\Subscription\SubscriptionAccessService;

class PublicBookingAccessService
{
    public function __construct(
        private SalonRepositoryInterface $salonRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SubscriptionAccessService $subscriptionAccess,
    ) {}

    public function resolveBookableSalon(string $slug): Salon
    {
        $salon = $this->salonRepository->findBySlug($slug);

        if ($salon === null) {
            throw new PublicBookingException('Salon introuvable.', 404, 'salon_not_found');
        }

        if (! $salon->is_active) {
            throw new PublicBookingException('Ce salon n\'est pas disponible.', 403, 'salon_inactive');
        }

        $subscription = $this->subscriptionRepository->findLatestBySalonId($salon->id);

        if ($subscription === null || ! $this->subscriptionAccess->isUsable($subscription)) {
            throw new PublicBookingException(
                'Ce salon n\'a pas activé la réservation en ligne.',
                403,
                'subscription_inactive',
            );
        }

        $this->assertOnlineBookingFeature($subscription);

        return $salon;
    }

    private function assertOnlineBookingFeature(Subscription $subscription): void
    {
        try {
            $this->subscriptionAccess->assertPlanFeature($subscription, 'online_booking');
        } catch (SubscriptionAccessException) {
            throw new PublicBookingException(
                'Ce salon n\'a pas activé la réservation en ligne.',
                403,
                'booking_disabled',
            );
        }
    }

    public function resolveSubscriptionForSalon(Salon $salon): Subscription
    {
        $subscription = $this->subscriptionRepository->findLatestBySalonId($salon->id);

        if ($subscription === null) {
            throw new PublicBookingException(
                'Ce salon n\'a pas activé la réservation en ligne.',
                403,
                'subscription_inactive',
            );
        }

        return $subscription;
    }
}
