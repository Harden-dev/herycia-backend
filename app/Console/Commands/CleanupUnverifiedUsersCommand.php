<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CleanupUnverifiedUsersCommand extends Command
{
    protected $signature = 'users:cleanup-unverified';

    protected $description = 'Supprime les comptes non vérifiés depuis plus de 24 h';

    public function handle(): int
    {
        $cutoff = now()->subHours(24);

        $deleted = User::query()
            ->where('is_active', false)
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Supprimé {$deleted} compte(s) non vérifié(s).");

        return self::SUCCESS;
    }
}
