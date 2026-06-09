<?php

namespace App\Actions\Salon;

use App\Data\Salon\SalonDetailData;
use App\Repositories\Contracts\SalonRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Services\Salon\SalonContextService;
use App\Services\Storage\SalonLogoStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UploadSalonLogoAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private SalonRepositoryInterface $salonRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private SalonLogoStorageService $logoStorage,
    ) {}

    public function execute(UploadedFile $logo): SalonDetailData
    {
        $newLogoPath = null;

        try {
            return DB::transaction(function () use ($logo, &$newLogoPath) {
                $salon = $this->salonContext->resolveAuthenticatedSalon();

                if ($salon->logo_url) {
                    $this->logoStorage->delete($salon->logo_url);
                }

                $newLogoPath = $this->logoStorage->upload($logo);
                $salon = $this->salonRepository->update($salon, ['logo_url' => $newLogoPath]);

                Log::info('Salon logo uploaded', [
                    'salon_id' => $salon->id,
                    'path' => $newLogoPath,
                ]);

                $subscription = $this->subscriptionRepository->findLatestBySalonId($salon->id);

                return new SalonDetailData(
                    $salon,
                    $subscription,
                    $this->logoStorage->url($salon->logo_url),
                );
            });
        } catch (\Throwable $e) {
            if ($newLogoPath) {
                $this->logoStorage->delete($newLogoPath);
            }

            throw $e;
        }
    }
}
