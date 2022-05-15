<?php

namespace App\Http\Middleware\V1;

class Middleware
{
    protected function routePurifier(&$request, $values)
    {
        $route = $request->route();
        foreach ($values as $key => $value) {
            $route[2][$key] = $value;
        }
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
    }
}
