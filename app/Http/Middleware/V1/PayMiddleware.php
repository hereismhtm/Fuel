<?php

namespace App\Http\Middleware\V1;

use App\Http\Stamp;
use Closure;
use Fuel;
use Illuminate\Http\Request;
use Toolly\Typ;

class PayMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $_POST['fuel'] = gettype(Fuel::tryFrom($request->input('fuel'))) === 'object';
        $is_ok = Typ::validate($_POST, [
            'point' => Typ::i(10),
            'fuel' => Typ::correct(),
            'litre' => Typ::d(6),
            'price' => Typ::d(9),
        ]);

        if (floatval($request->input('litre')) <= 0) $is_ok = false;
        if (floatval($request->input('price')) <= 0) $is_ok = false;

        if (!$is_ok) {
            return response(...app('answer')->be(Stamp::BadRequest));
        }

        return $next($request);
    }
}
