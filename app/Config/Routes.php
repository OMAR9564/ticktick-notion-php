<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'TickTickController::index'); // Ana sayfa (Listelerin görüntülendiği sayfa)
$routes->get('ticktick', 'TickTickController::index'); // Listeleri çekmek için
$routes->get('ticktick/authenticate', 'TickTickController::authenticate'); // OAuth işlemleri için
$routes->get('ticktick/callback', 'TickTickController::callback'); // OAuth callback
$routes->get('ticktick/getProjectTasks/(:any)/data', 'TickTickController::getProjectTasks/$1'); // Seçilen listenin görevlerini gösterir
$routes->get('ticktick/projects', 'TickTickController::index');
$routes->get('ticktick/projects/(:any)/tasks', 'TickTickController::getProjectTasks/$1');
$routes->get('ticktick/login', to: 'TickTickController::login');
