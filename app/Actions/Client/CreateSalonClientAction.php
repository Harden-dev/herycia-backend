<?php

namespace App\Actions\Client;

use App\Data\Client\CreateSalonClientData;
use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Salon\SalonContextService;

class CreateSalonClientAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private ClientRepositoryInterface $clientRepository,
    ) {}

    public function execute(CreateSalonClientData $data): Client
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();

        if ($this->clientRepository->existsByPhoneForSalon($data->phone, $salon->id)) {
            throw new \RuntimeException('Ce numéro de téléphone est déjà enregistré pour un client de ce salon.');
        }

        return $this->clientRepository->create([
            'salon_id' => $salon->id,
            'name' => $data->name,
            'phone' => $data->phone,
            'whatsapp_id' => $data->whatsappId,
            'total_visits' => 0,
            'last_visit_at' => null,
            'created_at' => now(),
        ]);
    }
}
