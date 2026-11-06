<?php

namespace App\Console\Commands;

use App\Actions\Subscription\HandleGeniusPayCallbackAction;
use App\Exceptions\GeniusPayException;
use App\Models\PaymentTransaction;
use App\Services\Audit\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Traitement manuel d'une transaction GeniusPay en needs_review (pentest I5) :
 * --approve relit le paiement puis l'active par le chemin verrouillé habituel,
 * --reject la passe en failed (elle n'est plus jamais revérifiée). Journalisé (log + audit_logs).
 */
class ReviewGeniusPayTransactionCommand extends Command
{
    private const AUDIT_ACTION = 'payment.review';

    protected $signature = 'geniuspay:review
        {reference : Référence interne SAL-… de la transaction}
        {--approve : Activer l\'abonnement payé}
        {--reject : Rejeter la transaction (statut failed)}';

    protected $description = 'Valide ou rejette une transaction d\'abonnement GeniusPay en vérification manuelle';

    public function handle(HandleGeniusPayCallbackAction $action, AuditLogService $auditLogService): int
    {
        $reference = (string) $this->argument('reference');
        $approve = (bool) $this->option('approve');

        if ($approve === (bool) $this->option('reject')) {
            $this->error('Indiquez exactement une option : --approve ou --reject.');

            return self::INVALID;
        }

        if (! HandleGeniusPayCallbackAction::isValidReference($reference)) {
            $this->error('Référence invalide.');

            return self::INVALID;
        }

        try {
            $done = $approve ? $action->approveReview($reference) : $action->rejectReview($reference);
        } catch (GeniusPayException $e) {
            Log::error('GeniusPay review: vérification impossible: '.$e->getMessage(), ['reference' => $reference]);
            $this->error('GeniusPay est indisponible, réessayez plus tard.');

            return self::FAILURE;
        }

        $decision = $approve ? 'approve' : 'reject';
        $transaction = PaymentTransaction::query()->where('reference', $reference)->first(['id', 'status']);
        $status = $transaction?->status->value ?? 'introuvable';
        $context = ['reference' => $reference, 'decision' => $decision, 'applied' => $done, 'status' => $status];

        Log::warning('GeniusPay review: décision manuelle.', $context);

        if (! $done) {
            $this->error("Décision non appliquée (statut actuel : {$status}).");

            return self::FAILURE;
        }

        $auditLogService->log(
            action: self::AUDIT_ACTION,
            entityType: 'payment_transaction',
            entityId: $transaction?->id,
            description: "Revue manuelle GeniusPay ({$decision}) de {$reference}",
            newValues: $context,
        );

        $this->info("Décision appliquée : {$decision} (statut : {$status}).");

        return self::SUCCESS;
    }
}
