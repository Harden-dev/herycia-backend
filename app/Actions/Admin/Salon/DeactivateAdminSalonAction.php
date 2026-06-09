<?php

namespace App\Actions\Admin\Salon;

use App\Models\Salon;
use App\Repositories\Contracts\SalonRepositoryInterface;
use RuntimeException;

class DeactivateAdminSalonAction
{
    public function __construct(
        private SalonRepositoryInterface $salonRepository,
    ) {}

    public function execute(string $id): Salon
    {
        $salon = $this->salonRepository->findById($id);

        if (! $salon) {
            throw new RuntimeException('Salon introuvable.');
        }

        $this->salonRepository->update($salon, [
            'is_active' => false,
        ]);

        return $salon->refresh();
    }
}
