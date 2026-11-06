<?php

namespace App\Actions\Subscription;

use App\Data\GeniusPay\GeniusPayVerifyResult;
use App\Enums\BillingPaymentMethod;
use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\GeniusPayException;
use App\Models\PaymentTransaction;
use App\Notifications\PaymentAlertNotification;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use App\Repositories\Contracts\SubscriptionPaymentRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\GeniusPay\GeniusPayService;
use App\Services\Subscription\SubscriptionBillingService;
use App\Support\ThrottledLog;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Confirme un paiement d'abonnement GeniusPay (retour navigateur, webhook signé, réconciliation).
 *
 * Garanties (audit H4) :
 * - le statut est toujours relu via l'API GeniusPay : ni l'URL de retour ni le corps du webhook
 *   ne sont crus tels quels ;
 * - idempotent : la transaction puis l'abonnement sont verrouillés, une référence n'active
 *   l'abonnement qu'une fois et deux paiements concurrents d'un salon prolongent deux périodes ;
 * - un paiement vérifié est toujours crédité, même si les règles d'abonnement ont changé ;
 * - un paiement encaissé mais douteux (montant hors tolérance, devise, environnement sandbox,
 *   activation impossible) n'est jamais marqué échoué : il passe en needs_review avec alerte ;
 * - une erreur réseau ou un paiement non terminé laisse la transaction en attente ; en arrière-plan,
 *   l'erreur est relancée pour que le job réessaie (pentest M1) ;
 * - le retour navigateur ne relit que les transactions en attente, avec un timeout court et sans
 *   nouvel essai (pentest M5).
 */
class HandleGeniusPayCallbackAction
{
    /** Format de notre référence interne (cf. InitializeSubscriptionPaymentAction). */
    public const REFERENCE_PATTERN = '/^SAL-[A-Z0-9]{16}$/';

    public const REVIEW_REFERENCE_MISMATCH = 'reference_mismatch';

    public const REVIEW_CURRENCY_MISMATCH = 'currency_mismatch';

    public const REVIEW_SANDBOX_ENVIRONMENT = 'sandbox_environment';

    public const REVIEW_AMOUNT_MISMATCH = 'amount_mismatch';

    public const REVIEW_ACTIVATION_FAILED = 'activation_failed';

    /** Transaction rejetée manuellement (geniuspay:review --reject) : plus jamais revérifiée. */
    public const REVIEW_REJECTED = 'review_rejected';

    public const ALERT_REFUNDED_AFTER_SUCCESS = 'refunded_after_success';

    public const ALERT_WEBHOOK_PROCESSING_FAILED = 'webhook_processing_failed';

    public const ALERT_RECONCILIATION_FAILED = 'reconciliation_check_failed';

    public const ALERT_REFUND_NOT_CONFIRMED = 'refund_not_confirmed';

    /** Alertes répétables regroupées : une par transaction et par période (contre-audit I9, N5). */
    private const WEBHOOK_FAILURE_ALERT_INTERVAL_SECONDS = 3600;

    private const RECONCILIATION_FAILURE_ALERT_INTERVAL_SECONDS = 86400;

    /** Libellés de l'alerte de remboursement selon le recalcul de l'abonnement (contre-audit N1). */
    private const REVOKE_ALERT_LABELS = [
        SubscriptionBillingService::REVOKE_RESTORED => 'oui',
        SubscriptionBillingService::REVOKE_EXPIRED => 'abonnement expiré (aucun autre paiement valide) — vérifier',
        SubscriptionBillingService::REVOKE_UNCHANGED => 'non — ajustement manuel requis',
        SubscriptionBillingService::REVOKE_STATUS_KEPT => 'période ajustée, statut posé par l\'administration conservé (non réactivé) — vérifier',
    ];

    /** Retour navigateur : le client attend la redirection, le webhook et la réconciliation prennent le relais. */
    private const BROWSER_TIMEOUT_SECONDS = 5;

    private const BROWSER_CHECKS_PER_MINUTE = 6;

    private const BROWSER_CHECK_WINDOW_SECONDS = 60;

