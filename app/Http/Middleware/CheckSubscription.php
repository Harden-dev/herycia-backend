<?php

namespace App\Http\Middleware;

use App\Services\Subscription\SubscriptionAccessException;
use App\Services\Subscription\SubscriptionAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckSubscription
{
    public function __construct(
        private SubscriptionAccessService $subscriptionAccess,
    ) {}

    /** @param  string|null  $feature  Optional plan feature (online_booking, analytics, multi_branch) */
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        $user = JWTAuth::user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $subscription = $this->subscriptionAccess->resolveActiveSubscriptionForUser($user);

            if ($feature !== null) {
                $this->subscriptionAccess->assertPlanFeature($subscription, $feature);
            }

            $request->attributes->set('subscription', $subscription);

            return $next($request);
        } catch (SubscriptionAccessException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->reasonCode(),
            ], Response::HTTP_FORBIDDEN);
        }
    }
}
