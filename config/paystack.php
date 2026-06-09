<?php

return [

    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'payment_url' => rtrim(env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'), '/'),
    'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL', 'admin@salono.ci'),

    'callback_path' => '/api/v1/payment/callback',

    /*
    |--------------------------------------------------------------------------
    | Montant API Paystack (XOF)
    |--------------------------------------------------------------------------
    | Paystack exige amount × 100 (convention "subunit" même sans centimes XOF).
    | 5 000 F en base → 500 000 envoyé à Paystack.
    */
    'amount_multiplier' => (int) env('PAYSTACK_AMOUNT_MULTIPLIER', 100),

];
