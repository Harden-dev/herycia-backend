<?php

namespace App\Actions\Service;

use App\Data\Service\CreateSalonServiceData;
use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Salon\SalonContextService;

class CreateSalonServiceAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private ServiceRepositoryInterface $serviceRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function execute(CreateSalonServiceData $data): Service
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $subscription = $this->subscriptionRepository->findLatestBySalonId($salon->id);

        if ($subscription?->plan === null) {
            throw new \RuntimeException('Aucun plan actif pour ce salon.');
        }

        $activeServices = $this->serviceRepository->countActiveBySalonId($salon->id);

        if ($activeServices >= $subscription->plan->max_services) {
            throw new \RuntimeException('Limite de prestations atteinte pour votre plan.');
        }

        return $this->serviceRepository->create([
            'salon_id' => $salon->id,
            'name' => $data->name,
            'duration_min' => $data->durationMin,
            'price' => $data->price,
            'is_active' => true,
        ]);
    }
}
