<?php

namespace App\Actions\Appointment;

use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Services\Salon\SalonContextService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListSalonAppointmentsAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {}

    public function execute(
        ?string $date = null,
        ?string $from = null,
        ?string $to = null,
        ?string $userId = null,
    ): Collection {
        $salon = $this->salonContext->resolveAuthenticatedSalon();

        if ($from !== null && $to !== null) {
            $rangeFrom = Carbon::parse($from)->startOfDay();
            $rangeTo = Carbon::parse($to)->endOfDay();
        } else {
            $day = Carbon::parse($date ?? now()->toDateString());
            $rangeFrom = $day->copy()->startOfDay();
            $rangeTo = $day->copy()->endOfDay();
        }

        if ($userId !== null) {
            $this->salonContext->resolveSalonEmployee($userId);
        }

        return $this->appointmentRepository->getBySalonInRange(
            $salon->id,
            $rangeFrom,
            $rangeTo,
            $userId,
        );
    }
}
