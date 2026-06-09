<?php

namespace App\Repositories\Eloquent;

use App\Enums\BillingPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Repositories\Contracts\AdminStatsRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminStatsRepository implements AdminStatsRepositoryInterface
{
    public function countSalons(): int
    {
        return Salon::query()->count();
    }

    public function countUsers(): int
    {
        return User::query()->count();
    }

    public function calculateMrr(): int
    {
        return (int) Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', [SubscriptionStatus::Trial, SubscriptionStatus::Active])
            ->where('plans.is_archived', false)
            ->sum('plans.price_fcfa');
    }

    public function countNewSalonsThisMonth(): int
    {
        return Salon::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function countExpiredSubscriptions(): int
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Expired)
            ->count();
    }

    public function countAppointments(): int
    {
        return Appointment::query()->count();
    }

    public function countSalonsByCity(): array
    {
        return Salon::query()
            ->select('city', DB::raw('count(*) as total'))
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->groupBy('city')
            ->orderByDesc('total')
            ->pluck('total', 'city')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function getRecentBillingPayments(int $limit = 5): Collection
    {
        return SubscriptionPayment::query()
            ->with(['salon:id,name', 'plan:id,name'])
            ->where('status', BillingPaymentStatus::Paid)
            ->latest('paid_at')
            ->limit($limit)
            ->get();
    }
}
