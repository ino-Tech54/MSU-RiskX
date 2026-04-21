<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalSystemAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-Internal-Secret');
        $expectedSecret = env('INTERNAL_API_SECRET');
        $allowedIps = explode(',', env('ALLOWED_INTERNAL_IPS', '127.0.0.1'));

        // 1. Check if the secret matches
        if (!$secret || $secret !== $expectedSecret) {
            return response()->json([
                'error' => 'Unauthorized Internal Access',
                'message' => 'Missing or invalid internal security header.'
            ], 401);
        }

        // 2. Check if the IP is allowed (optional layer of security)
        if (!in_array($request->ip(), $allowedIps)) {
             return response()->json([
                'error' => 'IP Forbidden',
                'message' => 'Your IP address is not authorized for internal API access.'
            ], 403);
        }

        return $next($request);
    }

}
