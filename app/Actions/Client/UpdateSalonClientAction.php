<?php

namespace App\Actions\Client;

use App\Data\Client\UpdateSalonClientData;
use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Salon\SalonContextService;

class UpdateSalonClientAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private ClientRepositoryInterface $clientRepository,
    ) {}

    public function execute(string $clientId, UpdateSalonClientData $data): Client
    {
        $client = $this->salonContext->resolveSalonClient($clientId);
        $payload = $data->toArray();

        if ($payload === []) {
            throw new \RuntimeException('Aucune donnée à mettre à jour.');
        }

        if (isset($payload['phone']) && $this->clientRepository->existsByPhoneForSalon($payload['phone'], $client->salon_id, $client->id)) {
            throw new \RuntimeException('Ce numéro de téléphone est déjà enregistré pour un client de ce salon.');
        }

        $this->clientRepository->update($client, $payload);

        return $client->refresh();
    }
}
