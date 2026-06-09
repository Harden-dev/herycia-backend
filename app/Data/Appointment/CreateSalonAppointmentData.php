<?php

namespace App\Data\Appointment;

use App\Http\Requests\V1\Appointment\CreateSalonAppointmentRequest;
use Carbon\Carbon;

readonly class CreateSalonAppointmentData
{
    public function __construct(
        public string $clientId,
        public string $userId,
        public string $serviceId,
        public Carbon $scheduledAt,
        public ?string $notes = null,
    ) {}

    public static function fromRequest(CreateSalonAppointmentRequest $request): self
    {
        return new self(
            clientId: $request->string('client_id')->toString(),
            userId: $request->string('user_id')->toString(),
            serviceId: $request->string('service_id')->toString(),
            scheduledAt: Carbon::parse($request->string('scheduled_at')->toString()),
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
        );
    }
}
