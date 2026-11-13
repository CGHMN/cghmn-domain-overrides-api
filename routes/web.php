<?php

use MiladRahimi\PhpRouter\Router;

use Src\Controllers\FilteredListController;
use Src\Controllers\IndexController;

/** @var Router $router Instance of PHP router, creted by sourcing index.php script */
$router->get('/', [IndexController::class, 'show']);
$router->get('/lists/filtered', [FilteredListController::class, 'getFilteredList']);
$router->define('HEAD', '/lists/filtered', [FilteredListController::class, 'headFilteredList']);
