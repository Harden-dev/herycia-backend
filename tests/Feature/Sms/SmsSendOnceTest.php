<?php

namespace Tests\Feature\Sms;

use App\Exceptions\SmsDeliveryException;
use App\Jobs\Sms\SendAppointmentReminderSms;
use App\Jobs\Sms\SendAppointmentTrackingSms;
use App\Jobs\Sms\SendQueueSoonSms;
use App\Listeners\SendSalonWelcomeSms;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\QueueEntry;
use App\Models\Salon;
use App\Services\Sms\SmsDeliveryGuard;
use App\Services\Sms\TwilioSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Twilio\Exceptions\EnvironmentException;
use Twilio\Exceptions\RestException;

/** Jobs SMS : jamais deux fois le même SMS, quota décompté une seule fois, réessai seulement si sûr. */
class SmsSendOnceTest extends TestCase
{
    use RefreshDatabase;

    private const TO = '+2250707070707';

    public function test_same_sms_key_is_sent_only_once(): void
    {
        $this->mock(TwilioSmsService::class)->shouldReceive('send')->once();
        $guard = app(SmsDeliveryGuard::class);

        $guard->sendOnce('test:once', 'salon-1', self::TO, 'Bonjour');
        $guard->sendOnce('test:once', 'salon-1', self::TO, 'Bonjour');

        $this->assertSame(1, RateLimiter::attempts('sms-quota:salon-1'));
    }

    public function test_retryable_failure_can_be_retried_and_quota_is_consumed_once(): void
    {
        $calls = 0;
        $this->mock(TwilioSmsService::class)->shouldReceive('send')
            ->twice()
            ->andReturnUsing(function () use (&$calls): void {
                if (++$calls === 1) {
                    throw new SmsDeliveryException('429', retryable: true);
                }
            });
        $guard = app(SmsDeliveryGuard::class);

        try {
            $guard->sendOnce('test:retry', 'salon-1', self::TO, 'Bonjour');
            $this->fail('L\'échec temporaire doit remonter pour déclencher le réessai.');
        } catch (SmsDeliveryException $e) {
            $this->assertTrue($e->retryable);
        }

        $guard->sendOnce('test:retry', 'salon-1', self::TO, 'Bonjour');
        $guard->sendOnce('test:retry', 'salon-1', self::TO, 'Bonjour');

        $this->assertSame(1, RateLimiter::attempts('sms-quota:salon-1'));
    }

    public function test_uncertain_failure_is_never_resent(): void
    {
        $this->mock(TwilioSmsService::class)->shouldReceive('send')
            ->once()
            ->andThrow(new SmsDeliveryException('Operation timed out', retryable: false));
        $guard = app(SmsDeliveryGuard::class);

        try {
            $guard->sendOnce('test:uncertain', null, self::TO, 'Bonjour');
        } catch (SmsDeliveryException) {
            // attendu
        }

        // Rejeu du job : le SMS a peut-être été reçu, on ne le renvoie pas.
        $guard->sendOnce('test:uncertain', null, self::TO, 'Bonjour');
    }

    public function test_reminder_job_run_twice_sends_one_sms_but_rescheduling_sends_a_new_one(): void
    {
        Queue::fake();
        $salon = Salon::factory()->create();
        $client = Client::factory()->create(['salon_id' => $salon->id, 'phone' => '2250707070707']);
        $appointment = Appointment::factory()->create([
            'salon_id' => $salon->id,
            'client_id' => $client->id,
            'scheduled_at' => now()->addHour(),
        ]);

        $this->mock(TwilioSmsService::class)->shouldReceive('send')->twice();

        $job = new SendAppointmentReminderSms($appointment);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);

