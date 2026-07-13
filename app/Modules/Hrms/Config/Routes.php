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
    $routes->get('employee_checkin', 'EmployeeCheckinController::index');
    $routes->get('employee_checkin/create', 'EmployeeCheckinController::create');
    $routes->get('employee_checkin/edit/(:segment)', 'EmployeeCheckinController::edit/$1');
    $routes->get('api/employee_checkin', 'EmployeeCheckinController::data');
    $routes->get('api/employee_checkin/link-options', 'EmployeeCheckinController::data');
    $routes->get('api/employee_checkin/load/(:segment)', 'EmployeeCheckinController::load/$1');
    $routes->post('api/employee_checkin/save', 'EmployeeCheckinController::save');
    $routes->post('api/employee_checkin/delete/(:segment)', 'EmployeeCheckinController::delete/$1');
});