<?php

/*
|--------------------------------------------------------------------------
| CORS (audit M7)
|--------------------------------------------------------------------------
|
| Origines autorisées : CORS_ALLOWED_ORIGINS (séparées par des virgules), par
| exemple "https://salono.ci,https://app.salono.ci". Vide = toutes les origines
| (comportement historique, à éviter en production). L'API utilise des jetons
| Bearer : pas de cookies, donc pas de credentials CORS.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
)));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins !== [] ? $origins : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
