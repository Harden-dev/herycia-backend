<?php

namespace App\Data;

use App\Http\Requests\V1\User\ResetPasswordRequest;

class ResetPasswordData
{
    public function __construct(
        public readonly string $token,
        public readonly string $email,
        public readonly string $password,
    ) {}

    public static function fromRequest(ResetPasswordRequest $request): self
    {
        return new self(
            token: $request->token,
            email: $request->email,
            password: $request->password,
        );
    }
}

