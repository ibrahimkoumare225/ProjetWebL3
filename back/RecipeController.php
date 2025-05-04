<?php

// Active l'affichage de toutes les erreurs (utile en développement)
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Contrôleur chargé de la gestion des recettes (CRUD).
 * Gère les opérations de lecture, création, mise à jour, suppression et gestion des likes
 * à partir d'un fichier JSON.
 */
class RecipeController
{
    /**
     * Chemin vers le fichier JSON contenant les données des recettes.
     * @var string
     */
    private string $filePath;

    /**
     * Constructeur de la classe.
     * Initialise le chemin du fichier JSON des recettes.
     *
     * @param string $filePath Chemin vers le fichier JSON.
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Vérifie si l'utilisateur est authentifié via la session.
     * Retourne les informations de l'utilisateur connecté, enrichies avec un champ id_user.
     * Termine l'exécution avec une erreur 401 si non authentifié ou si l'ID est manquant.
     *
     * @return array Données de l'utilisateur connecté.
     */
    private function checkAuth(): array
    {
        // Démarre la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifie la présence de l'utilisateur dans la session
        if (empty($_SESSION['user'])) {
            error_log("checkAuth: Échec - Aucune session utilisateur trouvée");
            http_response_code(401);
            echo json_encode(['error' => 'Utilisateur non authentifié']);
            exit;
        }

        $user = $_SESSION['user'];
        // Extrait l'ID utilisateur, en priorisant id_user ou id
        $userId = isset($user['id_user']) ? strval($user['id_user']) : (isset($user['id']) ? strval($user['id']) : null);

        // Vérifie la validité de l'ID utilisateur
        if (!$userId) {
            error_log("checkAuth: Échec - ID utilisateur manquant dans la session, user=" . json_encode($user));
            http_response_code(401);
            echo json_encode(['error' => 'ID utilisateur manquant dans la session']);
            exit;
        }

        error_log("checkAuth: Succès - userId=$userId, user=" . json_encode($user));
        // Retourne les données utilisateur avec id_user standardisé
        return array_merge($user, ['id_user' => $userId]);
    }

    /**
     * Vérifie si l'utilisateur courant est propriétaire de la recette ou administrateur.
     * Termine l'exécution avec une erreur 403 si non autorisé.
     *
     * @param array $recipe Données de la recette.
     * @return void
     */
    private function checkRecipeOwnership(array $recipe): void
    {
        $user = $this->checkAuth();
        // Extrait l'ID de l'auteur de la recette
        $authorId = is_array($recipe['Author']) ? ($recipe['Author']['id'] ?? null) : null;
        $userId = $user['id_user'];

        // Vérifie les autorisations (admin ou auteur)
        if ($user['role'] !== 'admin' && strval($userId) !== strval($authorId)) {
            error_log("checkRecipeOwnership: Échec - userId=$userId, authorId=$authorId, role={$user['role']}");
            http_response_code(403);
            echo json_encode(['error' => 'Action réservée à l\'auteur ou administrateur']);
            exit;
        }

        error_log("checkRecipeOwnership: Succès - userId=$userId, authorId=$authorId, role={$user['role']}");
    }

    /**
     * Vérifie si l'utilisateur courant a le rôle 'admin' ou 'chef' pour ajouter une recette.
     * Termine l'exécution avec une erreur 403 si non autorisé.
     *
     * @return array Données de l'utilisateur connecté.
     */
    private function checkIfCanAddRecipe(): array
    {
        $user = $this->checkAuth();

        // Vérifie les rôles autorisés
        if (!in_array($user['role'], ['admin', 'chef'])) {
            error_log("checkIfCanAddRecipe: Échec - role={$user['role']} non autorisé");
            http_response_code(403);
            echo json_encode(['error' => 'Seuls les administrateurs ou chefs peuvent ajouter une recette']);
            exit;
        }

        error_log("checkIfCanAddRecipe: Succès - role={$user['role']}");
        return $user;
    }

