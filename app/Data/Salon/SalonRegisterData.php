<?php

namespace App\Data\Salon;

use App\Http\Requests\V1\Salon\SalonRegisterRequest;

readonly class SalonRegisterData
{
    public function __construct(
        public string $salonName,
        public string $city,
        public string $whatsappNumber,
        public string $adminName,
        public string $phone,
        public string $password,
        public string $planCode,
    ) {}

    public static function fromRequest(SalonRegisterRequest $request): self
    {
        return new self(
            salonName: $request->string('salon_name')->trim()->toString(),
            city: $request->string('city')->toString(),
            whatsappNumber: $request->string('whatsapp_number')->toString(),
            adminName: $request->string('admin_name')->trim()->toString(),
            phone: $request->string('phone')->toString(),
            password: $request->string('password')->toString(),
            planCode: $request->string('plan_code')->toString(),
        );
    }
}
