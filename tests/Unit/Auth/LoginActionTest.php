<?php

namespace Tests\Unit\Auth;

use App\Actions\Auth\LoginAction;
use App\Data\LoginData;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Mockery;
use Tests\TestCase;

class LoginActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Execute retourne user, token, token_type et expires_in. */
    public function test_execute_returns_user_token_and_metadata(): void
    {
        $user = new User(['id' => 'user-uuid', 'email' => 'u@test.com']);
        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('login')
            ->once()
            ->with('u@test.com', 'Password123!')
            ->andReturn($user);
        $jwtService = Mockery::mock(JwtService::class);
        $jwtService->shouldReceive('createToken')->once()->with($user)->andReturn('jwt-token');
        $jwtService->shouldReceive('getExpiresIn')->once()->with('jwt-token')->andReturn(3600);
        $jwtService->shouldReceive('getExpiresAt')->once()->with('jwt-token')->andReturn('2099-01-01 00:00:00');

        $action = new LoginAction($authService, $jwtService);
        $result = $action->execute(new LoginData('u@test.com', 'Password123!'));

        $this->assertSame($user, $result['user']);
        $this->assertSame('jwt-token', $result['token']);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertEquals(3600, $result['expires_in']);
        $this->assertSame('2099-01-01 00:00:00', $result['expires_at']);
    }

    /** Execute propage l'exception si AuthService échoue. */
    public function test_execute_throws_when_auth_service_fails(): void
    {
        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('login')->once()->andThrow(new \Exception('Identifiants invalides'));
        $jwtService = Mockery::mock(JwtService::class);
        $jwtService->shouldNotReceive('createToken');

        $action = new LoginAction($authService, $jwtService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Identifiants invalides');

        $action->execute(new LoginData('u@test.com', 'wrong'));
    }
}
