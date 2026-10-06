<?php

namespace Tests\Unit\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Data\ResetPasswordData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Auth\RedisOtpService;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class ResetPasswordActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Reset réussi met à jour le mot de passe et nettoie Redis. */
    public function test_execute_updates_password_and_cleans_redis(): void
    {
        $tokenKey = 'password_reset_token:abc123';
        $tokenData = json_encode(['email' => 'user@test.com', 'user_id' => 'user-uuid', 'created_at' => time()]);
        Redis::shouldReceive('get')->with($tokenKey)->andReturn($tokenData);
        Redis::shouldReceive('del')->with($tokenKey)->once();
        Redis::shouldReceive('del')->with('otp:ratelimit:user@test.com')->once();
        Redis::shouldReceive('del')->with('otp:password_reset:attempts:user@test.com')->once();

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 'user-uuid';
        $user->email = 'user@test.com';
        $user->is_active = true;
        $user->shouldReceive('setAttribute')->with('password', Mockery::type('string'))->once();
        $user->shouldReceive('save')->once()->andReturn(true);

        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldReceive('findByEmail')->with('user@test.com')->andReturn($user);
        $redisOtp = Mockery::mock(RedisOtpService::class);

        $action = new ResetPasswordAction($userRepo, $redisOtp);
        $result = $action->execute(new ResetPasswordData('abc123', 'user@test.com', 'NewPassword123!'));

        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('réinitialisé', $result['message']);
    }

    /** Token invalide ou expiré lance une exception. */
    public function test_execute_throws_when_token_invalid_or_expired(): void
    {
        Redis::shouldReceive('get')->with('password_reset_token:invalid')->andReturn(null);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldNotReceive('findByEmail');
        $redisOtp = Mockery::mock(RedisOtpService::class);

        $action = new ResetPasswordAction($userRepo, $redisOtp);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token invalide ou expiré');

        $action->execute(new ResetPasswordData('invalid', 'user@test.com', 'NewPassword123!'));
    }

    /** Email ne correspondant pas au token lance une exception. */
    public function test_execute_throws_when_email_does_not_match_token(): void
    {
        $tokenData = json_encode(['email' => 'other@test.com', 'user_id' => 'uuid', 'created_at' => time()]);
        Redis::shouldReceive('get')->with('password_reset_token:abc')->andReturn($tokenData);
        $userRepo = Mockery::mock(UserRepositoryInterface::class);
        $userRepo->shouldNotReceive('findByEmail');
        $redisOtp = Mockery::mock(RedisOtpService::class);

        $action = new ResetPasswordAction($userRepo, $redisOtp);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Les informations ne correspondent pas');

        $action->execute(new ResetPasswordData('abc', 'user@test.com', 'NewPassword123!'));
    }
}
