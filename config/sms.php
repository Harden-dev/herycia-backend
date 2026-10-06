<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP par SMS (inscription / renvoi code)
    |--------------------------------------------------------------------------
    |
    | Si false : aucun SMS n'est envoyé (comportement par défaut). Les variables
    | Twilio peuvent rester vides.
    |
    */
    'otp_enabled' => filter_var(env('SMS_OTP_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Notifications SMS (RDV, bienvenue salon)
    |--------------------------------------------------------------------------
    */
    'notifications_enabled' => filter_var(env('SMS_NOTIFICATIONS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Plafond de SMS de rendez-vous par salon et par 24 h (protection contre le « SMS pumping », audit H5)
    'daily_limit_per_salon' => (int) env('SMS_DAILY_LIMIT_PER_SALON', 200),

];
