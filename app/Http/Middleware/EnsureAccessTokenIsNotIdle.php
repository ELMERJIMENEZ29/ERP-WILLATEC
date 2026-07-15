<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessTokenIsNotIdle
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return $next($request);
        }

        $timeoutMinutes = (int) config('session.idle_timeout_minutes', 16);

        if ($timeoutMinutes > 0) {
            $lastActivityAt = $token->last_used_at ?: $token->created_at;

            if ($lastActivityAt && $lastActivityAt->copy()->addMinutes($timeoutMinutes)->isPast()) {
                $token->delete();

                return response()->json([
                    'message' => 'La sesion expiro por inactividad.',
                ], 401);
            }
        }

        $response = $next($request);

        $token->forceFill([
            'last_used_at' => now(),
        ])->save();

        return $response;
    }
}
