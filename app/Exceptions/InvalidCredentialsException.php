<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InvalidCredentialsException extends Exception
{
    public function __construct(string $message = 'Les identifiants fournis sont invalides', int $code = 401, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}
