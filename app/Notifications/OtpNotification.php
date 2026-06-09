<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $code,
        public string $type = 'email',
        public string $purpose = 'verification'
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->type === 'email') {
            $channels[] = 'mail';
        }

        // Pour SMS, on ajoutera le channel plus tard (ex: Nexmo, Twilio, etc.)
        // if ($this->type === 'sms') {
        //     $channels[] = 'nexmo';
        // }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if ($this->purpose === 'password_reset') {
            return (new MailMessage)
                ->subject('Réinitialisation de votre mot de passe')
                ->greeting('Bonjour !')
                ->line('Vous avez demandé la réinitialisation de votre mot de passe.')
                ->line('Votre code de vérification est :')
                ->line('**' . $this->code . '**')
                ->line('Saisissez ce code sur la page de réinitialisation pour continuer.')
                ->line('Ce code est valide pendant 10 minutes.')
                ->line('Si vous n\'avez pas demandé cette réinitialisation, veuillez ignorer ce message.')
                ->salutation('Cordialement, L\'équipe ' . config('app.name'));
        }

        return (new MailMessage)
            ->subject('Votre code de vérification')
            ->greeting('Bonjour !')
            ->line('Votre code de vérification est :')
            ->line('**' . $this->code . '**')
            ->line('Ce code est valide pendant 10 minutes.')
            ->line('Si vous n\'avez pas demandé ce code, veuillez ignorer ce message.')
            ->salutation('Cordialement, L\'équipe ' . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type,
        ];
    }
}
