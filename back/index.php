<?php

/**
 * Fichier principal de l'application web PHP.
 * Configure la session, les en-têtes CORS, la journalisation des erreurs,
 * et initialise le routage des requêtes HTTP.
 */

/**
 * Configuration de la session pour sécuriser les cookies.
 * - 'SameSite=Lax' limite l'envoi des cookies à certaines requêtes cross-site.
 * - 'secure=false' pour permettre le développement local (non HTTPS).
 * - Autres paramètres : durée de vie, chemin, domaine, httponly.
 */
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', 0);
session_set_cookie_params([
    'lifetime' => 86400, // Durée de vie du cookie de session (24 heures)
    'path' => '/', // Cookie disponible sur tout le domaine
    'domain' => 'localhost', // Domaine pour le développement local
    'secure' => false, // Non sécurisé pour localhost (pas de HTTPS)
    'httponly' => true, // Cookie inaccessible via JavaScript
    'samesite' => 'Lax' // Protection contre les attaques CSRF
]);

/**
 * Configuration de la journalisation des erreurs.
 * - Active la journalisation des erreurs dans un fichier spécifique.
 * - Le fichier error.log est créé dans le répertoire courant.
 */
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

/**
 * Configuration des en-têtes CORS pour permettre les requêtes cross-origin.
 * - Autorise les requêtes depuis http://localhost:3000 (ex. frontend React).
 * - Permet l'envoi de cookies (credentials).
 * - Définit les méthodes et en-têtes autorisés.
 */
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Expose-Headers: *");

/**
 * Gestion des requêtes CORS préliminaires (OPTIONS).
 * Renvoie une réponse 204 No Content et arrête l'exécution.
 */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 204 No Content");
    exit();
}

/**
 * Démarre la session pour gérer l'authentification et les données utilisateur.
 */
session_start();

/**
 * Configuration de l'affichage des erreurs pour le développement.
 * - Affiche toutes les erreurs (E_ALL) pour faciliter le débogage.
 * - À désactiver en production pour des raisons de sécurité.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Inclusion des classes nécessaires pour le routage et la logique métier.
 */
require_once 'Router.php';
require_once 'RoleController.php';
require_once 'AuthController.php';
require_once 'RecipeController.php';
require_once 'CommentController.php';

/**
 * Initialisation des contrôleurs avec les chemins vers les fichiers de données JSON.
 * - Chaque contrôleur gère une entité spécifique (authentification, rôles, recettes, commentaires).
 */
$router = new Router();
$authController = new AuthController(__DIR__ . '/data/users.json');
$roleController = new RoleController(__DIR__ . '/data/roles.json', $authController);
$recipeController = new RecipeController(__DIR__ . '/data/recipe.json');
$commentController = new CommentController(__DIR__ . '/data/comments.json');

/**
 * Enregistrement des routes pour l'authentification.
 * - POST /register : Inscription d'un nouvel utilisateur.
 * - POST /login : Connexion d'un utilisateur.
 * - POST /logout : Déconnexion de l'utilisateur.
 */
$router->register('POST', '/register', [$authController, 'handleRegister']);
$router->register('POST', '/login', [$authController, 'handleLogin']);
$router->register('POST', '/logout', [$authController, 'handleLogout']);

/**
 * Route pour récupérer les informations de l'utilisateur connecté.
 * - GET /user : Retourne les données de la session utilisateur ou une erreur 401 si non connecté.
 */
$router->register('GET', '/user', function () use ($authController) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(["error" => "Utilisateur non connecté"]);
        return;
    }

    http_response_code(200);
    echo json_encode($_SESSION['user']);
});

/**
 * Enregistrement des routes pour la gestion des recettes.
 * - GET /recipes : Liste toutes les recettes.
 * - GET /recipes/search : Recherche de recettes.
 * - POST /recipes : Ajoute une nouvelle recette.
 * - DELETE /recipes/{id} : Supprime une recette spécifique.
 * - PUT /recipes/{id} : Met à jour une recette spécifique.
 */
$router->register('GET', '/recipes', [$recipeController, 'getRecipes']);
$router->register('GET', '/recipes/search', [$recipeController, 'searchRecipes']);
$router->register('POST', '/recipes', [$recipeController, 'addRecipe']);
$router->register('DELETE', '/recipes/{id}', function ($id) use ($recipeController) {
    $recipeController->deleteRecipe((int)$id);
});
$router->register('PUT', '/recipes/{id}', function ($id) use ($recipeController) {
    $recipeController->updateRecipe((int)$id);
});

/**
 * Route pour gérer les likes sur les recettes.
 * - POST /like : Ajoute ou retire un like sur une recette.
 */
$router->register('POST', '/like', [$recipeController, 'handleLike']);

/**
 * Enregistrement des routes pour la gestion des commentaires.
 * - GET /comments : Liste tous les commentaires.
 * - POST /comments : Ajoute un nouveau commentaire.
 * - DELETE /comments/{id} : Supprime un commentaire spécifique.
 * - PUT /comments/{id} : Met à jour un commentaire spécifique.
 */
$router->register('GET', '/comments', [$commentController, 'getComments']);
$router->register('POST', '/comments', [$commentController, 'addComment']);
$router->register('DELETE', '/comments/{id}', function ($id) use ($commentController) {
    $commentController->deleteComment((int)$id);
});
$router->register('PUT', '/comments/{id}', function ($id) use ($commentController) {
    $commentController->updateComment((int)$id);
});

/**
 * Enregistrement des routes pour la gestion des rôles.
 * - GET /roles : Liste tous les rôles.
 * - GET /roles/requests/pending : Liste les demandes de rôle en attente.
 * - POST /roles/request : Soumet une demande de rôle.
 * - PUT /roles/requests/{id}/{action} : Traite une demande de rôle (accepter/refuser).
 */
$router->register('GET', '/roles', [$roleController, 'getRoles']);
$router->register('GET', '/roles/requests/pending', [$roleController, 'getPendingRequests']);
$router->register('POST', '/roles/request', function () use ($roleController) {
    // Récupère les données JSON de la requête
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Vérifie la présence des champs requis
    if (!isset($input['userId'], $input['requestedRole'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing userId or requestedRole"]);
        return;
    }

    // Soumet la demande de rôle
    $roleController->requestRole($input['userId'], $input['requestedRole']);
});
$router->register('PUT', '/roles/requests/{id}/{action}', function ($id, $action) use ($roleController) {
    // Récupère les données JSON de la requête
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Vérifie la présence des champs requis
    if (!isset($input['requestId'], $input['action'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing requestId or action in request body"]);
        return;
    }

    // Vérifie la cohérence entre les paramètres URL et le corps de la requête
    if ($input['requestId'] != $id || $input['action'] != $action) {
        http_response_code(400);
        echo json_encode(["error" => "Mismatched requestId or action between URL and body"]);
        return;
    }

    // Traite la demande de rôle (accepter ou refuser)
    $roleController->handleRoleRequest((int)$id, $action);
});

/**
 * Exécution du routage des requêtes.
 * - Capture les erreurs potentielles et renvoie une réponse 500 en cas d'échec.
 */
try {
    $router->handleRequest();
} catch (Exception $e) {
    // Journalise l'erreur pour le débogage
    error_log("Erreur de routage: " . $e->getMessage());
    // Définit le type de contenu de la réponse
    header('Content-Type: application/json');
    // Renvoie une réponse d'erreur 500
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>