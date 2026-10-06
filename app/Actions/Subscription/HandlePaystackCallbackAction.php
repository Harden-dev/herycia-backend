<?php

namespace App\Actions\Subscription;

use App\Enums\BillingPaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Paystack\PaystackService;
use App\Services\Subscription\SubscriptionBillingService;
use App\Support\Paystack\PaystackAmount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Confirme un paiement d'abonnement Paystack (callback navigateur et webhook signé).
 *
 * Garanties (audit H4) :
 * - idempotent : la transaction est verrouillée, une référence n'active l'abonnement qu'une fois ;
 * - un paiement vérifié chez Paystack est toujours crédité, même si les règles d'abonnement
 *   ont changé entre l'initialisation et le paiement ;
 * - une erreur réseau ou un paiement non terminé laisse la transaction en attente
 *   (elle pourra être confirmée par le webhook ou un nouvel appel) au lieu de la marquer échouée.
 */
class HandlePaystackCallbackAction
{
    public function __construct(
        private PaymentTransactionRepositoryInterface $paymentTransactionRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PaystackService $paystackService,
        private SubscriptionBillingService $subscriptionBillingService,
    ) {}

    public function execute(?string $reference): bool
    {
        if ($reference === null || $reference === '') {
            return false;
        }

        $transaction = $this->paymentTransactionRepository->findByReference($reference);

        if ($transaction === null) {
            Log::warning('Paystack: transaction introuvable.', ['reference' => $reference]);

            return false;
        }

        if ($transaction->status === PaymentTransactionStatus::Success) {
            return true;
        }

        try {
            $verified = $this->paystackService->verify($reference);
        } catch (\Throwable $e) {
            // Erreur de vérification (réseau, API) : la transaction reste en attente.
            Log::error('Paystack verify failed: '.$e->getMessage(), ['reference' => $reference]);

            return false;
        }

        if (! $verified->success) {
            if ($verified->isDefinitiveFailure()) {
                $this->markFailed($transaction);
            }

            return false;
        }

        $expectedPaystackAmount = PaystackAmount::toPaystack($transaction->amount);

        if ($verified->amount !== $expectedPaystackAmount
            || strtoupper($verified->currency) !== strtoupper($transaction->currency)) {
            Log::critical('Paystack: montant/devise invalide.', [
                'reference' => $reference,
                'expected_amount_fcfa' => $transaction->amount,
                'expected_paystack_amount' => $expectedPaystackAmount,
                'received_paystack_amount' => $verified->amount,
                'received_currency' => $verified->currency,
            ]);
            $this->markFailed($transaction);

            return false;
        }

        try {
            return DB::transaction(function () use ($transaction, $verified, $reference): bool {
                /** @var PaymentTransaction|null $locked */
                $locked = PaymentTransaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    return false;
                }

                // Un appel concurrent (callback + webhook, double clic) a déjà activé l'abonnement.
                if ($locked->status === PaymentTransactionStatus::Success) {
                    return true;
                }

                $subscription = $this->subscriptionRepository->findCurrentBySalonId($locked->salon_id);

                if ($subscription === null || $locked->plan === null || $locked->salon === null) {
                    Log::critical('Paystack: paiement vérifié mais abonnement/plan/salon introuvable.', [
                        'reference' => $reference,
                        'transaction_id' => $locked->id,
                    ]);

                    return false;
                }

                $paidAt = $verified->paidAt !== null
                    ? Carbon::parse($verified->paidAt)
                    : Carbon::now();

                $this->subscriptionBillingService->activateFromPayment(
                    salon: $locked->salon,
                    subscription: $subscription,
                    targetPlan: $locked->plan,
                    amount: $locked->amount,
                    paymentReference: $locked->reference,
                    method: BillingPaymentMethod::fromPaystackChannel($verified->channel),
                    paidAt: $paidAt,
                    enforceSubscriptionRules: false,
                );

                $this->paymentTransactionRepository->update($locked, [
                    'status' => PaymentTransactionStatus::Success,
                    'paid_at' => $paidAt,
                    'paystack_ref' => $verified->reference,
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            // Paiement encaissé mais activation impossible : on garde la transaction en attente
            // pour une nouvelle tentative et on alerte (ne jamais perdre un paiement).
            Log::critical('Paystack: activation après paiement échouée: '.$e->getMessage(), [
                'reference' => $reference,
            ]);

            return false;
        }
    }

    private function markFailed(PaymentTransaction $transaction): void
    {
        if ($transaction->status === PaymentTransactionStatus::Pending) {
            $this->paymentTransactionRepository->update($transaction, [
                'status' => PaymentTransactionStatus::Failed,
            ]);
        }
    }
}
