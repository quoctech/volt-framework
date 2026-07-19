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
    $routes->get('employee_checkin', 'EmployeeCheckinController::index');
    $routes->get('employee_checkin/create', 'EmployeeCheckinController::create');
    $routes->get('employee_checkin/edit/(:segment)', 'EmployeeCheckinController::edit/$1');
    $routes->get('api/employee_checkin', 'EmployeeCheckinController::data');
    $routes->get('api/employee_checkin/link-options', 'EmployeeCheckinController::data');
    $routes->get('api/employee_checkin/load/(:segment)', 'EmployeeCheckinController::load/$1');
    $routes->post('api/employee_checkin/save', 'EmployeeCheckinController::save');
    $routes->post('api/employee_checkin/delete/(:segment)', 'EmployeeCheckinController::delete/$1');
    $routes->get('rest/employee_checkin', 'EmployeeCheckinApiController::index');
    $routes->get('rest/employee_checkin/(:segment)', 'EmployeeCheckinApiController::show/$1');
    $routes->post('rest/employee_checkin', 'EmployeeCheckinApiController::store');
    $routes->put('rest/employee_checkin/(:segment)', 'EmployeeCheckinApiController::update/$1');
    $routes->delete('rest/employee_checkin/(:segment)', 'EmployeeCheckinApiController::destroy/$1');
    $routes->get('employee_skill_map', 'EmployeeSkillMapController::index');
    $routes->get('employee_skill_map/create', 'EmployeeSkillMapController::create');
    $routes->get('employee_skill_map/edit/(:segment)', 'EmployeeSkillMapController::edit/$1');
    $routes->get('api/employee_skill_map', 'EmployeeSkillMapController::data');
    $routes->get('api/employee_skill_map/link-options', 'EmployeeSkillMapController::data');
    $routes->get('api/employee_skill_map/load/(:segment)', 'EmployeeSkillMapController::load/$1');
    $routes->post('api/employee_skill_map/save', 'EmployeeSkillMapController::save');
    $routes->post('api/employee_skill_map/delete/(:segment)', 'EmployeeSkillMapController::delete/$1');
    $routes->get('rest/employee_skill_map', 'EmployeeSkillMapApiController::index');
    $routes->get('rest/employee_skill_map/(:segment)', 'EmployeeSkillMapApiController::show/$1');
    $routes->post('rest/employee_skill_map', 'EmployeeSkillMapApiController::store');
    $routes->put('rest/employee_skill_map/(:segment)', 'EmployeeSkillMapApiController::update/$1');
    $routes->delete('rest/employee_skill_map/(:segment)', 'EmployeeSkillMapApiController::destroy/$1');
    $routes->get('traning_event', 'TraningEventController::index');
    $routes->get('traning_event/create', 'TraningEventController::create');
    $routes->get('traning_event/edit/(:segment)', 'TraningEventController::edit/$1');
    $routes->get('api/traning_event', 'TraningEventController::data');
    $routes->get('api/traning_event/link-options', 'TraningEventController::data');
    $routes->get('api/traning_event/load/(:segment)', 'TraningEventController::load/$1');
    $routes->post('api/traning_event/save', 'TraningEventController::save');
    $routes->post('api/traning_event/delete/(:segment)', 'TraningEventController::delete/$1');
    $routes->get('rest/traning_event', 'TraningEventApiController::index');
    $routes->get('rest/traning_event/(:segment)', 'TraningEventApiController::show/$1');
    $routes->post('rest/traning_event', 'TraningEventApiController::store');
    $routes->put('rest/traning_event/(:segment)', 'TraningEventApiController::update/$1');
    $routes->delete('rest/traning_event/(:segment)', 'TraningEventApiController::destroy/$1');
});