<?php

namespace App\Actions\Dashboard;

use App\Data\Dashboard\DashboardActivityItemData;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Services\Salon\SalonContextService;

class GetDashboardActivityAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    /** @return list<DashboardActivityItemData> */
    public function execute(int $limit = 5): array
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $items = $this->dashboardRepository->getRecentActivity($salon->id, $limit);

        return array_map(
            static fn (array $item): DashboardActivityItemData => new DashboardActivityItemData(
                id: $item['id'],
                type: $item['type'],
                message: $item['message'],
                createdAt: $item['created_at'],
            ),
            $items,
        );
    }
}
