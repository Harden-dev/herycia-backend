<?php

namespace Tests\Unit\Auth;

use App\Actions\Auth\VerifyResetCodeAction;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\RedisOtpService;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class VerifyResetCodeActionTest extends TestCase
{
    private const RESET_KEY = 'otp:password_reset:email:user@test.com';

    private const ATTEMPTS_KEY = 'otp:password_reset:attempts:user@test.com';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Code valide retourne token et expires_in, puis invalide le code et le compteur. */
    public function test_execute_returns_token_when_code_valid(): void
    {
        $otpData = json_encode(['email' => 'user@test.com', 'code' => '123456', 'created_at' => time()]);

        Redis::shouldReceive('get')->with(self::ATTEMPTS_KEY)->andReturn(null);
        Redis::shouldReceive('get')->with(self::RESET_KEY)->andReturn($otpData);
        Redis::shouldReceive('setex')->once()->withArgs(function ($key, $ttl, $data) {
            return str_starts_with($key, 'password_reset_token:') && $ttl === 900;
        });
        Redis::shouldReceive('del')->once()->with(self::RESET_KEY);
        Redis::shouldReceive('del')->once()->with(self::ATTEMPTS_KEY);

        $user = new User(['id' => 'user-uuid', 'email' => 'user@test.com', 'is_active' => true]);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldReceive('findByEmail')->with('user@test.com')->andReturn($user);

        $action = new VerifyResetCodeAction($userRepo, Mockery::mock(RedisOtpService::class));
        $result = $action->execute('user@test.com', '123456');

        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertSame(15, $result['expires_in']);
    }

    /** Code incorrect incrémente le compteur dédié et lance une exception. */
    public function test_execute_throws_when_code_invalid(): void
    {
        $otpData = json_encode(['email' => 'user@test.com', 'code' => '123456', 'created_at' => time()]);

        Redis::shouldReceive('get')->with(self::ATTEMPTS_KEY)->andReturn(null);
        Redis::shouldReceive('get')->with(self::RESET_KEY)->andReturn($otpData);
        Redis::shouldReceive('incr')->once()->with(self::ATTEMPTS_KEY)->andReturn(1);
        Redis::shouldReceive('expire')->once()->with(self::ATTEMPTS_KEY, 600);

        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldNotReceive('findByEmail');

        $action = new VerifyResetCodeAction($userRepo, Mockery::mock(RedisOtpService::class));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Code incorrect');

        $action->execute('user@test.com', '000000');
    }

    /** Trop d'essais : le code est invalidé. */
    public function test_execute_invalidates_code_after_max_attempts(): void
    {
        Redis::shouldReceive('get')->with(self::ATTEMPTS_KEY)->andReturn('5');
        Redis::shouldReceive('del')->once()->with(self::RESET_KEY);

        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldNotReceive('findByEmail');

        $action = new VerifyResetCodeAction($userRepo, Mockery::mock(RedisOtpService::class));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Trop de tentatives');

        $action->execute('user@test.com', '123456');
    }

    /** OTP expiré ou absent lance une exception. */
    public function test_execute_throws_when_otp_expired_or_invalid(): void
    {
        Redis::shouldReceive('get')->with(self::ATTEMPTS_KEY)->andReturn(null);
        Redis::shouldReceive('get')->with(self::RESET_KEY)->andReturn(null);

        $action = new VerifyResetCodeAction(
            Mockery::mock(UserRepositoryInterface::class),
            Mockery::mock(RedisOtpService::class),
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Code expiré ou invalide');

        $action->execute('user@test.com', '123456');
    }
}
