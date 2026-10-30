<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeviceToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null || trim($bearerToken) === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Token perangkat wajib disertakan pada header Authorization Bearer.',
            ], 401);
        }

        $tokenHash = Device::hashToken($bearerToken);

        $device = Device::query()
            ->active()
            ->where('token_hash', $tokenHash)
            ->first();

        if ($device === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Token perangkat tidak valid atau perangkat telah dinonaktifkan.',
            ], 401);
        }

        $device->forceFill([
            'last_seen_at' => now('Asia/Jakarta'),
        ])->saveQuietly();

        $request->attributes->set('authenticated_device', $device);

        return $next($request);
    }
}

