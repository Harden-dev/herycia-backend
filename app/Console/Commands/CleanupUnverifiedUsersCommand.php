<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupUnverifiedUsersCommand extends Command
{
    protected $signature = 'users:cleanup-unverified';

    protected $description = '[Désactivée] Ancienne purge des comptes non vérifiés';

    public function handle(): int
    {
        // Désactivée (audit C4) : is_active = false désigne désormais des employés désactivés
        // ou des comptes bloqués par le super admin. Les supprimer effaçait leur historique
        // et échouait sur la contrainte appointments.user_id.
        $this->warn('Commande désactivée : aucun compte n\'est supprimé (voir docs/AUDIT_SECURITE_ARCHITECTURE.md, C4).');

        return self::SUCCESS;
    }
}
