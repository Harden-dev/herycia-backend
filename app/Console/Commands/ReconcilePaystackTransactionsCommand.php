<?php

namespace App\Console\Commands;

use App\Actions\Subscription\HandlePaystackCallbackAction;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;

/**
 * Filet de sécurité Paystack (audit H4/P3) : revérifie les transactions restées en attente
 * (navigateur fermé, webhook perdu) pour qu'aucun paiement encaissé ne reste sans activation.
 */
class ReconcilePaystackTransactionsCommand extends Command
{
    protected $signature = 'paystack:reconcile {--hours=48 : Ancienneté maximale des transactions à revérifier}';

    protected $description = 'Revérifie auprès de Paystack les transactions d\'abonnement en attente';

    public function handle(HandlePaystackCallbackAction $confirm): int
    {
        $confirmed = 0;

        PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(10))
            ->where('created_at', '>=', now()->subHours((int) $this->option('hours')))
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->each(function (PaymentTransaction $transaction) use ($confirm, &$confirmed): void {
                if ($confirm->execute($transaction->reference)) {
                    $confirmed++;
                }
            });

        $this->info("Transactions confirmées : {$confirmed}");

        return self::SUCCESS;
    }
}
