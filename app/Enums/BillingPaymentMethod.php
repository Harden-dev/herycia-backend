<?php

namespace App\Enums;

enum BillingPaymentMethod: string
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case Cash = 'cash';
    case Paystack = 'paystack';

    public static function fromPaystackChannel(?string $channel): self
    {
        return match ($channel) {
            'card' => self::Card,
            'mobile_money', 'ussd', 'bank_transfer' => self::MobileMoney,
            'bank' => self::OrangeMoney,
            default => self::Paystack,
        };
    }
}
