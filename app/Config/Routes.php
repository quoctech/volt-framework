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
    $routes->post('desk/profile/generate-api-key', 'AuthController::generateApiKey');
});

$routes->group('', ['namespace' => 'Volt\Core\Metadata\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('desk/entity-builder', 'EntityBuilderController::index');
    $routes->get('desk/create-module', 'EntityBuilderController::modulePage');
    $routes->get('entity-builder', 'EntityBuilderController::index');
    $routes->get('entities/new', 'EntityBuilderController::index');
});

$routes->group('desk/users', ['namespace' => 'Volt\Core\Auth\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('/', 'UserController::index');
    $routes->get('create', 'UserController::create');
    $routes->post('store', 'UserController::store');
    $routes->get('edit/(:segment)', 'UserController::edit/$1');
    $routes->post('update/(:segment)', 'UserController::update/$1');
    $routes->post('delete/(:segment)', 'UserController::delete/$1');
});

$routes->group('desk/roles', ['namespace' => 'Volt\Core\Role\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('/', 'RoleController::index');
    $routes->get('create', 'RoleController::create');
    $routes->post('store', 'RoleController::store');
    $routes->get('edit/(:segment)', 'RoleController::edit/$1');
    $routes->post('update/(:segment)', 'RoleController::update/$1');
    $routes->post('delete/(:segment)', 'RoleController::delete/$1');
    $routes->get('permissions/(:segment)', 'RolePermissionController::index/$1');
    $routes->post('permissions/(:segment)', 'RolePermissionController::update/$1');
});

$routes->group('desk', ['namespace' => 'Volt\Core\System\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('system-status', 'SystemStatusController::index');
    $routes->get('system-settings', 'SystemSettingController::index');
    $routes->post('system-settings/save', 'SystemSettingController::save');
});

$routes->group('desk', ['namespace' => 'Volt\Core\System\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('error-logs', 'ErrorLogController::index');
});

$routes->group('api/awesome-bar', ['namespace' => 'Volt\Core\AwesomeBar\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('search', 'AwesomeBarController::search');
});

$routes->group('api/entity-builder', ['namespace' => 'Volt\Core\Metadata\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('load/(:segment)', 'EntityBuilderController::load/$1');
    $routes->post('module/save', 'EntityBuilderController::saveModule');
    $routes->post('save', 'EntityBuilderController::save');
    $routes->post('delete/(:segment)', 'EntityBuilderController::delete/$1');
});

$routes->group('api/file', ['namespace' => 'Volt\Core\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->post('upload', 'FileController::upload');
    $routes->get('download/(:segment)', 'FileController::download/$1');
    $routes->post('delete/(:segment)', 'FileController::delete/$1');
    $routes->get('list/(:segment)/(:segment)', 'FileController::listByEntity/$1/$2');
    $routes->get('list/(:segment)/(:segment)/(:segment)', 'FileController::listByEntity/$1/$2/$3');
});
