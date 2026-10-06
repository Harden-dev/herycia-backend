<?php

namespace App\Enums;

enum QueueEntrySource: string
{
    /** Client arrivé à l'heure : passe à l'heure de son rendez-vous. */
    case Appointment = 'appointment';

    /** Client arrivé en retard qui a choisi de passer après le dernier de la liste. */
    case Late = 'late';
}
