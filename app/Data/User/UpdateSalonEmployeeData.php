<?php

namespace App\Data\User;

use App\Enums\SalonStaffRole;
use App\Http\Requests\V1\User\UpdateSalonEmployeeRequest;

readonly class UpdateSalonEmployeeData
{
    public function __construct(
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?SalonStaffRole $role = null,
        public ?string $password = null,
    ) {}

    public static function fromRequest(UpdateSalonEmployeeRequest $request): self
    {
        return new self(
            name: $request->filled('name') ? $request->string('name')->trim()->toString() : null,
            phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
            email: $request->has('email') ? ($request->filled('email') ? $request->string('email')->toString() : null) : null,
            role: $request->filled('role') ? SalonStaffRole::from($request->string('role')->toString()) : null,
            password: $request->filled('password') ? $request->string('password')->toString() : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => $this->role?->value,
            'password' => $this->password,
        ], fn (mixed $value) => $value !== null);

        return $payload;
    }
}
