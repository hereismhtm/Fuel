<?php

namespace App\Http\Middleware;

use App\Http\Stamp;
use Closure;
use Firewl\Firewl;
use Illuminate\Http\Request;

class AppMiddleware
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

        $firewl = new Firewl(app('medoo'));
        if ($firewl->isBanned()) {
            return response(...app('answer')->be(Stamp::Forbidden));
        }

        return $next($request);
    }
}
