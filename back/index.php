<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Router.php';
require_once 'AuthController.php';
require_once 'RecipeController.php';
require_once 'CommentController.php';

session_start(); // Start the session

$router = new Router();
$authController = new AuthController(__DIR__ . '/data/users.json');
$recipeController = new RecipeController(__DIR__ . '/data/recipe.json');
$commentController = new CommentController(__DIR__ . '/data/recipe.json',__DIR__ . '/data/comment.json');

//Route pour l'authentification

$router->register('POST', '/register', [$authController, 'handleRegister']);//OKI
$router->register('POST', '/login', [$authController, 'handleLogin']);//OKI
$router->register('GET', '/logout', [$authController, 'handleLogout']);//OKI



//Route pour les recettes

$router->register('GET', '/recipes', [$recipeController, 'getRecipes']); //OKI
$router->register('POST', '/recipes', [$recipeController, 'addRecipe']); // OKI
$router->register('DELETE', '/recipe/{id}', function ($id) use ($recipeController) {
    $recipeController->deleteRecipe((int)$id); // Supprimer une recette par ID
});
$router->register('PUT', '/recipes/{id}', function ($id) use ($recipeController) {
    $recipeController->updateRecipe((int)$id); // Modifier une recette 
});

//Routes pour les commentaires


$router->register('POST', '/comment', [$commentController, 'addComment']); // Ajouter un commentaire



$router->handleRequest();
