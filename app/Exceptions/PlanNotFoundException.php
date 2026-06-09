<?php

namespace App\Exceptions;

use Exception;

class PlanNotFoundException extends Exception
{
    public function __construct(string $code = 'free')
    {
        parent::__construct("Le plan '{$code}' est introuvable en base de données.");
    }
}
