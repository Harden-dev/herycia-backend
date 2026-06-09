<?php

namespace App\Data\Service;

use App\Http\Requests\V1\Service\UpdateSalonServiceRequest;

readonly class UpdateSalonServiceData
{
    public function __construct(
        public ?string $name = null,
        public ?int $durationMin = null,
        public ?int $price = null,
    ) {}

    public static function fromRequest(UpdateSalonServiceRequest $request): self
    {
        return new self(
            name: $request->filled('name') ? $request->string('name')->trim()->toString() : null,
            durationMin: $request->filled('duration_min') ? $request->integer('duration_min') : null,
            price: $request->filled('price') ? $request->integer('price') : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'duration_min' => $this->durationMin,
            'price' => $this->price,
        ], fn (mixed $value) => $value !== null);
    }
}
