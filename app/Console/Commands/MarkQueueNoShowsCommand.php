<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Console\Command;

/**
 * File d'attente V2 : passe en « absent » les rendez-vous du jour jamais arrivés après le délai
 * configuré (60 min par défaut). Le client peut encore s'enregistrer plus tard dans la journée :
 * il est alors traité comme un retardataire (voir QueueCheckInService).
 *
 * Limité au jour en cours pour ne pas réécrire l'historique des salons qui ne tiennent pas
 * leurs statuts à jour.
 */
class MarkQueueNoShowsCommand extends Command
{
    protected $signature = 'queue:mark-no-shows';

    protected $description = 'Marque absents les rendez-vous du jour jamais arrivés';

    public function handle(): int
    {
        $delay = max(15, (int) config('salono.auto_no_show_after_minutes', 60));

        $count = Appointment::query()
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
            ->whereNull('checked_in_at')
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->where('scheduled_at', '<=', now()->subMinutes($delay))
            ->update(['status' => AppointmentStatus::NoShow]);

        $this->info("Rendez-vous marqués absents : {$count}");

        return self::SUCCESS;
    }
}
