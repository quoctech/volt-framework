<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('hrms', ['namespace' => 'App\Modules\Hrms\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('employee', 'EmployeeController::index');
    $routes->get('employee/create', 'EmployeeController::create');
    $routes->get('employee/edit/(:segment)', 'EmployeeController::edit/$1');
    $routes->get('api/employee', 'EmployeeController::data');
    $routes->get('api/employee/link-options', 'EmployeeController::data');
    $routes->get('api/employee/load/(:segment)', 'EmployeeController::load/$1');
    $routes->post('api/employee/save', 'EmployeeController::save');
    $routes->post('api/employee/delete/(:segment)', 'EmployeeController::delete/$1');
    $routes->get('rest/employee', 'EmployeeApiController::index');
    $routes->get('rest/employee/(:segment)', 'EmployeeApiController::show/$1');
    $routes->post('rest/employee', 'EmployeeApiController::store');
    $routes->put('rest/employee/(:segment)', 'EmployeeApiController::update/$1');
    $routes->delete('rest/employee/(:segment)', 'EmployeeApiController::destroy/$1');
});