<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// users:cleanup-unverified n'est plus planifiée (audit C4) : l'inscription crée des comptes actifs,
// les seuls comptes inactifs sont des employés désactivés ou des comptes bloqués, qu'il ne faut pas supprimer.

// Filet de sécurité Paystack : confirme les paiements dont ni le callback ni le webhook ne sont arrivés.
Schedule::command('paystack:reconcile')->everyFifteenMinutes()->withoutOverlapping();

// File d'attente : absents automatiques et SMS « c'est bientôt votre tour ».
Schedule::command('queue:mark-no-shows')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:notify-soon')->everyMinute()->withoutOverlapping();
