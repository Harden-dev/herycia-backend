<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
class JwtService
{
 public function createToken(User $user): string
 {
    try {
        $token = JWTAuth::fromUser($user);
        return $token;
    } catch (\Throwable $th) {
        Log::error('Error creating token: ' . $th->getMessage());
        throw new \Exception('Error creating token: ' . $th->getMessage());
    }
 }

 public function refreshToken(string $token): string
 {
    try {
        // refresh() n'accepte pas le jeton en argument (1er paramètre = $forceForever) : il faut le poser avant.
        $token = JWTAuth::setToken($token)->refresh();
        return $token;
    } catch (\Throwable $th) {
        Log::error('Error refreshing token: ' . $th->getMessage());
        throw new \Exception('Error refreshing token: ' . $th->getMessage());
    }
 }

 public function logout()
 {
    try {
    JWTAuth::invalidate(JWTAuth::getToken());

    Log::info('User logged out successfully', [
        'user_id' => Auth::id(),
    ]);
    } catch (\Throwable $th) {
        Log::error('Error logging out user: ' . $th->getMessage());
        throw new \Exception('Error logging out user: ' . $th->getMessage());
    }
 }

 public function validateToken(string $token): bool
 {
    try {
        JWTAuth::setToken($token)->check();
        return true;
    } catch (\Throwable $th) {
        Log::error('Error validating token: ' . $th->getMessage());
        return false;
    }
 }

 public function getExpiresIn(string $token): string
 {
    try {
        return JWTAuth::setToken($token)->getPayload()->get('exp');
    } catch (\Throwable $th) {
        return 'never';
    }
 }

 public function getExpiresAt(string $token): string
 {
    try {
        return date('Y-m-d H:i:s', JWTAuth::setToken($token)->getPayload()->get('exp'));
    } catch (\Throwable $th) {
        Log::error('Error getting expires at: ' . $th->getMessage());
        throw new \Exception('Error getting expires at: ' . $th->getMessage());
    }
 }
}
