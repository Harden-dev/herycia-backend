<?php

namespace App\Data\Service;

use App\Http\Requests\V1\Service\CreateSalonServiceRequest;

readonly class CreateSalonServiceData
{
    public function __construct(
        public string $name,
        public int $durationMin,
        public int $price,
    ) {}

    public static function fromRequest(CreateSalonServiceRequest $request): self
    {
        return new self(
            name: $request->string('name')->trim()->toString(),
            durationMin: $request->integer('duration_min'),
            price: $request->integer('price'),
        );
    }
}
