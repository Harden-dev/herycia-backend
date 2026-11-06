<?php

namespace App\Jobs\Storage;

use App\Services\Storage\SalonLogoStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Supprime un ancien logo de salon après le commit du remplacement : un échec de stockage ne
 * peut ni annuler la mise à jour du salon ni laisser le salon pointer vers un fichier supprimé.
 * Idempotent : supprimer un fichier déjà absent ne fait rien.
 */
class DeleteSalonLogoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 30;

    public function __construct(public string $path)
    {
        $this->afterCommit();
    }

    public function handle(SalonLogoStorageService $logoStorage): void
    {
        $logoStorage->delete($this->path);
    }
}
