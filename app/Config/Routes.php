<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('ticktick', 'TickTickController::index'); // Listeleri çekmek için
$routes->get('ticktick/authenticate', 'TickTickController::authenticate'); // OAuth işlemleri için
$routes->get('ticktick/callback', 'TickTickController::callback'); // OAuth callback
$routes->get('ticktick/getProjectTasks/(:any)/data', 'TickTickController::getProjectTasks/$1'); // Seçilen listenin görevlerini gösterir
$routes->get('ticktick/projects', 'TickTickController::index');
$routes->get('ticktick/projects/(:any)/tasks', 'TickTickController::getProjectTasks/$1');
$routes->get('ticktick/login', to: 'TickTickController::login');

$routes->get('/', 'Home::index'); // Ana sayfa (Listelerin görüntülendiği sayfa)
$routes->post('/login/validate', 'Home::loginValidate'); // For login validation
$routes->post('/sync', 'TickTickController::index');            // Placeholder for sync endpoint
$routes->get('/sync', 'TickTickController::index');            // Placeholder for sync endpoint

$routes->post('/sync/update-status', 'Home::updateStatus'); // To update sync status
$routes->get('/sync/check-status', 'TickTickController::checkStatus');   // To check sync status

$routes->get('sync-data', 'BackgroundTask::syncData');