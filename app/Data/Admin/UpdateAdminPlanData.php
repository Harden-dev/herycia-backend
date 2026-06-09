<?php

namespace App\Data\Admin;

use App\Http\Requests\V1\Admin\UpdateAdminPlanRequest;

readonly class UpdateAdminPlanData
{
    public function __construct(
        public ?string $name = null,
        public ?string $code = null,
        public ?int $priceFcfa = null,
        public ?int $maxEmployees = null,
        public ?int $maxServices = null,
        public ?bool $hasOnlineBooking = null,
        public ?bool $hasAnalytics = null,
        public ?bool $hasMultiBranch = null,
    ) {}

    public static function fromRequest(UpdateAdminPlanRequest $request): self
    {
        return new self(
            name: $request->has('name') ? $request->string('name')->trim()->toString() : null,
            code: $request->has('code') ? $request->string('code')->trim()->toString() : null,
            priceFcfa: $request->has('price_fcfa') ? (int) $request->input('price_fcfa') : null,
            maxEmployees: $request->has('max_employees') ? (int) $request->input('max_employees') : null,
            maxServices: $request->has('max_services') ? (int) $request->input('max_services') : null,
            hasOnlineBooking: $request->has('has_online_booking') ? $request->boolean('has_online_booking') : null,
            hasAnalytics: $request->has('has_analytics') ? $request->boolean('has_analytics') : null,
            hasMultiBranch: $request->has('has_multi_branch') ? $request->boolean('has_multi_branch') : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'code' => $this->code,
            'price_fcfa' => $this->priceFcfa,
            'max_employees' => $this->maxEmployees,
            'max_services' => $this->maxServices,
            'has_online_booking' => $this->hasOnlineBooking,
            'has_analytics' => $this->hasAnalytics,
            'has_multi_branch' => $this->hasMultiBranch,
        ], fn ($value) => $value !== null);
    }
}
