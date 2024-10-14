<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('/integration', 'Home::index');
$routes->get('/activate-integration', 'Home::activateIntegration');
$routes->get('/deactivate-integration', 'Home::deActivateIntegration');
$routes->post('/sync-tasks', 'Home::syncTasks');
$routes->get('/handleCallback', 'IntegrationController::handleCallback');
$routes->post('/callback', 'IntegrationController::callback');
$routes->get('/callback', 'IntegrationController::callback');
