<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('', ['namespace' => 'Volt\Core\Auth\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('/', 'AuthController::index');
    $routes->get('login', 'AuthController::login', ['filter' => 'guest']);
    $routes->post('login', 'AuthController::authenticate', ['filter' => 'guest']);
    $routes->post('setup', 'AuthController::setup', ['filter' => 'guest']);
    $routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);

    $routes->group('api', static function (RouteCollection $routes): void {
        $routes->post('login', 'AuthController::apiLogin');
        $routes->get('me', 'AuthController::apiMe', ['filter' => 'apiauth']);
    });
});

$routes->group('notes', ['namespace' => 'Volt\Core\Notes\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('/', 'NoteController::index');
    $routes->get('create', 'NoteController::create');
    $routes->post('store', 'NoteController::store');
    $routes->get('edit/(:num)', 'NoteController::edit/$1');
    $routes->post('update/(:num)', 'NoteController::update/$1');
    $routes->post('delete/(:num)', 'NoteController::delete/$1');
});
