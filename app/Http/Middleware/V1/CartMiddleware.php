<?php

namespace App\Http\Middleware\V1;

use Closure;
use Fuel;
use Illuminate\Http\Request;

class CartMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $this->routePurifier($request, [
            'fuel' => Fuel::from($request->route('fuel')),
            'litre' => number_format($request->route('litre'), 2, '.', ''),
        ]);

        if (floatval($request->route('litre')) <= 0) {
            return response('Bad Request.', 400);
        }

        return $next($request);
    }
}
