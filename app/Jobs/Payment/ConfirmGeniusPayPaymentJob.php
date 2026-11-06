<?php

namespace App\Jobs\Payment;

use App\Actions\Subscription\HandleGeniusPayCallbackAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Revérifie une transaction auprès de GeniusPay (réconciliation planifiée). Unique par
 * transaction : un passage de réconciliation ne double pas une vérification encore en file.
 * Une vérification impossible lève une exception pour que tries/backoff s'appliquent ; après le
 * dernier essai, failed() envoie une alerte, au plus une par jour et par transaction (contre-audit N5).
 */
class ConfirmGeniusPayPaymentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 60;

    /** Verrou d'unicité relâché au plus tard après la période de réconciliation. */
    public int $uniqueFor = 900;

    public function __construct(
        public string $transactionId,
        public string $reference,
        public bool $checkRefund = false,
    ) {
        $this->onQueue(config('geniuspay.queue'));
    }

    public function uniqueId(): string
    {
        return $this->transactionId;
    }

    public function handle(HandleGeniusPayCallbackAction $confirm): void
    {
        $confirm->execute(
            $this->reference,
            checkRefund: $this->checkRefund,
            timeoutSeconds: (int) config('geniuspay.reconcile_timeout', 8),
            throwOnVerificationError: true,
        );
    }

    public function failed(?\Throwable $exception): void
    {
        app(HandleGeniusPayCallbackAction::class)->reportReconciliationFailure($this->reference, $this->checkRefund);
    }
}
