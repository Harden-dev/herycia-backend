<?php

namespace App\Actions\User;

use App\Data\User\UpdateSalonEmployeeData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Salon\SalonContextService;

class UpdateSalonEmployeeAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function execute(string $employeeId, UpdateSalonEmployeeData $data): User
    {
        $employee = $this->salonContext->resolveSalonEmployee($employeeId);
        $payload = $data->toArray();

        if ($payload === []) {
            throw new \RuntimeException('Aucune donnée à mettre à jour.');
        }

        if (isset($payload['phone']) && $this->userRepository->existsByPhone($payload['phone'], $employee->id)) {
            throw new \RuntimeException('Ce numéro de téléphone est déjà utilisé.');
        }

        if (isset($payload['email']) && $payload['email'] !== null
            && $this->userRepository->existsByEmail($payload['email'], $employee->id)) {
            throw new \RuntimeException('Cet email est déjà utilisé.');
        }

        $this->userRepository->update($employee, $payload);

        return $employee->refresh();
    }
}
