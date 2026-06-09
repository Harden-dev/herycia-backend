<?php

namespace App\Actions\Admin\Salon;

use App\Repositories\Contracts\SalonRepositoryInterface;
use RuntimeException;

class DeleteAdminSalonAction
{
    public function __construct(
        private SalonRepositoryInterface $salonRepository,
    ) {}

    public function execute(string $id): void
    {
        $salon = $this->salonRepository->findById($id);

        if (! $salon) {
            throw new RuntimeException('Salon introuvable.');
        }

        if (! $this->salonRepository->delete($salon)) {
            throw new RuntimeException('Impossible de supprimer ce salon.');
        }
    }
}