    /** Écart d'arrondi admis entre amount - fees et net_amount (en francs). */
    private const NET_AMOUNT_ROUNDING_TOLERANCE = 1;

    public function __construct(
        private PaymentTransactionRepositoryInterface $paymentTransactionRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private GeniusPayService $geniusPayService,
        private SubscriptionBillingService $subscriptionBillingService,
        private SubscriptionPaymentRepositoryInterface $subscriptionPaymentRepository,
    ) {}

    public static function isValidReference(?string $reference): bool
    {
        return is_string($reference) && preg_match(self::REFERENCE_PATTERN, $reference) === 1;
    }

    /**
     * @param  string|null  $reference  Référence interne (SAL-…), pas celle de GeniusPay.
     * @param  bool  $checkRefund  Relire aussi une transaction déjà réussie (webhook payment.refunded).
     * @param  int|null  $timeoutSeconds  Timeout HTTP de la relecture (réduit en arrière-plan).
     * @param  bool  $throwOnVerificationError  Relancer l'erreur de vérification (jobs : retries).
     * @return bool true si la transaction est (ou était déjà) réussie et l'abonnement activé
     *
     * @throws GeniusPayException si $throwOnVerificationError et la vérification est impossible
     */
    public function execute(
        ?string $reference,
        bool $checkRefund = false,
        ?int $timeoutSeconds = null,
        bool $throwOnVerificationError = false,
    ): bool {
        $transaction = $this->findTransaction($reference);

        if ($transaction === null) {
            return false;
        }

        if (! $this->needsVerification($transaction, $checkRefund)) {
            return $transaction->status === PaymentTransactionStatus::Success;
        }

        return $this->verify($transaction, $checkRefund, $timeoutSeconds, $throwOnVerificationError);
    }

    /**
     * Retour navigateur (pentest M5) : seule une transaction en attente est relue, au plus
     * BROWSER_CHECKS_PER_MINUTE fois par minute et par référence, avec un timeout court et sans
     * nouvel essai. Les autres états répondent selon la base.
     */
    public function executeFromBrowser(string $reference): bool
    {
        $transaction = $this->findTransaction($reference);

        if ($transaction === null) {
            return false;
        }

        if ($transaction->status !== PaymentTransactionStatus::Pending || ! $this->needsVerification($transaction, false)) {
            return $transaction->status === PaymentTransactionStatus::Success;
        }

        $throttleKey = 'geniuspay-callback-check:'.$transaction->reference;

        if (RateLimiter::tooManyAttempts($throttleKey, self::BROWSER_CHECKS_PER_MINUTE)) {
            return false;
        }

        RateLimiter::hit($throttleKey, self::BROWSER_CHECK_WINDOW_SECONDS);

        return $this->verify($transaction, false, self::BROWSER_TIMEOUT_SECONDS, false, retry: false);
    }

    /**
     * Validation manuelle d'une transaction needs_review (geniuspay:review --approve) : le paiement
     * est relu, puis activé par le même chemin verrouillé que les confirmations automatiques, sans
     * les contrôles de montant, devise et environnement déjà examinés par l'opérateur.
     *
     * @throws GeniusPayException API indisponible
     */
    public function approveReview(string $reference): bool
    {
        $transaction = $this->findTransaction($reference);

        if ($transaction?->status !== PaymentTransactionStatus::NeedsReview || ! $this->needsVerification($transaction, true)) {
            return false;
        }

        $verified = $this->geniusPayService->getPayment(
            $transaction->gateway_ref,
            (int) config('geniuspay.reconcile_timeout', 8),
        );

        if ($verified->isRefunded()) {
            $this->handleRefund($transaction);

            return false;
        }

        if (! $verified->isSuccessful()) {
            return false;
        }

        return $this->activate($transaction, $verified, [PaymentTransactionStatus::NeedsReview]);
    }

    /** Rejet manuel d'une transaction needs_review (geniuspay:review --reject) : passe en failed. */
    public function rejectReview(string $reference): bool
    {
        $transaction = $this->findTransaction($reference);

        return $transaction !== null && $this->paymentTransactionRepository->transitionStatus(
            $transaction,
            [PaymentTransactionStatus::NeedsReview],
            ['status' => PaymentTransactionStatus::Failed, 'review_reason' => self::REVIEW_REJECTED],
        );
    }

