<?php

namespace App\Actions\Admin\Salon;

use App\Models\Salon;
use App\Repositories\Contracts\SalonRepositoryInterface;
use RuntimeException;

class GetAdminSalonAction
{
    public function __construct(
        private SalonRepositoryInterface $salonRepository,
    ) {}

    public function execute(string $id): Salon
    {
        $salon = $this->salonRepository->findByIdWithLatestSubscription($id);

        if (! $salon) {
            throw new RuntimeException('Salon introuvable.');
        }

        return $salon;
    }
}
