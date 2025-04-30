<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', 0);
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => 'localhost',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// En-têtes CORS
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Expose-Headers: *");

// Gestion OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 204 No Content");
    exit();
}

// Début de session
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Router.php';
require_once 'AuthController.php';
require_once 'RecipeController.php';
require_once 'CommentController.php';

$router = new Router();
$authController = new AuthController(__DIR__ . '/data/users.json');
$recipeController = new RecipeController(__DIR__ . '/data/recipe.json');
$commentController = new CommentController(__DIR__ . '/data/comments.json');

// Routes pour l'authentification
$router->register('POST', '/register', [$authController, 'handleRegister']);
$router->register('POST', '/login', [$authController, 'handleLogin']);
$router->register('POST', '/logout', [$authController, 'handleLogout']);

// Routes pour les recettes
$router->register('GET', '/recipes', [$recipeController, 'getRecipes']);
$router->register('GET', '/recipes/search', [$recipeController, 'searchRecipes']);
$router->register('POST', '/recipes', [$recipeController, 'addRecipe']);
$router->register('DELETE', '/recipes/{id}', function ($id) use ($recipeController) {
    $recipeController->deleteRecipe((int)$id);
});
$router->register('PUT', '/recipes/{id}', function ($id) use ($recipeController) {
    $recipeController->updateRecipe((int)$id);
});

// Route pour les likes
$router->register('POST', '/like', [$recipeController, 'handleLike']);

// Routes pour les commentaires
$router->register('GET', '/comments', [$commentController, 'getComments']);
$router->register('POST', '/comments', [$commentController, 'addComment']);
$router->register('DELETE', '/comments/{id}', function ($id) use ($commentController) {
    $commentController->deleteComment((int)$id);
});
$router->register('PUT', '/comments/{id}', function ($id) use ($commentController) {
    $commentController->updateComment((int)$id);
});

try {
    $router->handleRequest();
} catch (Exception $e) {
    error_log("Erreur de routage: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}