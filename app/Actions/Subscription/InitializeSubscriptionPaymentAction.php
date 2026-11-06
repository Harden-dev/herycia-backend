<?php

namespace App\Actions\Subscription;

use App\Data\GeniusPay\GeniusPayPaymentResult;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyKeyMismatchException;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\User;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Auth\AuthService;
use App\Services\GeniusPay\GeniusPayService;
use App\Services\Salon\SalonContextService;
use App\Services\Subscription\SubscriptionBillingService;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InitializeSubscriptionPaymentAction
{
    /** Durée de vie du verrou : couvre l'appel GeniusPay (timeout HTTP 20 s) avec marge. */
    private const LOCK_TTL_SECONDS = 60;

    /** Attente maximale d'une requête concurrente portant la même clé. */
    private const LOCK_WAIT_SECONDS = 10;

    /** Liens de paiement en attente non expirés tolérés par salon (pentest M3). */
    private const MAX_PENDING_CHECKOUTS = 3;

    public function __construct(
        private SalonContextService $salonContext,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PaymentTransactionRepositoryInterface $paymentTransactionRepository,
        private SubscriptionBillingService $subscriptionBillingService,
        private GeniusPayService $geniusPayService,
        private AuthService $authService,
    ) {}

    /**
     * Initialise un paiement GeniusPay de façon idempotente par (salon, clé) : GeniusPay
     * n'ayant pas de clé d'idempotence, un rejeu renvoie le lien déjà créé sans le rappeler.
     *
     * @return array{transaction: PaymentTransaction, payment: GeniusPayPaymentResult, replayed: bool}
     *
     * @throws IdempotencyKeyMismatchException
     * @throws IdempotencyConflictException
     */
    public function execute(string $planCode, string $idempotencyKey): array
    {
        $user = $this->authService->getAuthenticatedUser();
        $salon = $this->salonContext->resolveAuthenticatedSalon();

        $lock = Cache::lock('subscription-initialize:'.$salon->id.':'.$idempotencyKey, self::LOCK_TTL_SECONDS);

        try {
            return $lock->block(
                self::LOCK_WAIT_SECONDS,
                fn () => $this->initializeOnce($user, $salon, $planCode, $idempotencyKey),
            );
        } catch (LockTimeoutException) {
            throw IdempotencyConflictException::inProgress();
        }
    }

    /**
     * @return array{transaction: PaymentTransaction, payment: GeniusPayPaymentResult, replayed: bool}
     */
    private function initializeOnce(User $user, Salon $salon, string $planCode, string $idempotencyKey): array
    {
        // Avant les contrôles d'abonnement : une clé déjà payée doit répondre 409, pas « déjà actif ».
        $existing = $this->paymentTransactionRepository->findBySalonAndIdempotencyKey($salon->id, $idempotencyKey);

        if ($existing !== null) {
            $this->assertSamePlan($existing, $planCode);

            if (! $this->isRetryable($existing)) {
                return $this->replay($existing);
            }
        }

        $subscription = $this->subscriptionRepository->findCurrentBySalonId($salon->id);

        if ($subscription === null || $subscription->plan === null) {
            throw new \RuntimeException('Aucun abonnement trouvé pour ce salon.');
        }

        $targetPlan = $this->subscriptionBillingService->resolveTargetPlan($subscription, $planCode);
        $this->subscriptionBillingService->assertCanSubscribe($subscription, $targetPlan);

        $openedAfter = Carbon::now()->subMinutes($this->checkoutTtlMinutes());

        if ($this->paymentTransactionRepository->countOpenCheckoutsForSalon($salon->id, $openedAfter) >= self::MAX_PENDING_CHECKOUTS) {
            throw IdempotencyConflictException::tooManyPending();
        }

        $attributes = [
            'plan_id' => $targetPlan->id,
            'amount' => $targetPlan->price_fcfa,
            'currency' => 'XOF',
            // Référence interne : connue avant l'appel GeniusPay, elle est portée par les URLs de retour
            // (GeniusPay génère sa propre référence MTX-…, stockée dans gateway_ref). Nouvelle à chaque
            // tentative : un appel précédent a pu aboutir chez GeniusPay sans réponse exploitable.
            'reference' => 'SAL-'.Str::upper(Str::random(16)),
            'status' => PaymentTransactionStatus::Pending,
        ];

        if ($existing !== null) {
            // Tentative précédente échouée avant création du paiement : la même clé est réessayée.
            $transaction = $this->paymentTransactionRepository->update($existing, $attributes);

            return $this->requestCheckout($user, $salon, $targetPlan, $transaction);
        }

        try {
            // Ligne committée avant l'appel GeniusPay : aucune transaction BDD ouverte pendant l'appel HTTP.
            $transaction = $this->paymentTransactionRepository->create($attributes + [
                'salon_id' => $salon->id,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Filet si le verrou n'a pas protégé (cache sans verrou partagé) : l'index unique
            // (salon_id, idempotency_key) a bloqué le doublon, on rejoue la transaction gagnante.
            $existing = $this->paymentTransactionRepository->findBySalonAndIdempotencyKey($salon->id, $idempotencyKey);

            if ($existing === null) {
                throw $e;
            }

            $this->assertSamePlan($existing, $planCode);

            return $this->replay($existing);
        }

        return $this->requestCheckout($user, $salon, $targetPlan, $transaction);
    }

    /**
     * @return array{transaction: PaymentTransaction, payment: GeniusPayPaymentResult, replayed: bool}
     */
    private function requestCheckout(User $user, Salon $salon, Plan $targetPlan, PaymentTransaction $transaction): array
    {
        $returnUrl = $this->callbackUrl($transaction->reference);

        try {
            $payment = $this->geniusPayService->createPayment(
                amount: $transaction->amount,
                description: $this->description($salon, $targetPlan),
                successUrl: $returnUrl,
                errorUrl: $returnUrl,
                customer: $this->customer($user),
                metadata: [
                    'reference' => $transaction->reference,
                    'salon_id' => $salon->id,
                    'plan_id' => $targetPlan->id,
                    'plan_code' => $targetPlan->code,
                    'payment_transaction_id' => $transaction->id,
                ],
            );
        } catch (\Throwable $e) {
            // Échec sans paiement créé (pas de gateway_ref) : la ligne est conservée pour l'audit
            // et la même clé d'idempotence pourra être réessayée.
            $this->paymentTransactionRepository->update($transaction, ['status' => PaymentTransactionStatus::Failed]);

            throw $e;
        }

        $expiresAt = $this->parseExpiry($payment->expiresAt)
            ?? Carbon::now()->addMinutes($this->checkoutTtlMinutes());

        $transaction = $this->paymentTransactionRepository->update($transaction, [
            'gateway_ref' => $payment->reference,
            'checkout_url' => $payment->checkoutUrl,
            'checkout_expires_at' => $expiresAt,
        ]);

        return [
            'transaction' => $transaction->loadMissing('plan'),
            'payment' => new GeniusPayPaymentResult(
                checkoutUrl: $payment->checkoutUrl,
                reference: $payment->reference,
                expiresAt: $expiresAt->toIso8601String(),
            ),
            'replayed' => false,
        ];
    }

    private function assertSamePlan(PaymentTransaction $transaction, string $planCode): void
    {
        if ($transaction->plan?->code !== $planCode) {
            throw new IdempotencyKeyMismatchException;
        }
    }

    /** Tentative échouée avant la création du paiement chez GeniusPay. */
    private function isRetryable(PaymentTransaction $transaction): bool
    {
        return $transaction->status === PaymentTransactionStatus::Failed && $transaction->gateway_ref === null;
    }

    /**
     * @return array{transaction: PaymentTransaction, payment: GeniusPayPaymentResult, replayed: bool}
     */
    private function replay(PaymentTransaction $transaction): array
    {
        // Payé, ou encaissé et en vérification manuelle : ne pas laisser payer une seconde fois.
        if (in_array($transaction->status, [PaymentTransactionStatus::Success, PaymentTransactionStatus::NeedsReview], true)) {
            throw IdempotencyConflictException::alreadyPaid();
        }

        // Ligne créée mais lien pas encore enregistré : une requête concurrente est en cours.
        if ($transaction->status === PaymentTransactionStatus::Pending && $transaction->gateway_ref === null) {
            throw IdempotencyConflictException::inProgress();
        }

        if ($transaction->status !== PaymentTransactionStatus::Pending || ! $this->isCheckoutReusable($transaction)) {
            throw IdempotencyConflictException::keyExpired();
        }

        return [
            'transaction' => $transaction,
            'payment' => new GeniusPayPaymentResult(
                checkoutUrl: $transaction->checkout_url,
                reference: $transaction->gateway_ref,
                expiresAt: $transaction->checkout_expires_at?->toIso8601String(),
            ),
            'replayed' => true,
        ];
    }

    /**
     * Un lien n'est rejoué que pendant min(expires_at GeniusPay, création + checkout_ttl_minutes)
     * (contre-audit N4) : une expiration lointaine renvoyée par GeniusPay ne prolonge pas le rejeu.
     */
    private function isCheckoutReusable(PaymentTransaction $transaction): bool
    {
        return $transaction->gateway_ref !== null
            && $transaction->checkout_url !== null
            && ($transaction->checkout_expires_at === null || $transaction->checkout_expires_at->isFuture())
            && $transaction->created_at?->copy()->addMinutes($this->checkoutTtlMinutes())->isFuture() === true;
    }

    /** Durée de vie d'un lien prise en compte côté Salono (plafond du plafond de liens et du rejeu). */
    private function checkoutTtlMinutes(): int
    {
        return max(1, (int) config('geniuspay.checkout_ttl_minutes', 30));
    }

    /**
     * Une date illisible ne doit pas annuler un paiement déjà créé chez GeniusPay :
     * la durée de vie par défaut (geniuspay.checkout_ttl_minutes) s'applique alors.
     */
    private function parseExpiry(?string $expiresAt): ?Carbon
    {
        if ($expiresAt === null || $expiresAt === '') {
            return null;
        }

        try {
            return Carbon::parse($expiresAt)->setTimezone(config('app.timezone'));
        } catch (InvalidFormatException $e) {
            Log::warning('GeniusPay expires_at illisible: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array{name?: string|null, email?: string|null, phone?: string|null}
     */
    private function customer(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            // GeniusPay attend le format international : un numéro local ferait échouer l'init.
            'phone' => is_string($user->phone) && str_starts_with($user->phone, '+') ? $user->phone : null,
        ];
    }

    private function description(Salon $salon, Plan $plan): string
    {
        return 'Abonnement '.$plan->name.' — '.$salon->name;
    }

    private function callbackUrl(string $reference): string
    {
        return rtrim(config('app.url'), '/').config('geniuspay.callback_path')
            .'?reference='.urlencode($reference);
    }
}
