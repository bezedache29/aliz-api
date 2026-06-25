<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaticTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('app.static_api_token');

        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $request->bearerToken() ?? '')) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
