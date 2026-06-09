<?php

namespace App\Repositories\Eloquent;

use App\Models\PaymentTransaction;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;

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

    public function update(PaymentTransaction $transaction, array $data): PaymentTransaction
    {
        $transaction->update($data);

        return $transaction->refresh();
    }
}
