<?php

namespace App\Services\Subscription;

use App\Enums\BillingPaymentMethod;
use App\Enums\BillingPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PlanNotFoundException;
use App\Exceptions\SubscriptionAlreadyActiveException;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\SubscriptionPaymentRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionBillingService
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SubscriptionPaymentRepositoryInterface $subscriptionPaymentRepository,
    ) {}

    public function resolveTargetPlan(Subscription $subscription, ?string $planCode): Plan
    {
        if ($planCode === null) {
            return $subscription->plan;
        }

        $plan = $this->planRepository->findRegisterableByCode($planCode);

        if ($plan === null) {
            throw new PlanNotFoundException($planCode);
        }

        return $plan;
    }

    public function assertCanSubscribe(Subscription $subscription, Plan $targetPlan, ?Carbon $now = null): void
    {
        $now ??= Carbon::now();

        $isActive = $subscription->status === SubscriptionStatus::Active
            && $subscription->ends_at !== null
            && $subscription->ends_at->endOfDay()->isFuture();

        if (! $isActive) {
            return;
        }

        $isSamePlan = $subscription->plan_id === $targetPlan->id;
        $renewalWindowDays = (int) config('salono.subscription_renewal_window_days', 7);

        if ($isSamePlan) {
            $daysUntilExpiry = $now->diffInDays($subscription->ends_at->endOfDay(), false);

            if ($daysUntilExpiry > $renewalWindowDays) {
                throw new SubscriptionAlreadyActiveException(
                    $subscription->ends_at->toDateString(),
                );
            }
        }
    }

    public function activateFromPayment(
        Salon $salon,
        Subscription $subscription,
        Plan $targetPlan,
        int $amount,
        string $paymentReference,
        BillingPaymentMethod $method,
        ?Carbon $paidAt = null,
        bool $enforceSubscriptionRules = true,
    ): Subscription {
        $paidAt ??= Carbon::now();
        $now = $paidAt;

        // Un paiement déjà encaissé (Paystack) doit toujours être crédité : les règles
        // ne s'appliquent qu'avant paiement (audit H4).
        if ($enforceSubscriptionRules) {
            $this->assertCanSubscribe($subscription, $targetPlan, $now);
        }

        return DB::transaction(function () use ($salon, $subscription, $targetPlan, $amount, $paymentReference, $method, $now) {
            [$periodStart, $periodEnd] = $this->resolveBillingPeriod($subscription, $targetPlan, $now);

            $updated = $this->subscriptionRepository->update($subscription, [
                'plan_id' => $targetPlan->id,
                'status' => SubscriptionStatus::Active,
                'is_trial' => false,
                'started_at' => $subscription->started_at ?? $now->toDateString(),
                'ends_at' => $periodEnd->toDateString(),
                'trial_ends_at' => null,
            ]);

            $this->subscriptionPaymentRepository->create([
                'salon_id' => $salon->id,
                'subscription_id' => $updated->id,
                'plan_id' => $targetPlan->id,
                'amount' => $amount,
                'method' => $method,
                'status' => BillingPaymentStatus::Paid,
                'reference' => $paymentReference,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'paid_at' => $now,
            ]);

            return $updated->loadMissing('plan');
        });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveBillingPeriod(Subscription $subscription, Plan $targetPlan, Carbon $now): array
    {
        $isPlanChange = $subscription->plan_id !== $targetPlan->id;
        $isActive = $subscription->status === SubscriptionStatus::Active
            && $subscription->ends_at?->isFuture();

        if ($isPlanChange) {
            $periodStart = $now->copy()->startOfDay();

            return [$periodStart, $periodStart->copy()->addMonth()->subDay()];
        }

        if ($isActive && $subscription->ends_at !== null) {
            $periodStart = $subscription->ends_at->copy()->addDay()->startOfDay();

            return [$periodStart, $periodStart->copy()->addMonth()->subDay()];
        }

        $periodStart = $now->copy()->startOfDay();

        return [$periodStart, $periodStart->copy()->addMonth()->subDay()];
    }
}
