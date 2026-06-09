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
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Code valide retourne token et expires_in. */
    public function test_execute_returns_token_when_code_valid(): void
    {
        $otpData = json_encode(['email' => 'user@test.com', 'code' => '123456', 'created_at' => time()]);
        Redis::shouldReceive('get')->with('otp:password_reset:email:user@test.com')->andReturn($otpData);
        Redis::shouldReceive('setex')->once()->withArgs(function ($key, $ttl, $data) {
            return str_starts_with($key, 'password_reset_token:') && $ttl === 900;
        });
        Redis::shouldReceive('del')->once()->with('otp:password_reset:email:user@test.com');

        $user = new User(['id' => 'user-uuid', 'email' => 'user@test.com']);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldReceive('findByEmail')->with('user@test.com')->andReturn($user);
        $redisOtp = Mockery::mock(RedisOtpService::class);
        $redisOtp->shouldReceive('checkVerifyAttempts')->with('user@test.com')->andReturn(true);
        $redisOtp->shouldNotReceive('incrementVerifyAttempts');

        $action = new VerifyResetCodeAction($userRepo, $redisOtp);
        $result = $action->execute('user@test.com', '123456');

        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertSame(15, $result['expires_in']);
    }

    /** Code incorrect incrémente les tentatives et lance une exception. */
    public function test_execute_throws_when_code_invalid(): void
    {
        $otpData = json_encode(['email' => 'user@test.com', 'code' => '123456', 'created_at' => time()]);
        Redis::shouldReceive('get')->with('otp:password_reset:email:user@test.com')->andReturn($otpData);

        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldNotReceive('findByEmail');
        $redisOtp = Mockery::mock(RedisOtpService::class);
        $redisOtp->shouldReceive('checkVerifyAttempts')->with('user@test.com')->andReturn(true);
        $redisOtp->shouldReceive('incrementVerifyAttempts')->once()->with('user@test.com');

        $action = new VerifyResetCodeAction($userRepo, $redisOtp);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Code incorrect');

        $action->execute('user@test.com', '000000');
    }

    /** OTP expiré ou absent lance une exception. */
    public function test_execute_throws_when_otp_expired_or_invalid(): void
    {
        Redis::shouldReceive('get')->with('otp:password_reset:email:user@test.com')->andReturn(null);

        $redisOtp = Mockery::mock(RedisOtpService::class);
        $redisOtp->shouldReceive('checkVerifyAttempts')->with('user@test.com')->andReturn(true);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);

        $action = new VerifyResetCodeAction($userRepo, $redisOtp);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Code expiré ou invalide');

        $action->execute('user@test.com', '123456');
    }
}
