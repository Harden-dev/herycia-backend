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

];
