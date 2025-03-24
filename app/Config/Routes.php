<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
//$routes->get('/', 'Home::index');
$routes->get('/', 'Users::index');
$routes->get('users', 'Users::index');
$routes->post('/', 'Users::index');
$routes->match(['get','post'],'register', 'Users::register');
$routes->get('dashboard', 'Dashboard::index');
