<?php

namespace App\Data\Client;

use App\Http\Requests\V1\Client\UpdateSalonClientRequest;

readonly class UpdateSalonClientData
{
    public function __construct(
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $whatsappId = null,
    ) {}

    public static function fromRequest(UpdateSalonClientRequest $request): self
    {
        return new self(
            name: $request->filled('name') ? $request->string('name')->trim()->toString() : null,
            phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
            whatsappId: $request->has('whatsapp_id')
                ? ($request->filled('whatsapp_id') ? $request->string('whatsapp_id')->toString() : null)
                : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'phone' => $this->phone,
            'whatsapp_id' => $this->whatsappId,
        ], fn (mixed $value) => $value !== null);
    }
}
