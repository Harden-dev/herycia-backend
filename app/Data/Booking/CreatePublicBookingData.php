<?php

namespace App\Data\Booking;

use App\Http\Requests\Booking\CreatePublicBookingRequest;
use Carbon\Carbon;

readonly class CreatePublicBookingData
{
    public function __construct(
        public string $clientPhone,
        public string $clientName,
        public string $serviceId,
        public string $userId,
        public Carbon $scheduledAt,
    ) {}

    public static function fromRequest(CreatePublicBookingRequest $request): self
    {
        return new self(
            clientPhone: $request->string('client_phone')->toString(),
            clientName: $request->string('client_name')->trim()->toString(),
            serviceId: $request->string('service_id')->toString(),
            userId: $request->string('user_id')->toString(),
            scheduledAt: Carbon::parse($request->string('scheduled_at')->toString()),
        );
    }
}
