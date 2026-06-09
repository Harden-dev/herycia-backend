<?php

namespace App\Data\Admin;

use App\Http\Requests\V1\Admin\UpdateAdminSalonRequest;

readonly class UpdateAdminSalonData
{
    public function __construct(
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $whatsappNumber = null,
        public ?string $city = null,
        public ?string $address = null,
    ) {}

    public static function fromRequest(UpdateAdminSalonRequest $request): self
    {
        return new self(
            name: $request->has('name') ? $request->string('name')->trim()->toString() : null,
            phone: $request->has('phone') ? $request->input('phone') : null,
            whatsappNumber: $request->has('whatsapp_number') ? $request->input('whatsapp_number') : null,
            city: $request->has('city') ? $request->string('city')->trim()->toString() : null,
            address: $request->has('address') ? $request->input('address') : null,
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
        ], fn ($value) => $value !== null);
    }
}
