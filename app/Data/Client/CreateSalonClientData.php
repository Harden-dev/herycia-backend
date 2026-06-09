<?php

namespace App\Data\Client;

use App\Http\Requests\V1\Client\CreateSalonClientRequest;

readonly class CreateSalonClientData
{
    public function __construct(
        public string $name,
        public string $phone,
        public ?string $whatsappId = null,
    ) {}

    public static function fromRequest(CreateSalonClientRequest $request): self
    {
        return new self(
            name: $request->string('name')->trim()->toString(),
            phone: $request->string('phone')->toString(),
            whatsappId: $request->filled('whatsapp_id') ? $request->string('whatsapp_id')->toString() : null,
        );
    }
}
