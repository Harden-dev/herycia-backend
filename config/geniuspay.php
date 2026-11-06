<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GeniusPay — paiement des abonnements salon
    |--------------------------------------------------------------------------
    | Clés : dashboard GeniusPay > Paramètres > API. Le mode (sandbox / live) est
    | déterminé par le préfixe des clés (pk_sandbox_… / pk_live_…), pas par l'URL.
    | Les montants sont envoyés en unités entières XOF (5 250 F → 5250), sans ×100.
    */
    'public_key' => env('GENIUSPAY_PUBLIC_KEY'),
    'secret_key' => env('GENIUSPAY_SECRET_KEY'),

    // Secret de signature des webhooks (whsec_…), fourni une seule fois à la création du webhook.
    'webhook_secret' => env('GENIUSPAY_WEBHOOK_SECRET'),

    'base_url' => rtrim(env('GENIUSPAY_BASE_URL', 'https://pay.genius.ci/api/v1/merchant'), '/'),

    'callback_path' => '/api/v1/payment/callback',

    // Fenêtre anti-rejeu des webhooks (secondes) sur X-Webhook-Timestamp.
    'webhook_tolerance' => (int) env('GENIUSPAY_WEBHOOK_TOLERANCE', 300),

    // Écart toléré (en %) sous le prix attendu, pour absorber les frais prélevés côté marchand
    // (ex. Wave 1,5 % + GeniusPay 1 % + 100 XOF : 5250 → ~5018). Au-delà : needs_review.
    'amount_tolerance_percent' => (float) env('GENIUSPAY_AMOUNT_TOLERANCE_PERCENT', 8),

    // Durée de vie appliquée au lien de paiement quand GeniusPay ne renvoie pas expires_at, et
    // plafond pris en compte pour le rejeu et la limite de liens ouverts (contre-audit N4).
    'checkout_ttl_minutes' => (int) env('GENIUSPAY_CHECKOUT_TTL_MINUTES', 30),

    // Timeout HTTP (secondes) des vérifications faites en arrière-plan (réconciliation).
    'reconcile_timeout' => (int) env('GENIUSPAY_RECONCILE_TIMEOUT', 8),

    // Passages de réconciliation (toutes les 15 min) sur un remboursement annoncé mais non confirmé
    // par l'API ; au-delà, la relecture s'arrête avec une alerte unique (96 ≈ 24 h).
    'refund_check_max_attempts' => (int) env('GENIUSPAY_REFUND_CHECK_MAX_ATTEMPTS', 96),

    // File dédiée aux traitements de paiement (webhooks, réconciliation, alertes).
    'queue' => env('GENIUSPAY_QUEUE', 'payments'),

    // Destinataire des alertes paiement (needs_review, remboursement après succès).
    'alert_email' => env('PAYMENT_ALERT_EMAIL'),

];
