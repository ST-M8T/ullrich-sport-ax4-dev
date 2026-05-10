<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): mixed
    {
        $expected = config('services.api.key');

        if (! $expected) {
            return $next($request);
        }

        $provided = $request->header('X-API-Key')
            ?? $request->query('api_key');

        if (! hash_equals($expected, (string) $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
