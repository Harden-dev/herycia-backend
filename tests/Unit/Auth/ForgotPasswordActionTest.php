<?php

namespace Tests\Unit\Auth;

use App\Actions\Auth\ForgotPasswordAction;
use App\Data\ForgotPasswordData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\RedisOtpService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class ForgotPasswordActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Retourne un message neutre si l'email n'existe pas (sécurité). */
    public function test_execute_returns_message_when_user_not_found(): void
    {
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldReceive('findByEmail')->with('unknown@test.com')->andReturn(null);
        $redis = Mockery::mock(RedisOtpService::class);
        $redis->shouldNotReceive('generateCode');

        $action = new ForgotPasswordAction($userRepo, $redis);
        $result = $action->execute(new ForgotPasswordData('unknown@test.com'));

        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('Si cet email existe', $result['message']);
    }

    /** Envoie le code et retourne message + expires_in quand l'utilisateur existe. */
    public function test_execute_sends_code_when_user_exists(): void
    {
        Notification::fake();
        Redis::shouldReceive('setex')->once()->withArgs(function ($key, $ttl, $data) {
            $decoded = json_decode($data, true);
            return str_contains($key, 'password_reset') && isset($decoded['code']) && $decoded['email'] === 'user@test.com';
        });

        $user = new User(['email' => 'user@test.com']);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldReceive('findByEmail')->with('user@test.com')->andReturn($user);
        $redis = Mockery::mock(RedisOtpService::class);
        $redis->shouldReceive('checkRateLimit')->with('user@test.com')->andReturn(true);
        $redis->shouldReceive('generateCode')->once()->andReturn('654321');
        $redis->shouldReceive('incrementRateLimit')->once()->with('user@test.com');

        $action = new ForgotPasswordAction($userRepo, $redis);
        $result = $action->execute(new ForgotPasswordData('user@test.com'));

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertSame(10, $result['expires_in']);
    }

    /** Échoue si rate limit dépassé. */
    public function test_execute_throws_when_rate_limit_exceeded(): void
    {
        $user = new User(['email' => 'user@test.com']);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldReceive('findByEmail')->with('user@test.com')->andReturn($user);
        $redis = Mockery::mock(RedisOtpService::class);
        $redis->shouldReceive('checkRateLimit')->with('user@test.com')->andReturn(false);

        $action = new ForgotPasswordAction($userRepo, $redis);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Trop de tentatives');

        $action->execute(new ForgotPasswordData('user@test.com'));
    }
}
