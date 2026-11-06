<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte d'exploitation sur un paiement d'abonnement qui exige une intervention
 * (vérification manuelle, remboursement après activation). Envoyée en file.
 */
class PaymentAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, scalar|null>  $context
     */
    public function __construct(
        public string $reason,
        public string $reference,
        public array $context = [],
    ) {
        $this->onQueue(config('geniuspay.queue'));
        // Jamais envoyée pour une écriture annulée, et son échec ne peut pas annuler la transaction.
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('[Paiement] Action requise : '.$this->reason.' — '.$this->reference)
            ->line('Un paiement d\'abonnement nécessite une vérification manuelle.')
            ->line('Motif : '.$this->reason)
            ->line('Référence : '.$this->reference);

        foreach ($this->context as $key => $value) {
            $message->line($key.' : '.($value ?? '—'));
        }

        return $message;
    }
}
