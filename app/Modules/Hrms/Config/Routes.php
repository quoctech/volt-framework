<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('hrms', ['namespace' => '\Volt\Core\Metadata\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('employee', 'VoltResourceController::indexView/employee');
    $routes->get('employee/create', 'VoltResourceController::createView/employee');
    $routes->get('employee/edit/(:segment)', 'VoltResourceController::editView/employee/$1');
    $routes->get('api/employee', 'VoltResourceController::data/employee');
    $routes->get('api/employee/link-options', 'VoltResourceController::linkOptions/employee');
    $routes->get('api/employee/load/(:segment)', 'VoltResourceController::show/employee/$1');
    $routes->post('api/employee/save', 'VoltResourceController::store/employee');
    $routes->post('api/employee/delete/(:segment)', 'VoltResourceController::destroy/employee/$1');
});