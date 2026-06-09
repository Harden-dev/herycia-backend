<?php

namespace App\Services\Salon;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\SalonRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\AuthService;

class SalonContextService
{
    public function __construct(
        private AuthService $authService,
        private SalonRepositoryInterface $salonRepository,
        private UserRepositoryInterface $userRepository,
        private ClientRepositoryInterface $clientRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
        private ServiceRepositoryInterface $serviceRepository,
    ) {}

    public function resolveAuthenticatedSalon(): Salon
    {
        $user = $this->authService->getAuthenticatedUser();

        if ($user->salon_id === null) {
            throw new \RuntimeException('Aucun salon associé à ce compte.');
        }

        $salon = $this->salonRepository->findById($user->salon_id);

        if ($salon === null) {
            throw new \RuntimeException('Salon introuvable.');
        }

        return $salon;
    }

    public function resolveSalonEmployee(string $employeeId): User
    {
        $salon = $this->resolveAuthenticatedSalon();
        $employee = $this->userRepository->findByIdForSalon($employeeId, $salon->id);

        if ($employee === null) {
            throw new \RuntimeException('Employé introuvable.');
        }

        return $employee;
    }

    public function resolveSalonClient(string $clientId): Client
    {
        $salon = $this->resolveAuthenticatedSalon();
        $client = $this->clientRepository->findByIdForSalon($clientId, $salon->id);

        if ($client === null) {
            throw new \RuntimeException('Client introuvable.');
        }

        return $client;
    }

    public function resolveSalonAppointment(string $appointmentId): Appointment
    {
        $salon = $this->resolveAuthenticatedSalon();
        $appointment = $this->appointmentRepository->findByIdForSalon($appointmentId, $salon->id);

        if ($appointment === null) {
            throw new \RuntimeException('Rendez-vous introuvable.');
        }

        return $appointment;
    }

    public function resolveSalonService(string $serviceId): Service
    {
        $salon = $this->resolveAuthenticatedSalon();
        $service = $this->serviceRepository->findByIdForSalon($serviceId, $salon->id);

        if ($service === null) {
            throw new \RuntimeException('Service introuvable.');
        }

        return $service;
    }
}
