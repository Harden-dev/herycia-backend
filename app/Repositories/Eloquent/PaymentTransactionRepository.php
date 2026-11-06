<?php

namespace App\Repositories\Eloquent;

use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use Carbon\CarbonInterface;

class PaymentTransactionRepository implements PaymentTransactionRepositoryInterface
{
    public function __construct(
        protected PaymentTransaction $paymentTransactionModel,
    ) {}

    public function create(array $data): PaymentTransaction
    {
        return $this->paymentTransactionModel->create($data);
    }

    public function findByReference(string $reference): ?PaymentTransaction
    {
        return $this->paymentTransactionModel->newQuery()
            ->with(['plan', 'salon'])
            ->where('reference', $reference)
            ->first();
    }

    public function findByGatewayReference(string $gatewayReference): ?PaymentTransaction
    {
        return $this->paymentTransactionModel->newQuery()
            ->with(['plan', 'salon'])
            ->where('gateway_ref', $gatewayReference)
            ->first();
    }

    public function findBySalonAndIdempotencyKey(string $salonId, string $idempotencyKey): ?PaymentTransaction
    {
        return $this->paymentTransactionModel->newQuery()
            ->with('plan')
            ->where('salon_id', $salonId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function update(PaymentTransaction $transaction, array $data): PaymentTransaction
    {
        $transaction->update($data);

        return $transaction->refresh();
    }

    public function countOpenCheckoutsForSalon(string $salonId, CarbonInterface $openedAfter): int
    {
        return $this->paymentTransactionModel->newQuery()
            ->where('salon_id', $salonId)
            ->where('status', PaymentTransactionStatus::Pending->value)
            ->whereNotNull('gateway_ref')
            ->where('created_at', '>', $openedAfter)
            ->where(fn ($query) => $query->whereNull('checkout_expires_at')->orWhere('checkout_expires_at', '>', now()))
            ->count();
    }

    public function clearRefundCheck(PaymentTransaction $transaction): bool
    {
        $cleared = $this->paymentTransactionModel->newQuery()
            ->whereKey($transaction->id)
            ->whereNotNull('refund_check_pending_at')
            ->update(['refund_check_pending_at' => null, 'refund_check_attempts' => 0]) === 1;

        if ($cleared) {
            $transaction->refresh();
        }

        return $cleared;
    }

    public function findForUpdate(string $id): ?PaymentTransaction
    {
        return $this->paymentTransactionModel->newQuery()
            ->with(['plan', 'salon'])
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    public function transitionStatus(PaymentTransaction $transaction, array $fromStatuses, array $data): bool
    {
        $updated = $this->paymentTransactionModel->newQuery()
            ->whereKey($transaction->id)
            ->whereIn('status', array_map(fn (PaymentTransactionStatus $status) => $status->value, $fromStatuses))
            ->update(array_map(
                fn ($value) => $value instanceof \BackedEnum ? $value->value : $value,
                $data,
            )) === 1;

        if ($updated) {
            $transaction->refresh();
        }

        return $updated;
    }
}
