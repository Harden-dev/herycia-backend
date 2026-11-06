<?php

namespace App\Enums;

enum BillingPaymentMethod: string
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case Cash = 'cash';
    case GeniusPay = 'geniuspay';
    /** Conservé pour l'historique des paiements passés par Paystack. */
    case Paystack = 'paystack';

    /**
     * GeniusPay renvoie selon l'endpoint payment_method = « wave » (GET) ou
     * « mobile_money » + provider = « wave » (webhook) : le fournisseur prime.
     */
    public static function fromGeniusPay(?string $paymentMethod, ?string $provider = null): self
    {
        foreach ([$provider, $paymentMethod] as $value) {
            $method = match ($value) {
                'wave' => self::Wave,
                'orange_money' => self::OrangeMoney,
                'mtn_money', 'mtn_momo', 'moov_money', 'pawapay', 'mobile_money' => self::MobileMoney,
                'card', 'paystack' => self::Card,
                default => null,
            };

            if ($method !== null) {
                return $method;
            }
        }

        return self::GeniusPay;
    }
}
