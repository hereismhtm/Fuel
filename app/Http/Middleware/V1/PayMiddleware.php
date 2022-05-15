<?php

namespace App\Http\Middleware\V1;

use Closure;
use Illuminate\Http\Request;
use Toolly\Typ;

class PayMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $is_ok = Typ::validate($_POST, [
            'point' => Typ::i(10),
            'fuel' => Typ::s(10),
            'litre' => Typ::d(6),
            'price' => Typ::d(9),
        ]);
        if (!in_array($request->input('fuel'), ['gasoline', 'diesel'])) $is_ok = false;
        if (floatval($request->input('litre')) <= 0) $is_ok = false;
        if (floatval($request->input('price')) <= 0) $is_ok = false;

        if (!$is_ok) {
            return response('Bad Request.', 400);
        }

        return $next($request);
    }
}
