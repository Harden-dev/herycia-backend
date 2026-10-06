<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frontend public (Vue.js)
    |--------------------------------------------------------------------------
    |
    | URL de base du frontend public (pages /booking/{slug} et /rdv/{token}).
    |
    */
    'frontend_url' => rtrim(env('FRONTEND_URL', 'https://salono.ci'), '/'),

    /*
    |--------------------------------------------------------------------------
    | QR code réservation (PNG)
    |--------------------------------------------------------------------------
    */
    'booking_qr_size' => (int) env('BOOKING_QR_SIZE', 300),

    /*
    |--------------------------------------------------------------------------
    | Super admin (seed)
    |--------------------------------------------------------------------------
    */
    // Horizon maximal de réservation publique, en jours (audit H5)
    'booking_max_days_ahead' => (int) env('BOOKING_MAX_DAYS_AHEAD', 90),

    // File d'attente : délai après l'heure du RDV avant de marquer absent un client jamais arrivé
    'auto_no_show_after_minutes' => (int) env('QUEUE_AUTO_NO_SHOW_AFTER_MINUTES', 60),

    // File d'attente : SMS « c'est bientôt votre tour » quand le passage est estimé dans ce délai
    'queue_soon_notify_minutes' => (int) env('QUEUE_SOON_NOTIFY_MINUTES', 10),

    'subscription_trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 7),

    /** Jours avant expiration où un renouvellement du même plan est autorisé */
    'subscription_renewal_window_days' => (int) env('SUBSCRIPTION_RENEWAL_WINDOW_DAYS', 7),

    // Désactivé par défaut : ne jamais activer en production (abonnements gratuits).
    'simulate_subscription_payments' => (bool) env('SIMULATE_SUBSCRIPTION_PAYMENTS', false),

    /*
    | Proxies de confiance (load balancer, Cloudflare…) : IP/CIDR séparés par des virgules, ou "*".
    | Vide = aucun proxy de confiance (audit M6).
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL', 'superadmin@salono.ci'),
        'phone' => env('SUPER_ADMIN_PHONE'),
        // Aucun mot de passe par défaut : le seeder refuse de s'exécuter sans valeur robuste.
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

];