    /** Tous les essais d'un webhook ont échoué (vérification impossible) : alerte d'exploitation. */
    public function reportWebhookProcessingFailure(?string $reference, string $event): void
    {
        $transaction = self::isValidReference($reference)
            ? $this->paymentTransactionRepository->findByReference($reference)
            : null;

        if ($transaction === null) {
            Log::critical('GeniusPay: webhook non traité après tous les essais, transaction inconnue.', ['event' => $event]);

            return;
        }

        $this->alertOnce(self::ALERT_WEBHOOK_PROCESSING_FAILED, $transaction, ['event' => $event], self::WEBHOOK_FAILURE_ALERT_INTERVAL_SECONDS);
    }

    /** Tous les essais d'une vérification de réconciliation ont échoué (contre-audit N5). */
    public function reportReconciliationFailure(string $reference, bool $checkRefund): void
    {
        $transaction = $this->paymentTransactionRepository->findByReference($reference);

        if ($transaction === null) {
            return;
        }

        $this->alertOnce(
            self::ALERT_RECONCILIATION_FAILED,
            $transaction,
            ['relecture_remboursement' => $checkRefund ? 'oui' : 'non'],
            self::RECONCILIATION_FAILURE_ALERT_INTERVAL_SECONDS,
        );
    }

    /**
     * Remboursement annoncé jamais confirmé après le nombre maximal de passages de réconciliation
     * (contre-audit N3/N5) : la relecture s'arrête et une alerte unique demande une vérification
     * manuelle. Le statut n'est pas modifié : une transaction réussie passée en needs_review
     * serait réactivée par geniuspay:review --approve.
     */
    public function abandonRefundCheck(PaymentTransaction $transaction): void
    {
        $attempts = $transaction->refund_check_attempts;

        if (! $this->paymentTransactionRepository->clearRefundCheck($transaction)) {
            return;
        }

        $this->alert(self::ALERT_REFUND_NOT_CONFIRMED, $transaction, ['passages_reconciliation' => $attempts]);
    }

    private function findTransaction(?string $reference): ?PaymentTransaction
    {
        if (! self::isValidReference($reference)) {
            return null;
        }

        $transaction = $this->paymentTransactionRepository->findByReference($reference);

        if ($transaction === null) {
            // Agrégé (pentest F5) : une référence inconnue ne prouve rien et peut être envoyée en boucle.
            ThrottledLog::warning('geniuspay-transaction-not-found', 'GeniusPay: transaction introuvable.', ['reference' => $reference]);
        }

        return $transaction;
    }

    private function needsVerification(PaymentTransaction $transaction, bool $checkRefund): bool
    {
        if ($transaction->gateway !== PaymentGateway::GeniusPay) {
            Log::info('GeniusPay: transaction d\'un autre prestataire ignorée.', ['reference' => $transaction->reference]);

            return false;
        }

        if (! is_string($transaction->gateway_ref) || $transaction->gateway_ref === '') {
            Log::warning('GeniusPay: transaction sans référence prestataire.', ['reference' => $transaction->reference]);

            return false;
        }

        if ($transaction->review_reason === self::REVIEW_REJECTED) {
            return false;
        }

        return match ($transaction->status) {
            PaymentTransactionStatus::Pending, PaymentTransactionStatus::Failed => true,
            PaymentTransactionStatus::Success, PaymentTransactionStatus::NeedsReview => $checkRefund,
            PaymentTransactionStatus::Refunded => false,
        };
    }

