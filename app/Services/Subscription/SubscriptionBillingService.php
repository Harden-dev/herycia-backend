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
    /** Résultats de revokeRefundedPayment (alerte « période retirée »). */
    public const REVOKE_RESTORED = 'restored';

    public const REVOKE_EXPIRED = 'expired';

    public const REVOKE_UNCHANGED = 'unchanged';

    /** Période ajustée, statut posé par l'administration (annulé…) conservé (pentest P4). */
    public const REVOKE_STATUS_KEPT = 'status_kept';

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

        // Un paiement déjà encaissé (GeniusPay) doit toujours être crédité : les règles
        // ne s'appliquent qu'avant paiement (audit H4).
        if ($enforceSubscriptionRules) {
            $this->assertCanSubscribe($subscription, $targetPlan, $now);
        }

        return DB::transaction(function () use ($salon, $subscription, $targetPlan, $amount, $paymentReference, $method, $now) {
            [$periodStart, $periodEnd] = $this->resolveBillingPeriod($subscription, $targetPlan, $now);

            // État avant paiement : restauré si ce paiement est remboursé (pentest M2).
            $previous = [
                'previous_plan_id' => $subscription->plan_id,
                'previous_ends_at' => $subscription->ends_at?->toDateString(),
                'previous_status' => $subscription->status,
            ];

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
            ] + $previous);

            return $updated->loadMissing('plan');
        });
    }

    /**
     * Remboursement d'un paiement déjà crédité : le paiement d'abonnement passe en « refunded »,
     * puis l'état de l'abonnement est recalculé à partir des paiements encore payés (contre-audit
     * N1/N2), jamais par restauration en chaîne d'un état lui-même payé par un paiement remboursé :
     * - le paiement n'était pas le dernier activé (created_at puis id), ou l'abonnement a été
     *   ajusté depuis : abonnement inchangé, ajustement manuel (REVOKE_UNCHANGED) ;
     * - un paiement payé plus ancien subsiste : son plan et sa fin de période (REVOKE_RESTORED) ;
     * - aucun autre paiement n'a jamais existé : l'état d'avant ce paiement, essai ou abonnement
     *   non facturé (REVOKE_RESTORED), ou inchangé s'il n'a pas été enregistré ;
     * - sinon (tous les autres paiements sont remboursés) : abonnement expiré (REVOKE_EXPIRED).
     * Un statut posé par l'administration (annulé, impayé, essai) n'est jamais modifié : seuls le plan
     * et la fin de période sont ajustés (REVOKE_STATUS_KEPT, pentest P4).
     *
     * @return self::REVOKE_*
     */
    public function revokeRefundedPayment(string $paymentReference, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();

        return DB::transaction(function () use ($paymentReference, $now): string {
            $payment = $this->subscriptionPaymentRepository->findByReference($paymentReference);

            if ($payment === null) {
                return self::REVOKE_UNCHANGED;
            }

            $subscription = $this->subscriptionRepository->findByIdForUpdate($payment->subscription_id);
            $latestPaid = $this->subscriptionPaymentRepository->findLatestPaidForSubscription($payment->subscription_id);

            $this->subscriptionPaymentRepository->update($payment, ['status' => BillingPaymentStatus::Refunded]);

            if ($subscription?->ends_at === null
                || $latestPaid?->id !== $payment->id
                || ! $subscription->ends_at->isSameDay($payment->period_end)) {
                return self::REVOKE_UNCHANGED;
            }

            $remaining = $this->subscriptionPaymentRepository->findLatestPaidForSubscription($payment->subscription_id);

            if ($remaining !== null) {
                return $this->applyPeriod($subscription, $remaining->plan_id, $remaining->period_end, true, $now)
                    ?? self::REVOKE_RESTORED;
            }

            if ($this->subscriptionPaymentRepository->existsOtherForSubscription($payment->subscription_id, $payment->id)) {
                return $this->applyPeriod($subscription, $payment->previous_plan_id ?? $subscription->plan_id, $now->copy()->subDay(), false, $now)
                    ?? self::REVOKE_EXPIRED;
            }

            if ($payment->previous_plan_id === null) {
                return self::REVOKE_UNCHANGED;
            }

            return $this->applyPeriod(
                $subscription,
                $payment->previous_plan_id,
                $payment->previous_ends_at ?? $payment->period_start->copy()->subDay(),
                $payment->previous_status === SubscriptionStatus::Active,
                $now,
            ) ?? self::REVOKE_RESTORED;
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

    /**
     * Actif seulement si la période court encore, que l'état visé était actif et que l'abonnement
     * est actif à cet instant : un remboursement ne réactive jamais un abonnement.
     * Statut autre qu'actif ou expiré (annulé, impayé, essai : posé par l'administration) : conservé,
     * seuls le plan et la fin de période changent ; renvoie alors REVOKE_STATUS_KEPT pour l'alerte.
     */
    private function applyPeriod(Subscription $subscription, string $planId, Carbon $endsAt, bool $wasActive, Carbon $now): ?string
    {
        $attributes = [
            'plan_id' => $planId,
            'ends_at' => $endsAt->toDateString(),
        ];
        $current = $subscription->status;
        $keepStatus = ! in_array($current, [SubscriptionStatus::Active, SubscriptionStatus::Expired], true);

        if (! $keepStatus) {
            $isActive = $current === SubscriptionStatus::Active
                && $wasActive
                && $endsAt->copy()->endOfDay()->gte($now);
            $attributes['status'] = $isActive ? SubscriptionStatus::Active : SubscriptionStatus::Expired;
        }

        $this->subscriptionRepository->update($subscription, $attributes);

        return $keepStatus ? self::REVOKE_STATUS_KEPT : null;
    }
}
