<?php

namespace App\Http\Middleware;

use App\Http\Stamp;
use Closure;
use Illuminate\Http\Request;

class GateMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (env('SERVICE_ONLINE_SWITCH') === '0') {
            return response(...app('answer')->be(Stamp::ServiceUnavailable));
        }

        if (env('APP_DEBUG') !== true) {
            if (!$request->secure()) {
                return response(...app('answer')->be(Stamp::PreconditionFailed));
            }
        }

        return $next($request);
    }
}
