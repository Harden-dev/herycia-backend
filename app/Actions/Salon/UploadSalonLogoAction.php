<?php

namespace App\Actions\Salon;

use App\Data\Salon\SalonDetailData;
use App\Jobs\Storage\DeleteSalonLogoJob;
use App\Repositories\Contracts\SalonRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Salon\SalonContextService;
use App\Services\Storage\SalonLogoStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class UploadSalonLogoAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private SalonRepositoryInterface $salonRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SalonLogoStorageService $logoStorage,
    ) {}

    /**
     * Le nouveau fichier est écrit avant la mise à jour du salon (une seule écriture BDD, sans
     * transaction ouverte pendant l'accès au stockage) ; l'ancien n'est supprimé qu'après, en file.
     */
    public function execute(UploadedFile $logo): SalonDetailData
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $previousLogoPath = $salon->logo_url;
        $newLogoPath = $this->logoStorage->upload($logo);

        try {
            $salon = $this->salonRepository->update($salon, ['logo_url' => $newLogoPath]);
        } catch (\Throwable $e) {
            $this->logoStorage->delete($newLogoPath);

            throw $e;
        }

        if ($previousLogoPath) {
            DeleteSalonLogoJob::dispatch($previousLogoPath);
        }

        Log::info('Salon logo uploaded', [
            'salon_id' => $salon->id,
            'path' => $newLogoPath,
        ]);

        return new SalonDetailData(
            $salon,
            $this->subscriptionRepository->findLatestBySalonId($salon->id),
            $this->logoStorage->url($salon->logo_url),
        );
    }
}
