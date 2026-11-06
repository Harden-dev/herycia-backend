<?php

namespace App\Exceptions;

use Exception;

/**
 * La clé d'idempotence a déjà servi pour une requête au contenu différent (autre plan).
 */
class IdempotencyKeyMismatchException extends Exception
{
    public function __construct()
    {
        parent::__construct('Cette clé d\'idempotence a déjà été utilisée pour une autre requête. Générez une nouvelle clé.');
    }
}
