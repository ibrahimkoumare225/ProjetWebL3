<?php

require_once 'Router.php';
require_once 'AuthController.php';

session_start(); // Start the session

$router = new Router();
$authController = new AuthController(__DIR__ . '/data/users.json');

$router->register('POST', '/register', [$authController, 'handleRegister']);
$router->register('POST', '/login', [$authController, 'handleLogin']);
$router->register('POST', '/logout', [$authController, 'handleLogout']);


$router->handleRequest();
