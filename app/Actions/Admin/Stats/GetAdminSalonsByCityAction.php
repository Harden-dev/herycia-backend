<?php

namespace App\Actions\Admin\Stats;

use App\Repositories\Contracts\AdminStatsRepositoryInterface;

class GetAdminSalonsByCityAction
{
    public function __construct(
        private AdminStatsRepositoryInterface $adminStatsRepository,
    ) {}

    /** @return array<string, int> */
    public function execute(): array
    {
        return $this->adminStatsRepository->countSalonsByCity();
    }
}
