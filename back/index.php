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
// Remplacer les headers existants par :
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Expose-Headers: *");

// Gestion OPTIONS (avant tout code métier)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 204 No Content");
    exit();
}

// Début de session après les headers
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

//Route pour l'authentification

$router->register('POST', '/register', [$authController, 'handleRegister']);//OKI
$router->register('POST', '/login', [$authController, 'handleLogin']);//OKI
$router->register('POST', '/logout', [$authController, 'handleLogout']);//OKI



//Route pour les recettes

$router->register('GET', '/recipes', [$recipeController, 'getRecipes']); //OKI
$router->register('GET', '/recipes/search', [$recipeController, 'getRecetteBySearch']);
$router->register('POST', '/recipes', [$recipeController, 'addRecipe']); // OKI
$router->register('DELETE', '/recipes/{id}', function ($id) use ($recipeController) {
    $recipeController->deleteRecipe((int)$id);
});
$router->register('PUT', '/recipes/{id}', function ($id) use ($recipeController) {
    $recipeController->updateRecipe((int)$id);
});

// Routes pour les commentaires

$router->register('GET', '/comments', [$commentController, 'getComments']); //OKI
$router->register('POST', '/comments', [$commentController, 'addComment']); // OKI
$router->register('DELETE', '/comments/{id}', function ($id) use ($commentController) {
    $commentController->deleteComment((int)$id);
});
$router->register('PUT', '/comments/{id}', function ($id) use ($commentController) {
    $commentController->updateComment((int)$id);
});

$router->handleRequest();
