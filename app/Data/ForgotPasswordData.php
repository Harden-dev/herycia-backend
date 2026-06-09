<?php

namespace App\Data;

use App\Http\Requests\V1\User\ForgotPasswordRequest;

class ForgotPasswordData
{
    public function __construct(
        public readonly string $email,
    ) {}

    public static function fromRequest(ForgotPasswordRequest $request): self
    {
        return new self(
            email: $request->email,
        );
    }
}

