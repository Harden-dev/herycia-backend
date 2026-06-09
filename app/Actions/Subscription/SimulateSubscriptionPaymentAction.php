<?php

namespace App\Actions\Subscription;

use App\Enums\BillingPaymentMethod;
use App\Exceptions\PlanNotFoundException;
use App\Exceptions\SubscriptionAlreadyActiveException;
use App\Models\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Salon\SalonContextService;
use App\Services\Subscription\SubscriptionBillingService;
use Illuminate\Support\Str;

class SimulateSubscriptionPaymentAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SubscriptionBillingService $subscriptionBillingService,
    ) {}

    public function execute(BillingPaymentMethod $method, ?string $planCode = null): Subscription
    {
        if (! config('salono.simulate_subscription_payments', true)) {
            throw new \RuntimeException('La simulation de paiement est désactivée.');
        }

        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $subscription = $this->subscriptionRepository->findCurrentBySalonId($salon->id);

        if ($subscription === null || $subscription->plan === null) {
            throw new \RuntimeException('Aucun abonnement trouvé pour ce salon.');
        }

        $targetPlan = $this->subscriptionBillingService->resolveTargetPlan($subscription, $planCode);

        return $this->subscriptionBillingService->activateFromPayment(
            salon: $salon,
            subscription: $subscription,
            targetPlan: $targetPlan,
            amount: $targetPlan->price_fcfa,
            paymentReference: 'SIM-'.Str::upper(Str::random(10)),
            method: $method,
        );
    }
}
