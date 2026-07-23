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
    $routes->post('api/employee/submit/(:segment)', 'VoltResourceController::restSubmit/employee/$1');
    $routes->post('api/employee/approve/(:segment)', 'VoltResourceController::restApprove/employee/$1');
    $routes->post('api/employee/cancel/(:segment)', 'VoltResourceController::restCancel/employee/$1');
    $routes->post('api/employee/amend/(:segment)', 'VoltResourceController::restAmend/employee/$1');
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
    $routes->post('api/employeeeducation/submit/(:segment)', 'VoltResourceController::restSubmit/employeeeducation/$1');
    $routes->post('api/employeeeducation/approve/(:segment)', 'VoltResourceController::restApprove/employeeeducation/$1');
    $routes->post('api/employeeeducation/cancel/(:segment)', 'VoltResourceController::restCancel/employeeeducation/$1');
    $routes->post('api/employeeeducation/amend/(:segment)', 'VoltResourceController::restAmend/employeeeducation/$1');
    $routes->get('rest/employeeeducation', 'VoltResourceController::restIndex/employeeeducation');
    $routes->get('rest/employeeeducation/(:segment)', 'VoltResourceController::restShow/employeeeducation/$1');
    $routes->post('rest/employeeeducation', 'VoltResourceController::restStore/employeeeducation');
    $routes->put('rest/employeeeducation/(:segment)', 'VoltResourceController::restUpdate/employeeeducation/$1');
    $routes->delete('rest/employeeeducation/(:segment)', 'VoltResourceController::restDestroy/employeeeducation/$1');
    $routes->get('leave', 'VoltResourceController::indexView/leave');
    $routes->get('leave/create', 'VoltResourceController::createView/leave');
    $routes->get('leave/edit/(:segment)', 'VoltResourceController::editView/leave/$1');
    $routes->get('api/leave', 'VoltResourceController::data/leave');
    $routes->get('api/leave/link-options', 'VoltResourceController::linkOptions/leave');
    $routes->get('api/leave/load/(:segment)', 'VoltResourceController::show/leave/$1');
    $routes->post('api/leave/save', 'VoltResourceController::store/leave');
    $routes->post('api/leave/delete/(:segment)', 'VoltResourceController::destroy/leave/$1');
    $routes->post('api/leave/submit/(:segment)', 'VoltResourceController::restSubmit/leave/$1');
    $routes->post('api/leave/approve/(:segment)', 'VoltResourceController::restApprove/leave/$1');
    $routes->post('api/leave/cancel/(:segment)', 'VoltResourceController::restCancel/leave/$1');
    $routes->post('api/leave/amend/(:segment)', 'VoltResourceController::restAmend/leave/$1');
    $routes->get('rest/leave', 'VoltResourceController::restIndex/leave');
    $routes->get('rest/leave/(:segment)', 'VoltResourceController::restShow/leave/$1');
    $routes->post('rest/leave', 'VoltResourceController::restStore/leave');
    $routes->put('rest/leave/(:segment)', 'VoltResourceController::restUpdate/leave/$1');
    $routes->delete('rest/leave/(:segment)', 'VoltResourceController::restDestroy/leave/$1');
});