<?php

namespace App\Http\Middleware\V1;

use App\Http\Stamp;
use Closure;
use Illuminate\Http\Request;
use Toolly\Typ;

class LoginMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $who_type = '?';
        $is_ok = Typ::validate($request->route()[2], [
            'who' => Typ::i_(12),
        ]);
        if ($is_ok) {
            $who_type = 'mobile:';
        } else {
            $is_ok = Typ::validate($request->route()[2], [
                'who' => Typ::email(100),
            ]);
            if ($is_ok) $who_type = 'email:';
        }
        if ($is_ok) {
            $this->routePurifier($request, [
                'who' => $who_type . $request->route('who'),
            ]);

            $is_ok = Typ::validate($_POST, [
                'vc' => Typ::i(6),
            ]);
        }

        if (!$is_ok) {
            return response(...app('answer')->be(Stamp::BadRequest));
        }

        return $next($request);
    }
}
