<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('health', '\Volt\Core\System\Controllers\HealthController::index');
$routes->get('ping', '\Volt\Core\System\Controllers\HealthController::index');
$routes->get('api/health', '\Volt\Core\System\Controllers\HealthController::index');
$routes->get('api/ping', '\Volt\Core\System\Controllers\HealthController::index');

$routes->group('', ['namespace' => 'Volt\Core\Auth\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('login', 'AuthController::login', ['filter' => 'guest']);
    $routes->post('login', 'AuthController::authenticate', ['filter' => ['guest', 'throttle']]);
    $routes->post('setup', 'AuthController::setup', ['filter' => 'guest']);
    $routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);

    $routes->group('api', static function (RouteCollection $routes): void {
        $routes->post('login', 'AuthController::apiLogin', ['filter' => 'throttle']);
        $routes->get('me', 'AuthController::apiMe', ['filter' => 'apiauth']);
    });
});

// Desk home: Workspace (mỗi user một workspace riêng).
$routes->group('', ['namespace' => 'Volt\Core\Workspace\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('/', 'WorkspaceController::index');
    $routes->get('desk', 'WorkspaceController::index');
});

// Desk: bất kỳ user đã login. Create Module / Entity Builder: chỉ admin.
$routes->group('', ['namespace' => 'Volt\Core\Metadata\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('desk/entities', 'EntityBuilderController::entityList');
});

// Workspace API (auth)
$routes->group('api/workspace', ['namespace' => 'Volt\Core\Workspace\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('load', 'WorkspaceController::load');
    $routes->post('block/save', 'WorkspaceController::saveBlock');
    $routes->post('block/delete', 'WorkspaceController::deleteBlock');
    $routes->post('block/reorder', 'WorkspaceController::reorderBlocks');
    $routes->post('save', 'WorkspaceController::save');
});

$routes->group('', ['namespace' => 'Volt\Core\Auth\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('desk/profile', 'AuthController::profile');
    $routes->post('desk/profile', 'AuthController::updateProfile');
    $routes->post('desk/profile/generate-api-key', 'AuthController::generateApiKey');
});

$routes->group('', ['namespace' => 'Volt\Core\Metadata\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('desk/entity-builder', 'EntityBuilderController::index');
    $routes->get('desk/create-module', 'EntityBuilderController::modulePage');
    $routes->get('desk/pages', 'PageController::index');
    $routes->get('desk/pages/create', 'PageController::create');
    $routes->get('desk/pages/edit/(:segment)', 'PageController::edit/$1');
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

$routes->group('desk/tenants', ['namespace' => 'Volt\Core\Tenant\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('/', 'TenantController::index');
    $routes->get('create', 'TenantController::create');
    $routes->post('store', 'TenantController::store');
    $routes->get('edit/(:segment)', 'TenantController::edit/$1');
    $routes->post('update/(:segment)', 'TenantController::update/$1');
    $routes->post('delete/(:segment)', 'TenantController::delete/$1');
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

$routes->group('api/pages', ['namespace' => 'Volt\Core\Metadata\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->post('save', 'PageController::save');
    $routes->post('delete/(:segment)', 'PageController::delete/$1');
});

// Reports builder UI (admin)
$routes->group('', ['namespace' => 'Volt\Core\Report\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('desk/reports', 'ReportController::index');
    $routes->get('desk/reports/create', 'ReportController::create');
    $routes->get('desk/reports/edit/(:segment)', 'ReportController::edit/$1');
});

// Reports API (admin)
$routes->group('api/reports', ['namespace' => 'Volt\Core\Report\Controllers', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->post('save', 'ReportController::save');
    $routes->post('delete/(:segment)', 'ReportController::delete/$1');
    $routes->get('entities', 'ReportController::entities');
    $routes->get('entity-fields/(:segment)', 'ReportController::entityFields/$1');
    $routes->post('suggest-joins', 'ReportController::suggestJoins');
});

// Reports run (auth - respects roles)
$routes->group('api/reports', ['namespace' => 'Volt\Core\Report\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->post('run/(:segment)', 'ReportController::run/$1');
    $routes->post('export/(:segment)/(:segment)', 'ReportController::export/$1/$2');
});

$routes->group('api/file', ['namespace' => 'Volt\Core\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->post('upload', 'FileController::upload');
    $routes->get('download/(:segment)', 'FileController::download/$1');
    $routes->post('delete/(:segment)', 'FileController::delete/$1');
    $routes->get('list/(:segment)/(:segment)', 'FileController::listByEntity/$1/$2');
    $routes->get('list/(:segment)/(:segment)/(:segment)', 'FileController::listByEntity/$1/$2/$3');
});

// Dynamic page routes (auto-generated by PageService)
$pageRoutesFile = __DIR__ . '/PageRoutes.php';
if (file_exists($pageRoutesFile)) {
    require $pageRoutesFile;
}
