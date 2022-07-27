<?php

namespace App\Http\Middleware;

use App\Http\Stamp;
use Closure;
use Firewl\Firewl;
use Illuminate\Http\Request;
use Toolly\Answer;

class AppMiddleware
{
    private Answer $answer;

    public function handle(Request $request, Closure $next)
    {
        $debug = env('APP_DEBUG') === true;
        $this->answer = app('answer');

        if (env('SERVICE_ONLINE_SWITCH') === '0') {
            return response(...$this->answer->be(Stamp::ServiceUnavailable));
        }

        if (!$debug) {
            if (!$request->secure()) {
                return response(...$this->answer->be(Stamp::PreconditionFailed));
            }
        }

        $this->answer->firewl = new Firewl(app('medoo'));
        $this->answer->firewl->penaltyMinutes = $debug ? 1 : 720;
        if ($this->answer->firewl->isBanned()) {
            return response(...$this->answer->be(Stamp::Forbidden));
        }

        return $next($request);
    }
}
