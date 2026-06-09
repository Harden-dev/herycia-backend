<?php

namespace App\Services\Firebase;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private $messaging;

    public function __construct()
    {
        $credentialsPath = config('services.firebase.credentials');
        
        if (!file_exists($credentialsPath)) {
            throw new \Exception("Firebase credentials file not found: {$credentialsPath}");
        }

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $this->messaging = $factory->createMessaging();
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget(MessageTarget::TOKEN, $token)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);

            return true;

        } catch (\Exception $e) {
            Log::error('FCM send error: ' . $e->getMessage(), [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        if (empty($tokens)) {
            return ['success' => 0, 'failed' => 0, 'invalid_tokens' => []];
        }

        try {
            $notification = Notification::create($title, $body);
            $messages = [];

            foreach ($tokens as $token) {
                $messages[] = CloudMessage::withTarget(MessageTarget::TOKEN, $token)
                    ->withNotification($notification)
                    ->withData($data);
            }

            $report = $this->messaging->sendAll($messages);

            $invalidTokens = [];
            foreach ($report->invalidTokens() as $invalidResult) {
                $target = $invalidResult->target();
                if ($target && method_exists($target, 'value')) {
                    $invalidTokens[] = $target->value();
                } elseif (is_string($target)) {
                    $invalidTokens[] = $target;
                }
            }

            return [
                'success' => $report->successes()->count(),
                'failed' => $report->failures()->count(),
                'invalid_tokens' => $invalidTokens,
            ];

        } catch (\Exception $e) {
            Log::error('FCM multicast error: ' . $e->getMessage());

            return ['success' => 0, 'failed' => count($tokens), 'invalid_tokens' => []];
        }
    }
}
