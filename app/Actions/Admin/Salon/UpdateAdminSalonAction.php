<?php

namespace App\Actions\Admin\Salon;

use App\Data\Admin\UpdateAdminSalonData;
use App\Models\Salon;
use App\Repositories\Contracts\SalonRepositoryInterface;
use RuntimeException;

class UpdateAdminSalonAction
{
    public function __construct(
        private SalonRepositoryInterface $salonRepository,
    ) {}

    public function execute(string $id, UpdateAdminSalonData $data): Salon
    {
        $salon = $this->salonRepository->findById($id);

        if (! $salon) {
            throw new RuntimeException('Salon introuvable.');
        }

        $payload = $data->toArray();

        if ($payload === []) {
            throw new RuntimeException('Aucune donnée à mettre à jour.');
        }

        if (isset($payload['whatsapp_number'])
            && $this->salonRepository->existsByWhatsappNumber($payload['whatsapp_number'], $salon->id)) {
            throw new RuntimeException('Ce numéro WhatsApp est déjà associé à un salon.');
        }

        $this->salonRepository->update($salon, $payload);

        return $salon->refresh();
    }
}
