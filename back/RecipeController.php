<?php
// Active l'affichage de toutes les erreurs (utile en développement)
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Contrôleur chargé de la gestion des recettes (CRUD) à partir d'un fichier JSON.
 */
class RecipeController
{
    private string $filePath;

    /**
     * Constructeur de la classe, prend en paramètre le chemin du fichier JSON.
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Vérifie si l'utilisateur est authentifié via la session.
     * Retourne les infos de l'utilisateur s'il est connecté.
     */
    private function checkAuth(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user'])) {
            error_log("checkAuth: Échec - Aucune session utilisateur trouvée");
            http_response_code(401);
            echo json_encode(['error' => 'Utilisateur non authentifié']);
            exit;
        }

        $user = $_SESSION['user'];
        $userId = isset($user['id_user']) ? strval($user['id_user']) : (isset($user['id']) ? strval($user['id']) : null);

        if (!$userId) {
            error_log("checkAuth: Échec - ID utilisateur manquant dans la session, user=" . json_encode($user));
            http_response_code(401);
            echo json_encode(['error' => 'ID utilisateur manquant dans la session']);
            exit;
        }

        error_log("checkAuth: Succès - userId=$userId, user=" . json_encode($user));
        return array_merge($user, ['id_user' => $userId]);
    }

    /**
     * Vérifie si l'utilisateur courant est propriétaire de la recette
     * ou s'il a un rôle administrateur.
     */
    private function checkRecipeOwnership(array $recipe): void
    {
        $user = $this->checkAuth();
        $authorId = is_array($recipe['Author']) ? ($recipe['Author']['id'] ?? null) : null;
        $userId = $user['id_user'];

        if ($user['role'] !== 'admin' && strval($userId) !== strval($authorId)) {
            error_log("checkRecipeOwnership: Échec - userId=$userId, authorId=$authorId, role={$user['role']}");
            http_response_code(403);
            echo json_encode(['error' => 'Action réservée à l\'auteur ou administrateur']);
            exit;
        }

        error_log("checkRecipeOwnership: Succès - userId=$userId, authorId=$authorId, role={$user['role']}");
    }

    /**
     * Vérifie si l'utilisateur courant a un rôle chef
     * ou s'il a un rôle administrateur.
     */
    private function checkIfCanAddRecipe(): array
    {
        $user = $this->checkAuth();

        if (!in_array($user['role'], ['admin', 'chef', 'cuisinier'])) {
            error_log("checkIfCanAddRecipe: Échec - role={$user['role']} non autorisé");
            http_response_code(403);
            echo json_encode(['error' => 'Seuls les administrateurs, chefs ou cuisiniers peuvent ajouter une recette']);
            exit;
        }

        error_log("checkIfCanAddRecipe: Succès - role={$user['role']}");
        return $user;
    }

    /**
     * Retourne toutes les recettes sous forme de JSON.
     */
    public function getRecipes(): void
    {
        header('Content-Type: application/json');
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $recipes = $this->getAllRecipes();
            $recipes = array_slice($recipes, 0, $limit);
            error_log("getRecipes: Requête avec limit=$limit, retourne " . count($recipes) . " recettes, likes inclus: " . json_encode(array_map(fn($r) => ['id' => $r['id'], 'likes' => $r['likes'], 'likedBy' => $r['likedBy']], $recipes)));
            echo json_encode($recipes);
        } catch (Exception $e) {
            error_log("Erreur dans getRecipes: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

    /**
     * Recherche des recettes en fonction d'une chaîne de recherche dans les champs name et nameFR.
     */
    public function searchRecipes(): void
    {
        header('Content-Type: application/json');
        try {
            $query = isset($_GET['q']) ? trim($_GET['q']) : '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            $recipes = $this->getAllRecipes();
            $filteredRecipes = [];

            if (!empty($query)) {
                foreach ($recipes as $recipe) {
                    $nameMatch = isset($recipe['name']) && stripos($recipe['name'], $query) !== false;
                    $nameFRMatch = isset($recipe['nameFR']) && stripos($recipe['nameFR'], $query) !== false;
                    if ($nameMatch || $nameFRMatch) {
                        $filteredRecipes[] = $recipe;
                    }
                }
            } else {
                $filteredRecipes = $recipes;
            }

            $filteredRecipes = array_slice($filteredRecipes, 0, $limit);
            error_log("searchRecipes: Query='$query', limit=$limit, retourne " . count($filteredRecipes) . " recettes, likes inclus: " . json_encode(array_map(fn($r) => ['id' => $r['id'], 'likes' => $r['likes'], 'likedBy' => $r['likedBy']], $filteredRecipes)));
            echo json_encode($filteredRecipes);
        } catch (Exception $e) {
            error_log("Erreur dans searchRecipes: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

    /**
     * Ajoute une nouvelle recette en validant les champs requis.
     */
    public function addRecipe(): void
    {
        header('Content-Type: application/json');

        try {
            $user = $this->checkIfCanAddRecipe();

            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                http_response_code(400);
                echo json_encode(['error' => 'Content-Type doit être application/json']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON invalide']);
                return;
            }

            $requiredFields = ['name', 'nameFR', 'ingredients', 'stepsFR'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Le champ $field est requis"]);
                    return;
                }
            }

            $recipes = $this->getAllRecipes();
            $newId = max(array_column($recipes, 'id') ?: [0]) + 1;

            $newRecipe = [
                'id' => $newId,
                'name' => $input['name'],
                'nameFR' => $input['nameFR'],
                'Author' => [
                    'id' => $user['id_user'],
                    'name' => $user['name'],
                    'prenom' => $user['prenom'] ?? '',
                    'email' => $user['email'] ?? '',
                    'role' => $user['role']
                ],
                'ingredients' => $input['ingredients'],
                'stepsFR' => $input['stepsFR'],
                'imageURL' => $input['imageURL'] ?? '',
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s'),
                'likes' => 0,
                'likedBy' => []
            ];

            error_log("addRecipe: Nouvelle recette ID=$newId, données=" . json_encode($newRecipe));

            $recipes[] = $newRecipe;
            $this->saveRecipes($recipes, 'addRecipe');

            http_response_code(201);
            echo json_encode($newRecipe);
        } catch (Exception $e) {
            error_log("Erreur dans addRecipe: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

    /**
     * Supprime une recette en fonction de son ID.
     * L'utilisateur doit être l'auteur ou admin.
     */
    public function deleteRecipe(int $id): void
    {
        header('Content-Type: application/json');
        try {
            $recipes = $this->getAllRecipes();

            foreach ($recipes as $key => $recipe) {
                if ($recipe['id'] === $id) {
                    $this->checkRecipeOwnership($recipe);
                    array_splice($recipes, $key, 1);
                    $this->saveRecipes($recipes, 'deleteRecipe');
                    error_log("deleteRecipe: Recette ID=$id supprimée avec succès");
                    echo json_encode(['message' => 'Recette supprimée avec succès']);
                    return;
                }
            }

            http_response_code(404);
            echo json_encode(['error' => 'Recette non trouvée']);
        } catch (Exception $e) {
            error_log("Erreur dans deleteRecipe: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

    /**
     * Met à jour les champs modifiables d'une recette.
     */
    public function updateRecipe(int $id): void
    {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON invalide']);
                return;
            }
            $recipes = $this->getAllRecipes();

            foreach ($recipes as &$recipe) {
                if ($recipe['id'] === $id) {
                    $this->checkRecipeOwnership($recipe);

                    if (!isset($recipe['likes']) || !is_numeric($recipe['likes'])) {
                        $recipe['likes'] = 0;
                        error_log("updateRecipe: Initialisation likes=0 pour recette ID=$id");
                    }
                    if (!isset($recipe['likedBy']) || !is_array($recipe['likedBy'])) {
                        $recipe['likedBy'] = [];
                        error_log("updateRecipe: Initialisation likedBy=[] pour recette ID=$id");
                    }

                    $updatableFields = ['name', 'nameFR', 'ingredients', 'stepsFR', 'imageURL'];
                    $hasUpdates = false;

                    foreach ($updatableFields as $field) {
                        if (isset($input[$field])) {
                            $recipe[$field] = $input[$field];
                            $hasUpdates = true;
                        }
                    }

                    if (isset($input['test']) && $input['test'] === true) {
                        echo json_encode(['message' => 'Test d\'édition autorisé']);
                        return;
                    }

                    if (!$hasUpdates) {
                        echo json_encode($recipe);
                        return;
                    }

                    $recipe['updatedAt'] = date('Y-m-d H:i:s');
                    $this->saveRecipes($recipes, 'updateRecipe');
                    error_log("updateRecipe: Recette ID=$id mise à jour, données=" . json_encode($recipe));
                    echo json_encode($recipe);
                    return;
                }
            }

            http_response_code(404);
            echo json_encode(['error' => 'Recette non trouvée']);
        } catch (Exception $e) {
            error_log("Erreur dans updateRecipe: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

    /**
     * Gère les actions de like/unlike pour une recette.
     */
    public function handleLike(): void
    {
        header('Content-Type: application/json');
        try {
            $user = $this->checkAuth();
            $userId = $user['id_user'];

            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                http_response_code(400);
                echo json_encode(['error' => 'Content-Type doit être application/json']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON invalide']);
                return;
            }

            $recipeId = isset($input['recipeId']) ? (int)$input['recipeId'] : null;
            $action = isset($input['action']) ? $input['action'] : null;

            if (!$recipeId || !in_array($action, ['like', 'unlike'])) {
                http_response_code(400);
                echo json_encode(['error' => 'recipeId et action (like/unlike) sont requis']);
                return;
            }

            $recipes = $this->getAllRecipes();
            foreach ($recipes as &$recipe) {
                if ($recipe['id'] === $recipeId) {
                    if (!isset($recipe['likes']) || !is_numeric($recipe['likes'])) {
                        $recipe['likes'] = 0;
                        error_log("handleLike: Initialisation likes=0 pour recette ID=$recipeId");
                    }
                    if (!isset($recipe['likedBy']) || !is_array($recipe['likedBy'])) {
                        $recipe['likedBy'] = [];
                        error_log("handleLike: Initialisation likedBy=[] pour recette ID=$recipeId");
                    }

                    $likedBy = array_map('strval', $recipe['likedBy']);
                    $hasLiked = in_array($userId, $likedBy, true);

                    error_log("handleLike: userId=$userId, action=$action, recipeId=$recipeId, likes={$recipe['likes']}, likedBy=" . json_encode($likedBy) . ", hasLiked=" . ($hasLiked ? 'true' : 'false') . ", input=" . json_encode($input));

                    if ($action === 'like' && !$hasLiked) {
                        $likedBy[] = $userId;
                        $recipe['likes'] = (int)$recipe['likes'] + 1;
                        error_log("Like ajouté pour recette $recipeId par utilisateur $userId, nouveau total={$recipe['likes']}");
                    } elseif ($action === 'unlike' && $hasLiked) {
                        $likedBy = array_filter($likedBy, fn($id) => $id !== $userId);
                        $recipe['likes'] = max(0, (int)$recipe['likes'] - 1);
                        error_log("Like retiré pour recette $recipeId par utilisateur $userId, nouveau total={$recipe['likes']}");
                    } else {
                        http_response_code(400);
                        echo json_encode([
                            'error' => 'Action invalide pour l\'état actuel',
                            'details' => $action === 'like' ? 'Utilisateur a déjà aimé' : 'Utilisateur n\'a pas aimé',
                            'userId' => $userId,
                            'likes' => (int)$recipe['likes'],
                            'likedBy' => $likedBy,
                            'hasLiked' => $hasLiked
                        ]);
                        return;
                    }

                    $recipe['likedBy'] = array_values($likedBy);
                    $this->saveRecipes($recipes, 'handleLike');
                    echo json_encode([
                        'likes' => (int)$recipe['likes'],
                        'likedByUser' => in_array($userId, $likedBy)
                    ]);
                    return;
                }
            }

            http_response_code(404);
            echo json_encode(['error' => 'Recette non trouvée']);
        } catch (Exception $e) {
            error_log("Erreur dans handleLike: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

    /**
     * Récupère toutes les recettes depuis le fichier JSON et normalise les données.
     */
    private function getAllRecipes(): array
    {
        if (!file_exists($this->filePath)) {
            error_log("Fichier JSON introuvable: " . $this->filePath);
            return [];
        }

        $data = file_get_contents($this->filePath);
        $recipes = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erreur JSON dans getAllRecipes: " . json_last_error_msg());
            return [];
        }

        if (!is_array($recipes)) {
            error_log("Données JSON invalides dans getAllRecipes: contenu n'est pas un tableau");
            return [];
        }

        $validRecipes = [];
        foreach ($recipes as $recipe) {
            if (!isset($recipe['id']) || !is_numeric($recipe['id'])) {
                error_log("Recette sans ID valide détectée: " . json_encode($recipe));
                continue;
            }
            if (!isset($recipe['likes']) || !is_numeric($recipe['likes'])) {
                $recipe['likes'] = 0;
                error_log("Champ 'likes' manquant ou invalide pour recette ID {$recipe['id']}, initialisé à 0");
            } else {
                $recipe['likes'] = (int)$recipe['likes'];
                error_log("Champ 'likes' pour recette ID {$recipe['id']} converti en entier: {$recipe['likes']}");
            }
            if (!isset($recipe['likedBy']) || !is_array($recipe['likedBy'])) {
                $recipe['likedBy'] = [];
                error_log("Champ 'likedBy' manquant ou invalide pour recette ID {$recipe['id']}, initialisé à []");
            } else {
                $recipe['likedBy'] = array_map('strval', $recipe['likedBy']);
                error_log("Champ 'likedBy' pour recette ID {$recipe['id']} normalisé: " . json_encode($recipe['likedBy']));
            }
            if (!isset($recipe['Author']) || is_string($recipe['Author'])) {
                $recipe['Author'] = [
                    'id' => 'unknown',
                    'name' => $recipe['Author'] ?? 'Unknown',
                    'prenom' => '',
                    'email' => '',
                    'role' => 'unknown'
                ];
                error_log("Normalisation Author pour recette ID {$recipe['id']}: " . json_encode($recipe['Author']));
            } elseif (is_array($recipe['Author'])) {
                $recipe['Author']['id'] = strval($recipe['Author']['id'] ?? 'unknown');
            }
            $validRecipes[] = $recipe;
        }

        error_log("getAllRecipes: Retourne " . count($validRecipes) . " recettes valides sur " . count($recipes) . " totales");
        return $validRecipes;
    }

    /**
     * Sauvegarde la liste complète des recettes dans le fichier JSON avec verrouillage.
     */
    private function saveRecipes(array $recipes, string $context): void
    {
        $changedRecipes = array_filter($recipes, fn($r) => isset($r['id']));
        error_log("saveRecipes ($context): Écriture dans {$this->filePath}, nombre de recettes=" . count($changedRecipes));

        $fp = fopen($this->filePath, 'w');
        if (flock($fp, LOCK_EX)) {
            if (!fwrite($fp, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
                error_log("Erreur lors de l'écriture dans le fichier: " . $this->filePath);
                throw new Exception("Impossible de sauvegarder les recettes");
            }
            flock($fp, LOCK_UN);
        } else {
            error_log("Impossible d'obtenir le verrou sur le fichier: " . $this->filePath);
            throw new Exception("Impossible de sauvegarder les recettes");
        }
        fclose($fp);
    }
}