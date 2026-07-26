<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
    $routes->get('new-page', '\Volt\Core\Metadata\Controllers\PageController::serve/new-page');
    $routes->get('test-page', '\Volt\Core\Metadata\Controllers\PageController::serve/test-page');
