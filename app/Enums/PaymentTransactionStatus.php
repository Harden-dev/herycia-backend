<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    /** Paiement encaissé mais non activé automatiquement (montant, devise, environnement, activation impossible). */
    case NeedsReview = 'needs_review';
    /** Paiement remboursé par le prestataire après encaissement. */
    case Refunded = 'refunded';
}
