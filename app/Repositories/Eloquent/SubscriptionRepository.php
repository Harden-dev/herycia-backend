<?php

namespace App\Repositories\Eloquent;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(
        protected Subscription $subscriptionModel,
    ) {}

    public function create(array $data): Subscription
    {
        return $this->subscriptionModel->create($data);
    }

    public function findLatestBySalonId(string $salonId): ?Subscription
    {
        return $this->subscriptionModel->newQuery()
            ->with('plan')
            ->where('salon_id', $salonId)
            ->latest('created_at')
            ->first();
    }

    public function findCurrentBySalonId(string $salonId): ?Subscription
    {
        $current = $this->subscriptionModel->newQuery()
            ->with('plan')
            ->where('salon_id', $salonId)
            ->whereIn('status', [
                SubscriptionStatus::Trial,
                SubscriptionStatus::Active,
            ])
            ->latest('created_at')
            ->first();

        if ($current !== null) {
            return $current;
        }

        return $this->findLatestBySalonId($salonId);
    }

    public function findById(string $id): ?Subscription
    {
        return $this->subscriptionModel->newQuery()
            ->with(['salon', 'plan'])
            ->find($id);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);

        return $subscription->refresh();
    }

    public function paginateForAdmin(
        int $perPage = 15,
        ?string $search = null,
        ?string $planId = null,
        ?string $status = null,
        ?string $salonId = null,
    ): LengthAwarePaginator {
        $query = $this->subscriptionModel->newQuery()
            ->with(['salon:id,name,city', 'plan:id,name,code,price_fcfa']);

        if ($search) {
            $query->whereHas('salon', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($planId) {
            $query->where('plan_id', $planId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($salonId) {
            $query->where('salon_id', $salonId);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
