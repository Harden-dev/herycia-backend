<?php

namespace App\Actions\Salon;

use App\Data\Salon\SalonDetailData;
use App\Data\Salon\UpdateSalonData;
use App\Repositories\Contracts\SalonRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Salon\SalonContextService;
use App\Services\Storage\SalonLogoStorageService;
use Illuminate\Support\Facades\DB;

class UpdateSalonAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private SalonRepositoryInterface $salonRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SalonLogoStorageService $logoStorage,
    ) {}

    public function execute(UpdateSalonData $data): SalonDetailData
    {
        $payload = $data->toArray();

        if ($payload === []) {
            throw new \RuntimeException('Aucune donnée à mettre à jour.');
        }

        $salon = DB::transaction(function () use ($payload) {
            $salon = $this->salonContext->resolveAuthenticatedSalon();

            if (isset($payload['whatsapp_number'])
                && $this->salonRepository->existsByWhatsappNumber($payload['whatsapp_number'], $salon->id)) {
                throw new \RuntimeException('Ce numéro WhatsApp est déjà associé à un salon.');
            }

            return $this->salonRepository->update($salon, $payload);
        });

        // Hors transaction : l'accès au stockage (exists) ne peut pas annuler la mise à jour.
        return new SalonDetailData(
            $salon,
            $this->subscriptionRepository->findLatestBySalonId($salon->id),
            $this->logoStorage->url($salon->logo_url),
        );
    }
}
