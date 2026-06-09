<?php

namespace App\Providers;

use App\Repositories\Contracts\AdminStatsRepositoryInterface;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\QueueEntryRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PaymentTransactionRepositoryInterface;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\SalonRepositoryInterface;
use App\Repositories\Contracts\SalonScheduleRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\SubscriptionPaymentRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AdminStatsRepository;
use App\Repositories\Eloquent\AppointmentRepository;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\ClientRepository;
use App\Repositories\Eloquent\DashboardRepository;
use App\Repositories\Eloquent\QueueEntryRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\PaymentTransactionRepository;
use App\Repositories\Eloquent\PlanRepository;
use App\Repositories\Eloquent\SalonRepository;
use App\Repositories\Eloquent\SalonScheduleRepository;
use App\Repositories\Eloquent\ServiceRepository;
use App\Repositories\Eloquent\SubscriptionPaymentRepository;
use App\Repositories\Eloquent\SubscriptionRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(QueueEntryRepositoryInterface::class, QueueEntryRepository::class);
        $this->app->bind(AppointmentRepositoryInterface::class, AppointmentRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(PaymentTransactionRepositoryInterface::class, PaymentTransactionRepository::class);
        $this->app->bind(SalonRepositoryInterface::class, SalonRepository::class);
        $this->app->bind(SalonScheduleRepositoryInterface::class, SalonScheduleRepository::class);
        $this->app->bind(PlanRepositoryInterface::class, PlanRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, SubscriptionRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->bind(AdminStatsRepositoryInterface::class, AdminStatsRepository::class);
        $this->app->bind(SubscriptionPaymentRepositoryInterface::class, SubscriptionPaymentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
