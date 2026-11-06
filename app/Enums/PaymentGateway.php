<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case GeniusPay = 'geniuspay';
    /** Transactions historiques, antérieures au passage à GeniusPay. */
    case Paystack = 'paystack';
}
