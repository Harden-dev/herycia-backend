<?php

namespace App\Jobs\Sms\Concerns;

use App\Exceptions\SmsDeliveryException;
use App\Services\Sms\SmsDeliveryGuard;

/**
 * Pour les jobs / listeners SMS en file (nécessite InteractsWithQueue) :
 * un échec sûr à réessayer remonte (tries + backoff), un échec définitif ou incertain
 * termine le job sans nouvel essai, pour ne jamais envoyer deux fois le même SMS.
 */
trait SendsSmsOnce
{
    public int $tries = 3;

    /** @var list<int> secondes avant le 2e puis le 3e essai */
    public array $backoff = [30, 120];

    protected function sendSmsOnce(string $key, ?string $salonId, string $toE164, string $body): void
    {
        try {
            app(SmsDeliveryGuard::class)->sendOnce($key, $salonId, $toE164, $body);
        } catch (SmsDeliveryException $e) {
            if ($e->retryable) {
                throw $e;
            }

            $this->fail($e);
        }
    }
}
