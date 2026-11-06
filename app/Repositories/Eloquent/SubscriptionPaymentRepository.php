<?php

namespace App\Repositories\Eloquent;

use App\Enums\BillingPaymentStatus;
use App\Models\SubscriptionPayment;
use App\Repositories\Contracts\SubscriptionPaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriptionPaymentRepository implements SubscriptionPaymentRepositoryInterface
{
    public function __construct(
        protected SubscriptionPayment $paymentModel,
    ) {}

    public function create(array $data): SubscriptionPayment
    {
        return $this->paymentModel->create($data);
    }

    public function findById(string $id): ?SubscriptionPayment
    {
        return $this->paymentModel->newQuery()
            ->with(['salon', 'plan', 'subscription'])
            ->find($id);
    }

    public function findByReference(string $reference): ?SubscriptionPayment
    {
        return $this->paymentModel->newQuery()
            ->where('reference', $reference)
            ->first();
    }

    public function update(SubscriptionPayment $payment, array $data): SubscriptionPayment
    {
        $payment->update($data);

        return $payment->refresh();
    }

    public function findLatestPaidForSubscription(string $subscriptionId): ?SubscriptionPayment
    {
        return $this->paymentModel->newQuery()
            ->where('subscription_id', $subscriptionId)
            ->where('status', BillingPaymentStatus::Paid->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function existsOtherForSubscription(string $subscriptionId, string $exceptPaymentId): bool
    {
        return $this->paymentModel->newQuery()
            ->where('subscription_id', $subscriptionId)
            ->whereKeyNot($exceptPaymentId)
            ->exists();
    }

    public function paginateForAdmin(
        int $perPage = 15,
        ?string $from = null,
        ?string $to = null,
        ?string $planId = null,
        ?string $status = null,
        ?string $salonId = null,
    ): LengthAwarePaginator {
        $query = $this->paymentModel->newQuery()
            ->with(['salon:id,name,city', 'plan:id,name,code']);

        if ($from !== null) {
            $query->whereDate('paid_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('paid_at', '<=', $to);
        }

        if ($planId !== null) {
            $query->where('plan_id', $planId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($salonId !== null) {
            $query->where('salon_id', $salonId);
        }

        return $query->orderByDesc('paid_at')->orderByDesc('created_at')->paginate($perPage);
    }
}
