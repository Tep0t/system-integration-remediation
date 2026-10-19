<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.env') === 'production' && ! $request->isSecure()) {
            return response()->json(['message' => 'HTTPS is required.'], 426);
        }

        return $next($request);
    }
}
