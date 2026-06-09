<?php

namespace App\Exceptions;

use Exception;

class SubscriptionAlreadyActiveException extends Exception
{
    public function __construct(string $endsAt)
    {
        parent::__construct("Votre abonnement est déjà actif jusqu'au {$endsAt}.");
    }
}
