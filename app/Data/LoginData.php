<?php

namespace App\Data;

use App\Http\Requests\V1\User\LoginRequest;

class LoginData
{
    public function __construct(
        public readonly string $login,
        public readonly string $password,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            login: $request->login,
            password: $request->password,
        );
    }

    public function toArray(): array
    {
        return [
            'login' => $this->login,
            'password' => $this->password,
        ];
    }
}
