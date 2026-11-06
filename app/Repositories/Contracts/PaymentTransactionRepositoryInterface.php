<?php

namespace App\Repositories\Contracts;

use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use Carbon\CarbonInterface;

interface PaymentTransactionRepositoryInterface
{
    public function create(array $data): PaymentTransaction;

    public function findByReference(string $reference): ?PaymentTransaction;

    public function findByGatewayReference(string $gatewayReference): ?PaymentTransaction;

    public function findBySalonAndIdempotencyKey(string $salonId, string $idempotencyKey): ?PaymentTransaction;

    public function update(PaymentTransaction $transaction, array $data): PaymentTransaction;

    /**
     * Transactions en attente du salon dont le lien de paiement est créé, non expiré et créé après
     * $openedAfter (durée prise en compte plafonnée, contre-audit N4).
     */
    public function countOpenCheckoutsForSalon(string $salonId, CarbonInterface $openedAfter): int;

    /** Retire la marque de relecture de remboursement ; false si elle était déjà retirée. */
    public function clearRefundCheck(PaymentTransaction $transaction): bool;

    /** Relit la transaction avec un verrou exclusif : à appeler dans une transaction. */
    public function findForUpdate(string $id): ?PaymentTransaction;

    /**
     * Mise à jour atomique conditionnée au statut courant (UPDATE … WHERE status IN …) :
     * retourne false si la transaction a changé de statut entre-temps.
     *
     * @param  list<PaymentTransactionStatus>  $fromStatuses
     */
    public function transitionStatus(PaymentTransaction $transaction, array $fromStatuses, array $data): bool;
}
