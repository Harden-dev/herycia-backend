<?php

namespace App\Console\Commands;

use App\Enums\QueueEntryStatus;
use App\Jobs\Sms\SendQueueSoonSms;
use App\Models\QueueEntry;
use App\Services\Queue\QueueService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * File d'attente V2 : prévient par SMS, une seule fois, le client qui est le prochain
 * ou dont le passage est estimé dans les prochaines minutes (10 par défaut).
 */
class NotifyQueueSoonCommand extends Command
{
    protected $signature = 'queue:notify-soon';

    protected $description = 'Envoie le SMS « c\'est bientôt votre tour » aux prochains clients de la file';

    public function handle(QueueService $queueService): int
    {
        $now = CarbonImmutable::now();
        $threshold = $now->addMinutes(max(1, (int) config('salono.queue_soon_notify_minutes', 10)));
        $notified = 0;

        // Une simulation par coiffeur ayant au moins un client à prévenir.
        $pairs = QueueEntry::query()
            ->where('status', QueueEntryStatus::Waiting)
            ->whereNull('soon_notified_at')
            ->where('arrived_at', '>=', $now->startOfDay())
            ->whereNotNull('user_id')
            ->select(['salon_id', 'user_id'])
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            foreach ($queueService->boardForStylist($pair->salon_id, $pair->user_id, $now) as $row) {
                $entry = $row['entry'];

                if ($entry->status !== QueueEntryStatus::Waiting || $entry->soon_notified_at !== null) {
                    continue;
                }

                $isNext = $row['position'] === 1;
                $isSoon = $row['estimated_start_at'] !== null && $row['estimated_start_at']->lessThanOrEqualTo($threshold);

                if (! $isNext && ! $isSoon) {
                    continue;
                }

                // Marqué avant l'envoi : jamais deux SMS pour le même passage, même si la commande se chevauche.
                $updated = QueueEntry::query()
                    ->whereKey($entry->id)
                    ->whereNull('soon_notified_at')
                    ->update(['soon_notified_at' => $now]);

                if ($updated === 1) {
                    SendQueueSoonSms::dispatch($entry);
                    $notified++;
                }
            }
        }

        $this->info("Clients prévenus : {$notified}");

        return self::SUCCESS;
    }
}
