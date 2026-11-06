<?php

namespace App\Jobs\Payment;

use App\Actions\Subscription\HandleGeniusPayCallbackAction;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Traite un webhook GeniusPay authentifié (signature vérifiée par le contrôleur) : la référence
 * est résolue puis le statut relu auprès de l'API avant toute activation ou remboursement.
 *
 * Une vérification impossible (API indisponible) lève une exception : le job est réessayé
 * (tries/backoff). Après le dernier essai, failed() libère la déduplication pour que GeniusPay
 * puisse relivrer et envoie une alerte ; un remboursement non vérifié reste marqué
 * (refund_check_pending_at) et la réconciliation le reprend (pentest M1).
 */
class ProcessGeniusPayWebhookJob implements ShouldQueue
{
    use Queueable;

    public const EVENT_REFUNDED = 'payment.refunded';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 60;

    public function __construct(
        public string $event,
        public ?string $gatewayReference,
        public ?string $metadataReference,
        public ?string $dedupKey = null,
    ) {
        $this->onQueue(config('geniuspay.queue'));
    }

    public function handle(
        HandleGeniusPayCallbackAction $confirm,
        PaymentTransactionRepositoryInterface $paymentTransactionRepository,
    ): void {
        $confirm->execute(
            $this->resolveInternalReference($paymentTransactionRepository),
            checkRefund: $this->event === self::EVENT_REFUNDED,
            timeoutSeconds: (int) config('geniuspay.reconcile_timeout', 8),
            throwOnVerificationError: true,
        );
    }

    public function failed(?\Throwable $exception): void
    {
        if ($this->dedupKey !== null) {
            Cache::forget($this->dedupKey);
        }

        app(HandleGeniusPayCallbackAction::class)->reportWebhookProcessingFailure(
            $this->resolveInternalReference(app(PaymentTransactionRepositoryInterface::class)),
            $this->event,
        );
    }

    private function resolveInternalReference(PaymentTransactionRepositoryInterface $paymentTransactionRepository): ?string
    {
        if ($this->gatewayReference !== null && $this->gatewayReference !== '') {
            $transaction = $paymentTransactionRepository->findByGatewayReference($this->gatewayReference);

            if ($transaction !== null) {
                return $transaction->reference;
            }
        }

        // Repli : notre référence transmise en metadata à la création du paiement.
        return $this->metadataReference;
    }
}