    /**
     * Une relecture de remboursement est marquée (refund_check_pending_at) avant l'appel et
     * démarquée seulement après une vérification aboutie : la réconciliation reprend celles qui
     * n'ont jamais abouti (pentest M1).
     *
     * @throws GeniusPayException si $throwOnError et la vérification est impossible
     */
    private function verify(
        PaymentTransaction $transaction,
        bool $checkRefund,
        ?int $timeoutSeconds,
        bool $throwOnError,
        bool $retry = true,
    ): bool {
        $isRefundCheck = $checkRefund
            && in_array($transaction->status, [PaymentTransactionStatus::Success, PaymentTransactionStatus::NeedsReview], true);

        if ($isRefundCheck && $transaction->refund_check_pending_at === null) {
            $transaction = $this->paymentTransactionRepository->update($transaction, [
                'refund_check_pending_at' => Carbon::now(),
                'refund_check_attempts' => 0,
            ]);
        }

        try {
            $verified = $this->geniusPayService->getPayment($transaction->gateway_ref, $timeoutSeconds, $retry);
        } catch (GeniusPayException $e) {
            // Erreur de vérification (réseau, API, réponse incomplète) : la transaction reste en l'état.
            Log::error('GeniusPay verify failed: '.$e->getMessage(), ['reference' => $transaction->reference]);

            if ($throwOnError) {
                throw $e;
            }

            return false;
        }

        $audit = [
            'gateway_amount' => $verified->amount,
            'gateway_fees' => $verified->fees,
            'gateway_net_amount' => $verified->netAmount,
        ];

        // Remboursement annoncé mais pas encore visible via l'API (contre-audit N3) : la marque est
        // conservée, la réconciliation relit jusqu'au seuil geniuspay.refund_check_max_attempts.
        if (! $isRefundCheck || $verified->isRefunded()) {
            $audit['refund_check_pending_at'] = null;
        }

        $transaction = $this->paymentTransactionRepository->update($transaction, $audit);

        return $this->apply($transaction, $verified);
    }

    private function apply(PaymentTransaction $transaction, GeniusPayVerifyResult $verified): bool
    {
        if ($verified->isRefunded()) {
            $this->handleRefund($transaction);

            return false;
        }

        // Relecture demandée par un webhook de remboursement, mais le paiement n'est pas remboursé.
        if (in_array($transaction->status, [PaymentTransactionStatus::Success, PaymentTransactionStatus::NeedsReview], true)) {
            return $transaction->status === PaymentTransactionStatus::Success;
        }

        if (! $verified->isSuccessful()) {
            if ($verified->isDefinitiveFailure()) {
                $this->paymentTransactionRepository->transitionStatus(
                    $transaction,
                    [PaymentTransactionStatus::Pending],
                    ['status' => PaymentTransactionStatus::Failed],
                );
            }

            return false;
        }

        $reviewReason = $this->reviewReason($transaction, $verified);

        if ($reviewReason !== null) {
            $this->markNeedsReview($transaction, $reviewReason, $this->verifiedContext($verified));

            return false;
        }

        return $this->activate($transaction, $verified);
    }

    private function reviewReason(PaymentTransaction $transaction, GeniusPayVerifyResult $verified): ?string
    {
        if ($verified->reference !== $transaction->gateway_ref) {
            return self::REVIEW_REFERENCE_MISMATCH;
        }

        if (strtoupper($verified->currency) !== strtoupper($transaction->currency)) {
            return self::REVIEW_CURRENCY_MISMATCH;
        }

        if (app()->isProduction() && strtolower((string) $verified->environment) === 'sandbox') {
            return self::REVIEW_SANDBOX_ENVIRONMENT;
        }

        return $this->isAcceptedAmount($transaction, $verified) ? null : self::REVIEW_AMOUNT_MISMATCH;
    }

    /**
     * Pentest M4 : le montant brut `amount` décide seul, `net_amount` ne valide jamais un paiement.
     * Brut égal ou supérieur au prix : crédité. Brut inférieur dans la tolérance (frais) : crédité
     * avec un warning. `net_amount`, s'il est fourni, doit être ≤ amount et égal à amount - fees
     * (à NET_AMOUNT_ROUNDING_TOLERANCE près) : sinon la réponse est incohérente et part en revue.
     */
    private function isAcceptedAmount(PaymentTransaction $transaction, GeniusPayVerifyResult $verified): bool
    {
        $expected = $transaction->amount;
        $context = ['reference' => $transaction->reference, 'expected_amount' => $expected] + $this->verifiedContext($verified);

        if (! $this->isConsistentNetAmount($verified)) {
            Log::warning('GeniusPay: net_amount incohérent avec amount et fees.', $context);

            return false;
        }

        if ($verified->amount === $expected) {
            return true;
        }

        if ($verified->amount > $expected) {
            Log::warning('GeniusPay: montant reçu supérieur au prix attendu, crédité.', $context);

            return true;
        }

        if ($this->isWithinTolerance($verified->amount, $expected)) {
            Log::warning('GeniusPay: montant reçu inférieur au prix attendu (frais), crédité dans la tolérance.', $context);

            return true;
        }

        return false;
    }

