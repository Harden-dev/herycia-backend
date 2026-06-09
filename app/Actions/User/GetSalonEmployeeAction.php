<?php

namespace App\Actions\User;

use App\Models\User;
use App\Services\Salon\SalonContextService;

class GetSalonEmployeeAction
{
    public function __construct(
        private SalonContextService $salonContext,
    ) {}

    public function execute(string $employeeId): User
    {
        return $this->salonContext->resolveSalonEmployee($employeeId);
    }
}
