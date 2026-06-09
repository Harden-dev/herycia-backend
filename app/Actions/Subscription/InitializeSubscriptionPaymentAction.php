<?php

namespace App\Actions\Subscription;

use App\Data\Paystack\PaystackInitializeResult;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Auth\AuthService;
use App\Services\Paystack\PaystackService;
use App\Services\Salon\SalonContextService;
use App\Services\Subscription\SubscriptionBillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitializeSubscriptionPaymentAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PaymentTransactionRepositoryInterface $paymentTransactionRepository,
        private SubscriptionBillingService $subscriptionBillingService,
        private PaystackService $paystackService,
        private AuthService $authService,
    ) {}

    /**
     * @return array{transaction: PaymentTransaction, paystack: PaystackInitializeResult}
     */
    public function execute(string $planCode): array
    {
        $user = $this->authService->getAuthenticatedUser();
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $subscription = $this->subscriptionRepository->findCurrentBySalonId($salon->id);

        if ($subscription === null || $subscription->plan === null) {
            throw new \RuntimeException('Aucun abonnement trouvé pour ce salon.');
        }

        $targetPlan = $this->subscriptionBillingService->resolveTargetPlan($subscription, $planCode);
        $this->subscriptionBillingService->assertCanSubscribe($subscription, $targetPlan);

        $reference = 'SAL-'.Str::upper(Str::random(16));

        return DB::transaction(function () use ($salon, $targetPlan, $reference, $user) {
            $transaction = $this->paymentTransactionRepository->create([
                'salon_id' => $salon->id,
                'plan_id' => $targetPlan->id,
                'amount' => $targetPlan->price_fcfa,
                'currency' => 'XOF',
                'reference' => $reference,
                'status' => PaymentTransactionStatus::Pending,
            ]);

            $paystack = $this->paystackService->initialize(
                email: $this->resolvePayerEmail($user),
                amount: $targetPlan->price_fcfa,
                reference: $reference,
                callbackUrl: $this->callbackUrl(),
                metadata: [
                    'salon_id' => $salon->id,
                    'plan_id' => $targetPlan->id,
                    'plan_code' => $targetPlan->code,
                    'payment_transaction_id' => $transaction->id,
                ],
            );

            $transaction = $this->paymentTransactionRepository->update($transaction, [
                'paystack_ref' => $paystack->reference,
            ]);

            return [
                'transaction' => $transaction->loadMissing('plan'),
                'paystack' => $paystack,
            ];
        });
    }

    private function resolvePayerEmail(User $user): string
    {
        if (is_string($user->email) && $user->email !== '') {
            return $user->email;
        }

        return config('paystack.merchant_email');
    }

    private function callbackUrl(): string
    {
        return rtrim(config('app.url'), '/').config('paystack.callback_path');
    }
}
