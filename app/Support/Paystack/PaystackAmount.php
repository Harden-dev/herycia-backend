<?php

namespace App\Support\Paystack;

class PaystackAmount
{
    public static function multiplier(): int
    {
        return max(1, (int) config('paystack.amount_multiplier', 100));
    }

    /** Montant affiché (FCFA) → unité Paystack */
    public static function toPaystack(int $amountFcfa): int
    {
        return $amountFcfa * self::multiplier();
    }

    /** Unité Paystack → montant affiché (FCFA) */
    public static function fromPaystack(int $paystackAmount): int
    {
        return intdiv($paystackAmount, self::multiplier());
    }
}
