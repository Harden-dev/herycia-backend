<?php

namespace App\Data\Salon;

use App\Http\Requests\V1\Salon\UpdateSalonRequest;

readonly class UpdateSalonData
{
    public function __construct(
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $whatsappNumber = null,
        public ?string $city = null,
        public ?string $address = null,
        public ?int $lateToleranceMinutes = null,
    ) {}

    public static function fromRequest(UpdateSalonRequest $request): self
    {
        return new self(
            name: $request->filled('name') ? $request->string('name')->trim()->toString() : null,
            phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
            whatsappNumber: $request->filled('whatsapp_number') ? $request->string('whatsapp_number')->toString() : null,
            city: $request->filled('city') ? $request->string('city')->toString() : null,
            address: $request->filled('address') ? $request->string('address')->trim()->toString() : null,
            lateToleranceMinutes: $request->filled('late_tolerance_minutes') ? $request->integer('late_tolerance_minutes') : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsappNumber,
            'city' => $this->city,
            'address' => $this->address,
            'late_tolerance_minutes' => $this->lateToleranceMinutes,
        ], fn (mixed $value) => $value !== null);
    }
}
