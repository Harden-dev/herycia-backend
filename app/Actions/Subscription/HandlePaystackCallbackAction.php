<?php

namespace App\Actions\Subscription;

use App\Enums\BillingPaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\PaystackException;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Support\Paystack\PaystackAmount;
use App\Services\Paystack\PaystackService;
use App\Services\Subscription\SubscriptionBillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            Log::warning('Paystack callback: transaction introuvable.', ['reference' => $reference]);

            return false;
        }

        if ($transaction->status === PaymentTransactionStatus::Success) {
            return true;
        }

        try {
            $verified = $this->paystackService->verify($reference);
        } catch (PaystackException $e) {
            Log::error('Paystack verify failed: '.$e->getMessage(), ['reference' => $reference]);
            $this->paymentTransactionRepository->update($transaction, [
                'status' => PaymentTransactionStatus::Failed,
            ]);

            return false;
        }

        if (! $verified->success) {
            $this->paymentTransactionRepository->update($transaction, [
                'status' => PaymentTransactionStatus::Failed,
            ]);

            return false;
        }

        $expectedPaystackAmount = PaystackAmount::toPaystack($transaction->amount);

        if ($verified->amount !== $expectedPaystackAmount || strtoupper($verified->currency) !== strtoupper($transaction->currency)) {
            Log::error('Paystack callback: montant/devise invalide.', [
                'reference' => $reference,
                'expected_amount_fcfa' => $transaction->amount,
                'expected_paystack_amount' => $expectedPaystackAmount,
                'received_paystack_amount' => $verified->amount,
            ]);
            $this->paymentTransactionRepository->update($transaction, [
                'status' => PaymentTransactionStatus::Failed,
            ]);

            return false;
        }

        $subscription = $this->subscriptionRepository->findCurrentBySalonId($transaction->salon_id);

        if ($subscription === null || $transaction->plan === null || $transaction->salon === null) {
            $this->paymentTransactionRepository->update($transaction, [
                'status' => PaymentTransactionStatus::Failed,
            ]);

            return false;
        }

        try {
            DB::transaction(function () use ($transaction, $subscription, $verified) {
                $paidAt = $verified->paidAt !== null
                    ? Carbon::parse($verified->paidAt)
                    : Carbon::now();

                $this->subscriptionBillingService->activateFromPayment(
                    salon: $transaction->salon,
                    subscription: $subscription,
                    targetPlan: $transaction->plan,
                    amount: $transaction->amount,
                    paymentReference: $transaction->reference,
                    method: BillingPaymentMethod::fromPaystackChannel($verified->channel),
                    paidAt: $paidAt,
                );

                $this->paymentTransactionRepository->update($transaction, [
                    'status' => PaymentTransactionStatus::Success,
                    'paid_at' => $paidAt,
                    'paystack_ref' => $verified->reference,
                ]);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Paystack callback activation failed: '.$e->getMessage(), [
                'reference' => $reference,
            ]);
            $this->paymentTransactionRepository->update($transaction, [
                'status' => PaymentTransactionStatus::Failed,
            ]);

            return false;
        }
    }
}
