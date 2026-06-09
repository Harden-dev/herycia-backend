<?php

namespace App\Actions\Salon;

use App\Data\Salon\SalonDetailData;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Salon\SalonContextService;
use App\Services\Storage\SalonLogoStorageService;

class GetSalonAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SalonLogoStorageService $logoStorage,
    ) {}

    public function execute(): SalonDetailData
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();

        $subscription = $this->subscriptionRepository->findLatestBySalonId($salon->id);

        return new SalonDetailData(
            $salon,
            $subscription,
            $this->logoStorage->url($salon->logo_url),
        );
    }
}
