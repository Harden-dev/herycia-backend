<?php

namespace App\Data\Admin;

use App\Http\Requests\V1\Admin\CreateAdminPlanRequest;

readonly class CreateAdminPlanData
{
    public function __construct(
        public string $name,
        public string $code,
        public int $priceFcfa,
        public int $maxEmployees,
        public int $maxServices,
        public bool $hasOnlineBooking,
        public bool $hasAnalytics,
        public bool $hasMultiBranch,
    ) {}

    public static function fromRequest(CreateAdminPlanRequest $request): self
    {
        return new self(
            name: $request->string('name')->trim()->toString(),
            code: $request->string('code')->trim()->toString(),
            priceFcfa: (int) $request->input('price_fcfa'),
            maxEmployees: (int) $request->input('max_employees'),
            maxServices: (int) $request->input('max_services'),
            hasOnlineBooking: $request->boolean('has_online_booking'),
            hasAnalytics: $request->boolean('has_analytics'),
            hasMultiBranch: $request->boolean('has_multi_branch'),
        );
    }
}
