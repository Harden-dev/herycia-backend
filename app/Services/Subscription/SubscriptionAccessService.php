<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;

class SubscriptionAccessService
{
    /** @var array<string, string> */
    private const PLAN_FEATURES = [
        'online_booking' => 'has_online_booking',
        'analytics' => 'has_analytics',
        'multi_branch' => 'has_multi_branch',
    ];

    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function resolveActiveSubscriptionForUser(User $user): Subscription
    {
        if ($user->salon_id === null) {
            throw new SubscriptionAccessException('Aucun salon associé à ce compte.');
        }

        $subscription = $this->subscriptionRepository->findLatestBySalonId($user->salon_id);

        if ($subscription === null) {
            throw new SubscriptionAccessException(
                'Aucun abonnement trouvé pour ce salon. Veuillez souscrire à un plan pour continuer.',
                'subscription_missing',
            );
        }

        if (! $this->isUsable($subscription)) {
            throw new SubscriptionAccessException(
                'Votre abonnement a expiré. Veuillez renouveler pour continuer à utiliser Salono.',
                'subscription_expired',
            );
        }

        return $subscription;
    }

    public function assertPlanFeature(Subscription $subscription, string $feature): void
    {
        $planColumn = self::PLAN_FEATURES[$feature] ?? null;

        if ($planColumn === null) {
            throw new \InvalidArgumentException("Fonctionnalité d'abonnement inconnue : {$feature}");
        }

        $plan = $subscription->plan;

        if ($plan === null || ! $plan->{$planColumn}) {
            throw new SubscriptionAccessException(
                'Cette fonctionnalité n\'est pas incluse dans votre plan actuel. Veuillez mettre à niveau votre abonnement.',
                'subscription_feature_denied',
            );
        }
    }

    public function isUsable(Subscription $subscription): bool
    {
        if (! in_array($subscription->status, [SubscriptionStatus::Trial, SubscriptionStatus::Active], true)) {
            return false;
        }

        if ($subscription->plan === null) {
            return false;
        }

        if ($subscription->status === SubscriptionStatus::Trial
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->endOfDay()->isPast()) {
            return false;
        }

        if ($subscription->status === SubscriptionStatus::Active
            && $subscription->ends_at !== null
            && $subscription->ends_at->endOfDay()->isPast()) {
            return false;
        }

        return true;
    }
}
