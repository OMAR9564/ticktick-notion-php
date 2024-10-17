<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'IntegrationController::index');
$routes->get('/integration', 'IntegrationController::index');
$routes->get('/activate-integration', 'IntegrationController::activateIntegration');
$routes->get('/deactivate-integration', 'IntegrationController::deActivateIntegration');
$routes->post('/sync-tasks', 'IntegrationController::syncTasks');
$routes->get('/handleCallback', 'IntegrationController::handleCallback');
$routes->post('/callback', 'IntegrationController::callback');
$routes->get('/callback', 'IntegrationController::callback');
