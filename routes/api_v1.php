<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->post('user/{who}/login', [
    'middleware' => 'login',
    'uses' => 'UserController@login'
]);


$router->get('cart/{worker:[0-9]{16}}/{fuel:' . Fuel::G->value . '|' . Fuel::D->value . '}/{litre:[0-9]+|[0-9]+\.[0-9]{1,2}}', [
    'middleware' => 'cart',
    'uses' => 'PointController@lookup'
]);

$router->post('pay', [
    'middleware' => 'pay',
    'uses' => 'PointController@payment'
]);


$router->get('cpanel/session/{point:[0-9]+}', [
    'uses' => 'CPanelController@point_session'
]);
