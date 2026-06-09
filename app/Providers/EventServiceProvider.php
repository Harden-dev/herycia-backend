<?php

namespace App\Providers;

use App\Events\AuditEvent;
use App\Events\SalonRegistered;
use App\Events\UserCreated;
use App\Listeners\LogAuditListener;
use App\Listeners\LogSalonRegistered;
use App\Listeners\SendSalonWelcomeSms;
use App\Listeners\SendUserCreatedNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserCreated::class => [
            SendUserCreatedNotifications::class,
        ],
        SalonRegistered::class => [
            LogSalonRegistered::class,
            SendSalonWelcomeSms::class,
        ],
        AuditEvent::class => [
            LogAuditListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // Désactivé pour éviter les doublons
    }

    /**
     * Get the listener directories that should be used to discover events.
     *
     * @return array<int, string>
     */
    protected function discoverEventsWithin(): array
    {
        return []; // Aucun auto-discovery
    }
}

