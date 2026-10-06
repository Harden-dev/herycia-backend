<?php

namespace App\Enums;

enum QueueEntrySource: string
{
    /** Client arrivé à l'heure : passe à l'heure de son rendez-vous. */
    case Appointment = 'appointment';

    /** Client arrivé en retard qui a choisi de passer après le dernier de la liste. */
    case Late = 'late';

    /** Client sans rendez-vous, placé après le dernier de la liste. */
    case WalkIn = 'walk_in';

    /** Passe à l'heure de son rendez-vous (ancré) ; les autres comblent les trous par ordre d'arrivée. */
    public function isAnchored(): bool
    {
        return $this === self::Appointment;
    }
}
