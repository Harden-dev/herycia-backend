<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// users:cleanup-unverified n'est plus planifiée (audit C4) : l'inscription crée des comptes actifs,
// les seuls comptes inactifs sont des employés désactivés ou des comptes bloqués, qu'il ne faut pas supprimer.