        $appointment->update(['scheduled_at' => now()->addHours(3)]);
        app()->call([new SendAppointmentReminderSms($appointment), 'handle']);
    }

    public function test_definitive_failure_ends_the_job_without_retry(): void
    {
        Queue::fake();
        $salon = Salon::factory()->create();
        $client = Client::factory()->create(['salon_id' => $salon->id, 'phone' => '2250707070707']);
        $appointment = Appointment::factory()->create(['salon_id' => $salon->id, 'client_id' => $client->id]);

        $this->mock(TwilioSmsService::class)->shouldReceive('send')
            ->once()
            ->andThrow(new SmsDeliveryException('Numéro invalide', retryable: false));

        // Ne doit pas lever : le job est terminé (fail) au lieu d'être réessayé.
        app()->call([(new SendAppointmentReminderSms($appointment))->withFakeQueueInteractions(), 'handle']);

        $this->assertTrue(true);
    }

    public function test_jobs_have_backoff_between_attempts(): void
    {
        $job = new SendAppointmentReminderSms(new Appointment);

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 120], $job->backoff);
    }

    public function test_twilio_errors_are_classified_safely(): void
    {
        $service = new TwilioSmsService;
        $classify = fn (\Throwable $e): bool => (fn () => $this->isSafeToRetry($e))->call($service);

        $this->assertTrue($classify(new RestException('Too Many Requests', 20429, 429)));
        $this->assertTrue($classify(new RestException('Service Unavailable', 0, 503)));
        $this->assertTrue($classify(new RestException('Internal Server Error', 0, 500)));
        // 502 / 504 : la passerelle a pu transmettre la requête, le SMS a pu partir (pentest P3).
        $this->assertFalse($classify(new RestException('Bad Gateway', 0, 502)));
        $this->assertFalse($classify(new RestException('Gateway Timeout', 0, 504)));
        $this->assertFalse($classify(new RestException('Invalid To number', 21211, 400)));
        $this->assertTrue($classify(new EnvironmentException('Could not resolve host: api.twilio.com')));
        $this->assertTrue($classify(new EnvironmentException('Failed to connect to api.twilio.com port 443')));
        $this->assertFalse($classify(new EnvironmentException('Operation timed out after 30000 milliseconds')));
        $this->assertFalse($classify(new \RuntimeException('Erreur inconnue')));
    }

    public function test_worker_killed_during_twilio_call_does_not_resend_on_retry(): void
    {
        // Pentest P3 : le processus meurt pendant l'appel Twilio (SIGALRM, OOM…) après acceptation du message.
        $this->mock(TwilioSmsService::class)->shouldReceive('send')
            ->once()
            ->andThrow(new \Error('Worker tué pendant l\'appel Twilio'));
        $guard = app(SmsDeliveryGuard::class);

        try {
            $guard->sendOnce('test:killed', 'salon-1', self::TO, 'Bonjour');
            $this->fail('L\'interruption doit remonter.');
        } catch (\Error) {
            // Le verrou est libéré, le marqueur « in_flight » reste.
        }

        $this->assertSame(SmsDeliveryGuard::STATE_IN_FLIGHT, Cache::get('sms:handled:test:killed'));

        // Réessai du job (après retry_after) : pas de second SMS, et un avertissement est journalisé.
        Log::spy();
        $guard->sendOnce('test:killed', 'salon-1', self::TO, 'Bonjour');

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'état incertain'));
        $this->assertSame(1, RateLimiter::attempts('sms-quota:salon-1'));
    }

    public function test_retryable_failure_clears_in_flight_marker(): void
    {
        $this->mock(TwilioSmsService::class)->shouldReceive('send')
            ->once()
            ->andThrow(new SmsDeliveryException('HTTP 429', retryable: true));

        try {
            app(SmsDeliveryGuard::class)->sendOnce('test:clear', null, self::TO, 'Bonjour');
        } catch (SmsDeliveryException) {
            // attendu
        }

        $this->assertNull(Cache::get('sms:handled:test:clear'));
    }

    public function test_twilio_http_timeout_is_below_sms_job_timeouts_and_retry_after(): void
    {
        $client = (fn () => $this->client('ACtest', 'token'))->call(new TwilioSmsService);
        $curlOptions = $client->getHttpClient()->options('POST', 'https://api.twilio.com');

        $this->assertSame(TwilioSmsService::HTTP_TIMEOUT_SECONDS, $curlOptions[CURLOPT_TIMEOUT]);

        $jobTimeouts = [
            (new SendAppointmentReminderSms(new Appointment))->timeout,
            (new SendAppointmentTrackingSms(new Appointment))->timeout,
            (new SendQueueSoonSms(new QueueEntry))->timeout,
            app(SendSalonWelcomeSms::class)->timeout,
        ];

        foreach ($jobTimeouts as $timeout) {
            $this->assertGreaterThan(TwilioSmsService::HTTP_TIMEOUT_SECONDS, $timeout);
            $this->assertLessThan((int) config('queue.connections.redis.retry_after'), $timeout);
        }
    }

    public function test_failure_message_kept_in_failed_jobs_does_not_contain_the_phone_number(): void
    {
        $service = new TwilioSmsService;
        $describe = fn (\Throwable $e): string => (fn () => $this->describe($e))->call($service);

        $rest = $describe(new RestException('Unable to create record: The \'To\' number +2250707070707 is not valid', 21211, 400));
        $network = $describe(new EnvironmentException('Error for +2250707070707: Operation timed out'));

        $this->assertSame('HTTP 400, code Twilio 21211', $rest);
        $this->assertStringNotContainsString('0707070707', $network);
        $this->assertStringContainsString('+225*****0707', $network);
    }
}