    private function isConsistentNetAmount(GeniusPayVerifyResult $verified): bool
    {
        if ($verified->netAmount === null) {
            return true;
        }

        if ($verified->netAmount > $verified->amount) {
            return false;
        }

        return $verified->fees === null
            || abs($verified->amount - $verified->fees - $verified->netAmount) <= self::NET_AMOUNT_ROUNDING_TOLERANCE;
    }

    private function isWithinTolerance(int $amount, int $expected): bool
    {
        $tolerance = max(0.0, (float) config('geniuspay.amount_tolerance_percent', 8));

        return $amount * 100 >= $expected * (100 - $tolerance);
    }

    /**
     * @param  list<PaymentTransactionStatus>  $fromStatuses  Statuts depuis lesquels l'activation est permise.
     */
    private function activate(
        PaymentTransaction $transaction,
        GeniusPayVerifyResult $verified,
        array $fromStatuses = [PaymentTransactionStatus::Pending, PaymentTransactionStatus::Failed],
    ): bool {
        $paidAt = $this->paidAt($verified->completedAt);

        try {
            return DB::transaction(function () use ($transaction, $verified, $paidAt, $fromStatuses): bool {
                $locked = $this->paymentTransactionRepository->findForUpdate($transaction->id);

                // Un appel concurrent (retour navigateur + webhook, double clic) a déjà traité le paiement.
                if ($locked === null || $locked->status === PaymentTransactionStatus::Success) {
                    return $locked !== null;
                }

                if (! in_array($locked->status, $fromStatuses, true)) {
                    return false;
                }

                $current = $this->subscriptionRepository->findCurrentBySalonId($locked->salon_id);
                $subscription = $current !== null ? $this->subscriptionRepository->findByIdForUpdate($current->id) : null;

                if ($subscription === null || $locked->plan === null || $locked->salon === null) {
                    throw new \RuntimeException('Abonnement, plan ou salon introuvable.');
                }

                $this->subscriptionBillingService->activateFromPayment(
                    salon: $locked->salon,
                    subscription: $subscription,
                    targetPlan: $locked->plan,
                    amount: $locked->amount,
                    paymentReference: $locked->reference,
                    method: BillingPaymentMethod::fromGeniusPay($verified->paymentMethod, $verified->provider),
                    paidAt: $paidAt,
                    enforceSubscriptionRules: false,
                );

                $this->recordReceivedAmount($locked->reference, $verified->amount);

                $this->paymentTransactionRepository->update($locked, [
                    'status' => PaymentTransactionStatus::Success,
                    'paid_at' => $paidAt,
                    'review_reason' => null,
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            // Paiement encaissé mais activation impossible : ne jamais le perdre, il passe en revue manuelle.
            Log::critical('GeniusPay: activation après paiement échouée: '.$e->getMessage(), [
                'reference' => $transaction->reference,
            ]);
            // Le message brut (SQL, valeurs liées) reste dans le log, pas dans l'alerte mail (pentest I1).
            $this->markNeedsReview($transaction, self::REVIEW_ACTIVATION_FAILED, ['error_type' => class_basename($e)]);

            return false;
        }
    }

    private function handleRefund(PaymentTransaction $transaction): void
    {
        $refunded = ['status' => PaymentTransactionStatus::Refunded];

        if ($this->paymentTransactionRepository->transitionStatus($transaction, [PaymentTransactionStatus::Success], $refunded)) {
            $this->revokeSubscriptionPeriod($transaction);

            return;
        }

        $notActivated = [PaymentTransactionStatus::Pending, PaymentTransactionStatus::Failed, PaymentTransactionStatus::NeedsReview];

        if ($this->paymentTransactionRepository->transitionStatus($transaction, $notActivated, $refunded)) {
            Log::warning('GeniusPay: paiement remboursé avant activation.', ['reference' => $transaction->reference]);
        }
    }

    private function revokeSubscriptionPeriod(PaymentTransaction $transaction): void
    {
        try {
            $outcome = $this->subscriptionBillingService->revokeRefundedPayment($transaction->reference);
        } catch (\Throwable $e) {
            Log::error('GeniusPay: retrait de la période remboursée échoué: '.$e->getMessage(), [
                'reference' => $transaction->reference,
            ]);
            $outcome = SubscriptionBillingService::REVOKE_UNCHANGED;
        }

        $this->alert(self::ALERT_REFUNDED_AFTER_SUCCESS, $transaction, [
            'periode_retiree_automatiquement' => self::REVOKE_ALERT_LABELS[$outcome],
        ]);
    }

    /** Montant brut réellement reçu, à côté du prix enregistré dans amount (pentest M4). */
    private function recordReceivedAmount(string $reference, int $receivedAmount): void
    {
        $payment = $this->subscriptionPaymentRepository->findByReference($reference);

        if ($payment !== null) {
            $this->subscriptionPaymentRepository->update($payment, ['gateway_amount' => $receivedAmount]);
        }
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    private function markNeedsReview(PaymentTransaction $transaction, string $reason, array $context): void
    {
        $moved = $this->paymentTransactionRepository->transitionStatus(
            $transaction,
            [PaymentTransactionStatus::Pending, PaymentTransactionStatus::Failed],
            ['status' => PaymentTransactionStatus::NeedsReview, 'review_reason' => $reason],
        );

        if ($moved) {
            $this->alert($reason, $transaction, $context);
        }
    }

    /**
     * Alerte regroupée : au plus une par motif, transaction et période ; les suivantes sont seulement loguées.
     *
     * @param  array<string, scalar|null>  $context
     */
    private function alertOnce(string $reason, PaymentTransaction $transaction, array $context, int $intervalSeconds): void
    {
        if (Cache::add('geniuspay-alert:'.$reason.':'.$transaction->id, true, $intervalSeconds)) {
            $this->alert($reason, $transaction, $context);

            return;
        }

        Log::warning('GeniusPay: alerte paiement déjà envoyée pour cette période, regroupée.', [
            'reason' => $reason,
            'reference' => $transaction->reference,
        ] + $context);
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    private function alert(string $reason, PaymentTransaction $transaction, array $context): void
    {
        $context = [
            'transaction_id' => $transaction->id,
            'salon_id' => $transaction->salon_id,
            'gateway_ref' => $transaction->gateway_ref,
            'expected_amount' => $transaction->amount,
            'currency' => $transaction->currency,
        ] + $context;

        Log::critical('GeniusPay: alerte paiement '.$reason, ['reference' => $transaction->reference] + $context);

        $email = config('geniuspay.alert_email');

        if (! is_string($email) || $email === '') {
            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new PaymentAlertNotification($reason, $transaction->reference, $context));
        } catch (\Throwable $e) {
            Log::error('GeniusPay: envoi de l\'alerte paiement impossible: '.$e->getMessage(), [
                'reference' => $transaction->reference,
            ]);
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    private function verifiedContext(GeniusPayVerifyResult $verified): array
    {
        return [
            'received_reference' => $verified->reference,
            'received_amount' => $verified->amount,
            'received_fees' => $verified->fees,
            'received_net_amount' => $verified->netAmount,
            'received_currency' => $verified->currency,
            'environment' => $verified->environment,
        ];
    }

    private function paidAt(?string $completedAt): Carbon
    {
        if ($completedAt === null) {
            return Carbon::now();
        }

        try {
            return Carbon::parse($completedAt);
        } catch (InvalidFormatException $e) {
            Log::warning('GeniusPay completed_at illisible: '.$e->getMessage());

            return Carbon::now();
        }
    }
}
