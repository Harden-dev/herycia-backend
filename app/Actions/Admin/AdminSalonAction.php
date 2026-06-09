<?php

namespace App\Actions\Admin;

use App\Data\Admin\UpdateAdminSalonData;
use App\Models\Salon;
use App\Repositories\Contracts\SalonRepositoryInterface;

class AdminSalonAction
{
    public function __construct(
        private SalonRepositoryInterface $salonRepository,
    ) {}

    public function index(
        int $perPage = 15,
        ?string $search = null,
        ?string $city = null,
        ?string $planId = null,
        ?bool $isActive = null,
        ?bool $isSuspended = null,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        return $this->salonRepository->paginateForAdmin(
            $perPage,
            $search,
            $city,
            $planId,
            $isActive,
            $isSuspended,
        );
    }

    public function show(string $salonId): Salon
    {
        $salon = $this->salonRepository->findByIdWithLatestSubscription($salonId);

        if (! $salon) {
            throw new \RuntimeException('Salon introuvable.');
        }

        return $salon;
    }

    public function update(string $salonId, UpdateAdminSalonData $data): Salon
    {
        $salon = $this->show($salonId);
        $payload = $data->toArray();

        if ($payload === []) {
            throw new \RuntimeException('Aucune donnée à mettre à jour.');
        }

        if (isset($payload['whatsapp_number']) && $payload['whatsapp_number'] !== null
            && $this->salonRepository->existsByWhatsappNumber($payload['whatsapp_number'], $salon->id)) {
            throw new \RuntimeException('Ce numéro WhatsApp est déjà associé à un autre salon.');
        }

        return $this->salonRepository->update($salon, $payload);
    }

    public function suspend(string $salonId): Salon
    {
        $salon = $this->show($salonId);

        return $this->salonRepository->update($salon, [
            'suspended_at' => now(),
        ]);
    }

    public function deactivate(string $salonId): Salon
    {
        $salon = $this->show($salonId);

        return $this->salonRepository->update($salon, [
            'is_active' => false,
        ]);
    }

    public function destroy(string $salonId): void
    {
        $salon = $this->show($salonId);
        $this->salonRepository->delete($salon);
    }
}
