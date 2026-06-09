<?php

namespace App\Repositories\Contracts;

use App\Models\PaymentTransaction;

interface PaymentTransactionRepositoryInterface
{
    public function create(array $data): PaymentTransaction;

    public function findByReference(string $reference): ?PaymentTransaction;

    public function update(PaymentTransaction $transaction, array $data): PaymentTransaction;
}
