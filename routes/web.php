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

$router->get('/', function () use ($router) {
    // $_p = '24';
    // $p = '24.00';
    // echo ($_p == $p) ? 'yes' : 'no';

    return $router->app->version();
});

$router->post('token', function () {
    $user = $_POST['user'];
    $counter = $_POST['counter'];
    $secret = $_POST['secret'];

    $h = hash('SHA1', "{$counter}{$secret}");

    $b64 = base64_encode("{$user}:{$counter}:{$h}");
    return "Authorization: Basic {$b64}";
});
