<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GateMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (env('SERVICE_ONLINE_SWITCH') === '0') {
            return response('Service Unavailable.', 503);
        }

        if (env('APP_DEBUG') !== true) {
            if (!$request->secure()) {
                return response('Precondition Failed.', 412);
            }
        }

        return $next($request);
    }
}