    /**
     * Récupère et retourne toutes les recettes sous forme de JSON.
     * Applique une limite au nombre de recettes retournées si spécifié.
     *
     * @return void
     */
    public function getRecipes(): void
    {
        header('Content-Type: application/json');
        try {
            // Récupère la limite depuis les paramètres GET (par défaut 10)
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $recipes = $this->getAllRecipes();
            // Limite le nombre de recettes retournées
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
     * Recherche des recettes par nom (name ou nameFR) et retourne les résultats sous forme de JSON.
     * Applique une limite au nombre de recettes retournées si spécifié.
     *
     * @return void
     */
    public function searchRecipes(): void
    {
        header('Content-Type: application/json');
        try {
            // Récupère la requête de recherche et la limite depuis les paramètres GET
            $query = isset($_GET['q']) ? trim($_GET['q']) : '';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            $recipes = $this->getAllRecipes();
            $filteredRecipes = [];

            // Filtre les recettes en fonction de la requête
            if (!empty($query)) {
                foreach ($recipes as $recipe) {
                    $nameMatch = isset($recipe['name']) && stripos($recipe['name'], $query) !== false;
                    $nameFRMatch = isset($recipe['nameFR']) && stripos($recipe['nameFR'], $query) !== false;
                    if ($nameMatch || $nameFRMatch) {
                        $filteredRecipes[] = $recipe;
                    }
                }
            } else {
                $filteredRecipes = $recipes; // Retourne toutes les recettes si pas de requête
            }

            // Limite le nombre de recettes retournées
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
     * Ajoute une nouvelle recette après validation des données et des autorisations.
     * Enregistre la recette dans le fichier JSON.
     *
     * @return void
     */
    public function addRecipe(): void
    {
        header('Content-Type: application/json');

        try {
            // Vérifie si l'utilisateur a le droit d'ajouter une recette
            $user = $this->checkIfCanAddRecipe();

            // Vérifie le type de contenu
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                http_response_code(400);
                echo json_encode(['error' => 'Content-Type doit être application/json']);
                return;
            }

            // Récupère et valide les données JSON
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON invalide']);
                return;
            }

            // Vérifie les champs requis
            $requiredFields = ['name', 'nameFR', 'ingredients', 'ingredientsFR','steps','stepsFR'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Le champ $field est requis"]);
                    return;
                }
            }

            $recipes = $this->getAllRecipes();
            // Génère un nouvel ID unique
            $newId = max(array_column($recipes, 'id') ?: [0]) + 1;

            // Crée la nouvelle recette
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
                'ingredientsFR' => $input['ingredientsFR'],
                'steps' => $input['steps'],
                'stepsFR' => $input['stepsFR'],
                'imageURL' => $input['imageURL'] ?? '',
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s'),
                'likes' => 0,
                'likedBy' => []
            ];

            error_log("addRecipe: Nouvelle recette ID=$newId, données=" . json_encode($newRecipe));

            // Ajoute la recette à la liste
            $recipes[] = $newRecipe;
            $this->saveRecipes($recipes, 'addRecipe');

            // Réponse de succès
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
     * Vérifie les autorisations (auteur ou admin) avant suppression.
     *
     * @param int $id ID de la recette à supprimer.
     * @return void
     */
    public function deleteRecipe(int $id): void
    {
        header('Content-Type: application/json');
        try {
            $recipes = $this->getAllRecipes();

            // Recherche et supprime la recette
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

            // Recette non trouvée
            http_response_code(404);
            echo json_encode(['error' => 'Recette non trouvée']);
        } catch (Exception $e) {
            error_log("Erreur dans deleteRecipe: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

    /**
     * Met à jour une recette en fonction de son ID.
     * Vérifie les autorisations (auteur ou admin) et met à jour les champs modifiables.
     *
     * @param int $id ID de la recette à mettre à jour.
     * @return void
     */
    public function updateRecipe(int $id): void
    {
        header('Content-Type: application/json');
        try {
            // Récupère et valide les données JSON
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON invalide']);
                return;
            }
            $recipes = $this->getAllRecipes();

            // Recherche et met à jour la recette
            foreach ($recipes as &$recipe) {
                if ($recipe['id'] === $id) {
                    $this->checkRecipeOwnership($recipe);

                    // Initialise les champs likes et likedBy si absents
                    if (!isset($recipe['likes']) || !is_numeric($recipe['likes'])) {
                        $recipe['likes'] = 0;
                        error_log("updateRecipe: Initialisation likes=0 pour recette ID=$id");
                    }
                    if (!isset($recipe['likedBy']) || !is_array($recipe['likedBy'])) {
                        $recipe['likedBy'] = [];
                        error_log("updateRecipe: Initialisation likedBy=[] pour recette ID=$id");
                    }

                    // Champs modifiables
                    $updatableFields = ['name', 'nameFR', 'ingredients','ingredientsFR' ,'steps','stepsFR', 'imageURL'];
                    $hasUpdates = false;

                    // Met à jour les champs fournis
                    foreach ($updatableFields as $field) {
                        if (isset($input[$field])) {
                            $recipe[$field] = $input[$field];
                            $hasUpdates = true;
                        }
                    }

                    // Mode test pour vérifier les autorisations
                    if (isset($input['test']) && $input['test'] === true) {
                        echo json_encode(['message' => 'Test d\'édition autorisé']);
                        return;
                    }

                    // Retourne la recette inchangée si aucun champ n'est modifié
                    if (!$hasUpdates) {
                        echo json_encode($recipe);
                        return;
                    }

                    // Met à jour la date de modification
                    $recipe['updatedAt'] = date('Y-m-d H:i:s');
                    $this->saveRecipes($recipes, 'updateRecipe');
                    error_log("updateRecipe: Recette ID=$id mise à jour, données=" . json_encode($recipe));
                    echo json_encode($recipe);
                    return;
                }
            }

            // Recette non trouvée
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
     * Met à jour le compteur de likes et la liste des utilisateurs ayant aimé.
     *
     * @return void
     */
    public function handleLike(): void
    {
        header('Content-Type: application/json');
        try {
            // Vérifie l'authentification de l'utilisateur
            $user = $this->checkAuth();
            $userId = $user['id_user'];

            // Vérifie le type de contenu
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                http_response_code(400);
                echo json_encode(['error' => 'Content-Type doit être application/json']);
                return;
            }

            // Récupère et valide les données JSON
            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON invalide']);
                return;
            }

            $recipeId = isset($input['recipeId']) ? (int)$input['recipeId'] : null;
            $action = isset($input['action']) ? $input['action'] : null;

            // Vérifie les champs requis
            if (!$recipeId || !in_array($action, ['like', 'unlike'])) {
                http_response_code(400);
                echo json_encode(['error' => 'recipeId et action (like/unlike) sont requis']);
                return;
            }

            $recipes = $this->getAllRecipes();
            foreach ($recipes as &$recipe) {
                if ($recipe['id'] === $recipeId) {
                    // Initialise les champs likes et likedBy si absents
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

                    // Gère l'action like
                    if ($action === 'like' && !$hasLiked) {
                        $likedBy[] = $userId;
                        $recipe['likes'] = (int)$recipe['likes'] + 1;
                        error_log("Like ajouté pour recette $recipeId par utilisateur $userId, nouveau total={$recipe['likes']}");
                    // Gère l'action unlike
                    } elseif ($action === 'unlike' && $hasLiked) {
                        $likedBy = array_filter($likedBy, fn($id) => $id !== $userId);
                        $recipe['likes'] = max(0, (int)$recipe['likes'] - 1);
                        error_log("Like retiré pour recette $recipeId par utilisateur $userId, nouveau total={$recipe['likes']}");
                    } else {
                        // Action invalide pour l'état actuel
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

                    // Met à jour la liste likedBy
                    $recipe['likedBy'] = array_values($likedBy);
                    $this->saveRecipes($recipes, 'handleLike');
                    // Réponse avec l'état des likes
                    echo json_encode([
                        'likes' => (int)$recipe['likes'],
                        'likedByUser' => in_array($userId, $likedBy)
                    ]);
                    return;
                }
            }

            // Recette non trouvée
            http_response_code(404);
            echo json_encode(['error' => 'Recette non trouvée']);
        } catch (Exception $e) {
            error_log("Erreur dans handleLike: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
        }
    }

    /**
     * Récupère toutes les recettes depuis le fichier JSON.
     * Normalise les données (likes, likedBy, Author) et gère les erreurs.
     *
     * @return array Liste des recettes normalisées.
     */
    private function getAllRecipes(): array
    {
        // Vérifie l'existence du fichier
        if (!file_exists($this->filePath)) {
            error_log("Fichier JSON introuvable: " . $this->filePath);
            return [];
        }

        // Lit et décode le contenu JSON
        $data = file_get_contents($this->filePath);
        $recipes = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erreur JSON dans getAllRecipes: " . json_last_error_msg());
            return [];
        }

        // Vérifie que les données sont un tableau
        if (!is_array($recipes)) {
            error_log("Données JSON invalides dans getAllRecipes: contenu n'est pas un tableau");
            return [];
        }

        $validRecipes = [];
        // Normalise chaque recette
        foreach ($recipes as $recipe) {
            // Vérifie la présence d'un ID valide
            if (!isset($recipe['id']) || !is_numeric($recipe['id'])) {
                error_log("Recette sans ID valide détectée: " . json_encode($recipe));
                continue;
            }
            // Normalise le champ likes
            if (!isset($recipe['likes']) || !is_numeric($recipe['likes'])) {
                $recipe['likes'] = 0;
                error_log("Champ 'likes' manquant ou invalide pour recette ID {$recipe['id']}, initialisé à 0");
            } else {
                $recipe['likes'] = (int)$recipe['likes'];
                error_log("Champ 'likes' pour recette ID {$recipe['id']} converti en entier: {$recipe['likes']}");
            }
            // Normalise le champ likedBy
            if (!isset($recipe['likedBy']) || !is_array($recipe['likedBy'])) {
                $recipe['likedBy'] = [];
                error_log("Champ 'likedBy' manquant ou invalide pour recette ID {$recipe['id']}, initialisé à []");
            } else {
                $recipe['likedBy'] = array_map('strval', $recipe['likedBy']);
                error_log("Champ 'likedBy' pour recette ID {$recipe['id']} normalisé: " . json_encode($recipe['likedBy']));
            }
            // Normalise le champ Author
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
     * Sauvegarde la liste des recettes dans le fichier JSON avec verrouillage exclusif.
     * Journalise l'opération pour le suivi.
     *
     * @param array  $recipes Liste des recettes à sauvegarder.
     * @param string $context Contexte de l'opération (ex. : addRecipe, deleteRecipe).
     * @return void
     * @throws Exception Si l'écriture ou le verrouillage échoue.
     */
    private function saveRecipes(array $recipes, string $context): void
    {
        $changedRecipes = array_filter($recipes, fn($r) => isset($r['id']));
        error_log("saveRecipes ($context): Écriture dans {$this->filePath}, nombre de recettes=" . count($changedRecipes));

        // Ouvre le fichier en mode écriture
        $fp = fopen($this->filePath, 'w');
        // Acquiert un verrou exclusif
        if (flock($fp, LOCK_EX)) {
            // Écrit les données JSON formatées
            if (!fwrite($fp, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
                error_log("Erreur lors de l'écriture dans le fichier: " . $this->filePath);
                throw new Exception("Impossible de sauvegarder les recettes");
            }
            flock($fp, LOCK_UN); // Libère le verrou
        } else {
            error_log("Impossible d'obtenir le verrou sur le fichier: " . $this->filePath);
            throw new Exception("Impossible de sauvegarder les recettes");
        }
        fclose($fp);
    }
}
?>