<?php

namespace App\Services\User;

use App\Mail\User\WelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserNotification
{
    public function __construct(public User $user)
    {
        $this->user = $user;
    }

    public function sendWelcomeEmail()
    {
        try {
            Mail::to($this->user->email)->queue(new WelcomeMail($this->user));
            Log::info("Welcome email sent", ['user_id' => $this->user->id, 'email' => $this->user->email]);
        } catch (\Exception $e) {
            Log::error("Error sending welcome email to {$this->user->email}: {$e->getMessage()}");
        }
    }

    // public function sendWelcomeSMS()
    // {
    //     try {
    //         Log::info("Sending welcome SMS to {$this->user->phone} with message: Welcome to our platform");
    //     } catch (\Exception $e) {
    //         Log::error("Error sending welcome SMS to {$this->user->phone}: {$e->getMessage()}");
    //     }
    // }
}
