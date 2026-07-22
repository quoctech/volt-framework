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
    $routes->get('rest/employee', 'VoltResourceController::restIndex/employee');
    $routes->get('rest/employee/(:segment)', 'VoltResourceController::restShow/employee/$1');
    $routes->post('rest/employee', 'VoltResourceController::restStore/employee');
    $routes->put('rest/employee/(:segment)', 'VoltResourceController::restUpdate/employee/$1');
    $routes->delete('rest/employee/(:segment)', 'VoltResourceController::restDestroy/employee/$1');
    $routes->get('employeeeducation', 'VoltResourceController::indexView/employeeeducation');
    $routes->get('employeeeducation/create', 'VoltResourceController::createView/employeeeducation');
    $routes->get('employeeeducation/edit/(:segment)', 'VoltResourceController::editView/employeeeducation/$1');
    $routes->get('api/employeeeducation', 'VoltResourceController::data/employeeeducation');
    $routes->get('api/employeeeducation/link-options', 'VoltResourceController::linkOptions/employeeeducation');
    $routes->get('api/employeeeducation/load/(:segment)', 'VoltResourceController::show/employeeeducation/$1');
    $routes->post('api/employeeeducation/save', 'VoltResourceController::store/employeeeducation');
    $routes->post('api/employeeeducation/delete/(:segment)', 'VoltResourceController::destroy/employeeeducation/$1');
    $routes->get('rest/employeeeducation', 'VoltResourceController::restIndex/employeeeducation');
    $routes->get('rest/employeeeducation/(:segment)', 'VoltResourceController::restShow/employeeeducation/$1');
    $routes->post('rest/employeeeducation', 'VoltResourceController::restStore/employeeeducation');
    $routes->put('rest/employeeeducation/(:segment)', 'VoltResourceController::restUpdate/employeeeducation/$1');
    $routes->delete('rest/employeeeducation/(:segment)', 'VoltResourceController::restDestroy/employeeeducation/$1');
});