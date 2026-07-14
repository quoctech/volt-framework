<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('', ['namespace' => 'Volt\Core\Auth\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('login', 'AuthController::login', ['filter' => 'guest']);
    $routes->post('login', 'AuthController::authenticate', ['filter' => 'guest']);
    $routes->post('setup', 'AuthController::setup', ['filter' => 'guest']);
    $routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);

    $routes->group('api', static function (RouteCollection $routes): void {
        $routes->post('login', 'AuthController::apiLogin');
        $routes->get('me', 'AuthController::apiMe', ['filter' => 'apiauth']);
    });
});

// Desk: bất kỳ user đã login. Create Module / Entity Builder: chỉ admin.
$routes->group('', ['namespace' => 'Volt\Core\Metadata\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('/', 'EntityBuilderController::desk');
    $routes->get('desk', 'EntityBuilderController::desk');
    $routes->get('desk/entities', 'EntityBuilderController::entityList');
});

$routes->group('', ['namespace' => 'Volt\Core\Auth\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('desk/profile', 'AuthController::profile');
    $routes->post('desk/profile', 'AuthController::updateProfile');
});

$routes->group('', ['namespace' => 'Volt\Core\Metadata\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('desk/entity-builder', 'EntityBuilderController::index');
    $routes->get('desk/create-module', 'EntityBuilderController::modulePage');
    $routes->get('entity-builder', 'EntityBuilderController::index');
    $routes->get('entities/new', 'EntityBuilderController::index');
});

$routes->group('api/entity-builder', ['namespace' => 'Volt\Core\Metadata\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('load/(:segment)', 'EntityBuilderController::load/$1');
    $routes->post('module/save', 'EntityBuilderController::saveModule');
    $routes->post('save', 'EntityBuilderController::save');
});
