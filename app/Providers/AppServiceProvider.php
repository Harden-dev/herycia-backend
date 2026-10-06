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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
        $this->configureTrustedProxies();
    }

    /** Sans proxies de confiance, derrière un load balancer toutes les requêtes ont l'IP du proxy (audit M6). */
    private function configureTrustedProxies(): void
    {
        $proxies = config('salono.trusted_proxies');

        if (! is_string($proxies) || trim($proxies) === '') {
            return;
        }

        TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
    }

    /**
     * Limiteurs de débit (audit C5) : par IP et par identifiant ciblé,
     * pour freiner la force brute, le spam de réservations et l'envoi massif d'emails/SMS.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            $login = strtolower(trim((string) $request->input('login')));

            return [
                Limit::perMinute(5)->by('login:'.sha1($login)),
                Limit::perHour(20)->by('login-hour:'.sha1($login)),
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-password', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes(15, 5)->by('password:'.sha1($email)),
                Limit::perHour(30)->by('password-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-refresh', fn (Request $request) => Limit::perMinute(30)->by('refresh-ip:'.$request->ip()));

        RateLimiter::for('api-user', fn (Request $request) => Limit::perMinute(240)->by(
            'api-user:'.($request->bearerToken() !== null ? sha1($request->bearerToken()) : $request->ip())
        ));

        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute(60)->by('public-read:'.$request->ip()));

        RateLimiter::for('public-booking', function (Request $request) {
            $phone = preg_replace('/\D/', '', (string) $request->input('client_phone')) ?? '';

            return [
                Limit::perHour(10)->by('booking-ip:'.$request->ip()),
                Limit::perDay(5)->by('booking-phone:'.$phone),
            ];
        });

        RateLimiter::for('payment-callback', fn (Request $request) => Limit::perMinute(30)->by('payment-cb:'.$request->ip()));
    }
}
