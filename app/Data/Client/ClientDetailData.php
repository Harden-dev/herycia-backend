<?php

namespace App\Data\Client;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

readonly class ClientDetailData
{
    /** @param Collection<int, \App\Models\Appointment> $appointments */
    /** @param Collection<int, \App\Models\Payment> $payments */
    public function __construct(
        public Client $client,
        public Collection $appointments,
        public Collection $payments,
    ) {}
}
