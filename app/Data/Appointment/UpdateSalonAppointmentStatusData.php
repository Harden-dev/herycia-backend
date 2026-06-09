<?php

namespace App\Data\Appointment;

use App\Enums\AppointmentStatus;
use App\Http\Requests\V1\Appointment\UpdateSalonAppointmentStatusRequest;

readonly class UpdateSalonAppointmentStatusData
{
    public function __construct(
        public AppointmentStatus $status,
    ) {}

    public static function fromRequest(UpdateSalonAppointmentStatusRequest $request): self
    {
        return new self(
            status: AppointmentStatus::from($request->string('status')->toString()),
        );
    }
}
