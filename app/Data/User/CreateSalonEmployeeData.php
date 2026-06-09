<?php

namespace App\Data\User;

use App\Enums\SalonStaffRole;
use App\Http\Requests\V1\User\CreateSalonEmployeeRequest;

readonly class CreateSalonEmployeeData
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $password,
        public SalonStaffRole $role,
        public ?string $email = null,
    ) {}

    public static function fromRequest(CreateSalonEmployeeRequest $request): self
    {
        return new self(
            name: $request->string('name')->trim()->toString(),
            phone: $request->string('phone')->toString(),
            password: $request->string('password')->toString(),
            role: SalonStaffRole::from($request->string('role')->toString()),
            email: $request->filled('email') ? $request->string('email')->toString() : null,
        );
    }
}
