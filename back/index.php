<?php

require_once 'Router.php';
require_once 'AuthController.php';
require_once 'CommentController.php';

session_start(); 

$router = new Router();
$commentController = new CommentController();

$authController = new AuthController(__DIR__ . '/data/users.json');


$router->register('POST', '/register', [$authController, 'handleRegister']);
$router->register('POST', '/login', [$authController, 'handleLogin']);
$router->register('POST', '/logout', [$authController, 'handleLogout']);

$router->register('POST', '/comment', [$commentController, 'handlePostCommentRequest']);
$router->register('GET', '/comment', [$commentController, 'handleGetCommentsRequest']);
$router->register('DELETE', '/comment', [$commentController, 'handleDeleteCommentRequest']);

$router->handleRequest();

