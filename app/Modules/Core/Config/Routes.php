<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('core', ['namespace' => '\Volt\Core\Metadata\Controllers', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('test_evt', 'VoltResourceController::indexView/test_evt');
    $routes->get('test_evt/create', 'VoltResourceController::createView/test_evt');
    $routes->get('test_evt/edit/(:segment)', 'VoltResourceController::editView/test_evt/$1');
    $routes->get('api/test_evt', 'VoltResourceController::data/test_evt');
    $routes->get('api/test_evt/link-options', 'VoltResourceController::linkOptions/test_evt');
    $routes->get('api/test_evt/load/(:segment)', 'VoltResourceController::show/test_evt/$1');
    $routes->post('api/test_evt/save', 'VoltResourceController::store/test_evt');
    $routes->post('api/test_evt/delete/(:segment)', 'VoltResourceController::destroy/test_evt/$1');
    $routes->post('api/test_evt/submit/(:segment)', 'VoltResourceController::restSubmit/test_evt/$1');
    $routes->post('api/test_evt/approve/(:segment)', 'VoltResourceController::restApprove/test_evt/$1');
    $routes->post('api/test_evt/cancel/(:segment)', 'VoltResourceController::restCancel/test_evt/$1');
    $routes->post('api/test_evt/amend/(:segment)', 'VoltResourceController::restAmend/test_evt/$1');
    $routes->get('rest/test_evt', 'VoltResourceController::restIndex/test_evt');
    $routes->get('rest/test_evt/(:segment)', 'VoltResourceController::restShow/test_evt/$1');
    $routes->post('rest/test_evt', 'VoltResourceController::restStore/test_evt');
    $routes->put('rest/test_evt/(:segment)', 'VoltResourceController::restUpdate/test_evt/$1');
    $routes->delete('rest/test_evt/(:segment)', 'VoltResourceController::restDestroy/test_evt/$1');
    $routes->get('test_wf', 'VoltResourceController::indexView/test_wf');
    $routes->get('test_wf/create', 'VoltResourceController::createView/test_wf');
    $routes->get('test_wf/edit/(:segment)', 'VoltResourceController::editView/test_wf/$1');
    $routes->get('api/test_wf', 'VoltResourceController::data/test_wf');
    $routes->get('api/test_wf/link-options', 'VoltResourceController::linkOptions/test_wf');
    $routes->get('api/test_wf/load/(:segment)', 'VoltResourceController::show/test_wf/$1');
    $routes->post('api/test_wf/save', 'VoltResourceController::store/test_wf');
    $routes->post('api/test_wf/delete/(:segment)', 'VoltResourceController::destroy/test_wf/$1');
    $routes->post('api/test_wf/submit/(:segment)', 'VoltResourceController::restSubmit/test_wf/$1');
    $routes->post('api/test_wf/approve/(:segment)', 'VoltResourceController::restApprove/test_wf/$1');
    $routes->post('api/test_wf/cancel/(:segment)', 'VoltResourceController::restCancel/test_wf/$1');
    $routes->post('api/test_wf/amend/(:segment)', 'VoltResourceController::restAmend/test_wf/$1');
    $routes->get('rest/test_wf', 'VoltResourceController::restIndex/test_wf');
    $routes->get('rest/test_wf/(:segment)', 'VoltResourceController::restShow/test_wf/$1');
    $routes->post('rest/test_wf', 'VoltResourceController::restStore/test_wf');
    $routes->put('rest/test_wf/(:segment)', 'VoltResourceController::restUpdate/test_wf/$1');
    $routes->delete('rest/test_wf/(:segment)', 'VoltResourceController::restDestroy/test_wf/$1');
});