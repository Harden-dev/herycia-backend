<?php

namespace App\Console\Commands;

use App\Actions\Subscription\HandleGeniusPayCallbackAction;
use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use App\Jobs\Payment\ConfirmGeniusPayPaymentJob;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Filet de sécurité GeniusPay (audit H4/P3) : sélectionne les transactions à revérifier
 * (en attente, ou échouées récemment pour rattraper une confirmation mobile money tardive)
 * et confie chaque vérification à un job en file.
 *
 * Tri par last_checked_at (jamais vérifiées d'abord) : toutes les transactions finissent
 * par être revérifiées même au-delà de la limite par passage.
 *
 * Reprend aussi les remboursements signalés par webhook mais jamais vérifiés ou pas encore
 * confirmés par l'API (refund_check_pending_at, pentest M1, contre-audit N3), au plus
 * geniuspay.refund_check_max_attempts passages : au-delà, la relecture est abandonnée avec une
 * alerte unique (contre-audit N5).
 */
class ReconcileGeniusPayTransactionsCommand extends Command
{
    private const BATCH_SIZE = 200;

    /** Délai laissé au callback et au webhook avant que la réconciliation ne prenne le relais. */
    private const GRACE_MINUTES = 10;

    protected $signature = 'geniuspay:reconcile {--hours=48 : Ancienneté maximale des transactions à revérifier}';

    protected $description = 'Planifie la revérification auprès de GeniusPay des transactions d\'abonnement en attente ou échouées récemment';

    public function handle(HandleGeniusPayCallbackAction $action): int
    {
        $unconfirmed = $this->baseQuery()
            ->whereIn('status', [PaymentTransactionStatus::Pending->value, PaymentTransactionStatus::Failed->value])
            ->where(fn (Builder $query) => $query->whereNull('review_reason')
                ->orWhere('review_reason', '!=', HandleGeniusPayCallbackAction::REVIEW_REJECTED))
            ->where('created_at', '<=', now()->subMinutes(self::GRACE_MINUTES))
            ->where('created_at', '>=', now()->subHours((int) $this->option('hours')))
            ->get(['id', 'reference']);

        $refundChecks = $this->baseQuery()
            ->whereIn('status', [PaymentTransactionStatus::Success->value, PaymentTransactionStatus::NeedsReview->value])
            ->whereNotNull('refund_check_pending_at')
            ->get();

        $maxAttempts = max(1, (int) config('geniuspay.refund_check_max_attempts', 96));
        [$exhausted, $refundChecks] = $refundChecks->partition(
            fn (PaymentTransaction $transaction) => $transaction->refund_check_attempts >= $maxAttempts,
        );

        foreach ($exhausted as $transaction) {
            $action->abandonRefundCheck($transaction);
        }

        if ($refundChecks->isNotEmpty()) {
            PaymentTransaction::query()->whereKey($refundChecks->modelKeys())->increment('refund_check_attempts');
        }

        $count = $this->dispatchChecks($unconfirmed, false) + $this->dispatchChecks($refundChecks, true);

        $this->info($count === 0 ? 'Aucune transaction à revérifier.' : "Vérifications planifiées : {$count}");

        return self::SUCCESS;
    }

    /**
     * @return Builder<PaymentTransaction>
     */
    private function baseQuery(): Builder
    {
        return PaymentTransaction::query()
            ->where('gateway', PaymentGateway::GeniusPay->value)
            ->whereNotNull('gateway_ref')
            ->orderByRaw('CASE WHEN last_checked_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_checked_at')
            ->limit(self::BATCH_SIZE);
    }

    /**
     * @param  Collection<int, PaymentTransaction>  $transactions
     */
    private function dispatchChecks(Collection $transactions, bool $checkRefund): int
    {
        if ($transactions->isEmpty()) {
            return 0;
        }

        PaymentTransaction::query()
            ->whereKey($transactions->modelKeys())
            ->update(['last_checked_at' => now()]);

        foreach ($transactions as $transaction) {
            ConfirmGeniusPayPaymentJob::dispatch($transaction->id, $transaction->reference, $checkRefund);
        }

        return $transactions->count();
    }
}
