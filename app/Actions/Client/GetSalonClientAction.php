<?php

namespace App\Actions\Client;

use App\Data\Client\ClientDetailData;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Salon\SalonContextService;

class GetSalonClientAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private ClientRepositoryInterface $clientRepository,
    ) {}

    public function execute(string $clientId): ClientDetailData
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $client = $this->clientRepository->findByIdForSalonWithHistory($clientId, $salon->id);

        if ($client === null) {
            throw new \RuntimeException('Client introuvable.');
        }

        return new ClientDetailData(
            client: $client,
            appointments: $client->appointments,
            payments: $client->payments,
        );
    }
}
