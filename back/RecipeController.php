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
            http_response_code(401);
            echo json_encode(['error' => 'Authentification requise']);
            exit;
        }

        return $_SESSION['user'];
    }

    /**
     * Vérifie si l'utilisateur courant est propriétaire de la recette
     * ou s'il a un rôle administrateur.
     */
    private function checkRecipeOwnership(array $recipe): void
    {
        $user = $this->checkAuth();
        $authorId = $recipe['Author']['id'] ?? null;
        $userId = $user['id'] ?? null;

        if ($user['role'] !== 'admin' && (int)$userId !== (int)$authorId) {
            http_response_code(403);
            echo json_encode(['error' => 'Action réservée à l\'auteur ou administrateur']);
            exit;
        }
    }
     /**
     * Vérifie si l'utilisateur courant a un rôle chef
     * ou s'il a un rôle administrateur.
     */
    private function checkIfCanAddRecipe(): array
    {
        $user = $this->checkAuth();
    
        if (!in_array($user['role'], ['admin', 'chef'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Seuls les administrateurs ou chefs peuvent ajouter une recette']);
            exit;
        }
    
        return $user;
    }
    

    /**
     * Retourne toutes les recettes sous forme de JSON.
     */
    public function getRecipes(): void
    {
        header('Content-Type: application/json');
        try {
            $recipes = $this->getAllRecipes();
            echo json_encode($recipes);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

  /**
 * Recherche les recettes contenant un mot-clé dans le nom, les ingrédients ou les étapes.
 *
 * @param string $searchTerm Le mot-clé à rechercher.
 * @return array Liste des recettes filtrées.
 */
public function getRecetteBySearch(string $searchTerm): array
{
    // Vérifie que le fichier des recettes existe
    if (!file_exists($this->filePath)) {
        return [];
    }

    // Lit et décode le contenu JSON en tableau PHP
    $content = file_get_contents($this->filePath);
    $recettes = json_decode($content, true);

    // Si le contenu est vide ou invalide, retourne un tableau vide
    if (!is_array($recettes)) {
        return [];
    }

    // Nettoyage du terme de recherche : suppression des espaces inutiles + conversion en minuscules
    $searchTerm = strtolower(trim($searchTerm));

    // Si la recherche est vide, retourne toutes les recettes
    if ($searchTerm === '') {
        return $recettes;
    }

    // Filtrage des recettes : on garde celles dont le nom, les ingrédients ou les étapes contiennent le mot-clé
    $filtered = array_filter($recettes, function ($recette) use ($searchTerm) {
        // Extraction et mise en minuscule des champs recherchables
        $name = strtolower($recette['name'] ?? '');
        $ingredients = strtolower(implode(' ', $recette['ingredients'] ?? []));
        $steps = strtolower(implode(' ', $recette['stepsFR'] ?? []));

        // Recherche du terme dans les trois champs
        return strpos($name, $searchTerm) !== false
            || strpos($ingredients, $searchTerm) !== false
            || strpos($steps, $searchTerm) !== false;
    });

    // Réindexe les résultats (évite les clés non consécutives)
    return array_values($filtered);
}

    /**
     * Ajoute une nouvelle recette en validant les champs requis.
     */
    public function addRecipe(): void
    {
        header('Content-Type: application/json');
    
        // Vérifie l'authentification et les autorisations, retourne les infos utilisateur
        $user = $this->checkIfCanAddRecipe();
    
        // Vérifie le type de contenu attendu
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            http_response_code(400);
            echo json_encode(['error' => 'Content-Type doit être application/json']);
            return;
        }
    
        // Récupère les données envoyées dans le corps de la requête
        $input = json_decode(file_get_contents('php://input'), true);
    
        // Liste des champs requis pour une recette
        $requiredFields = ['name', 'nameFR', 'ingredients', 'stepsFR'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Le champ $field est requis"]);
                return;
            }
        }
    
        // Charge toutes les recettes existantes
        $recipes = $this->getAllRecipes();
    
        // Prépare la nouvelle recette à ajouter
        $newRecipe = [
            'id' => count($recipes) + 1,
            'name' => $input['name'],
            'nameFR' => $input['nameFR'],
            'Author' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'ingredients' => $input['ingredients'],
            'stepsFR' => $input['stepsFR'],
            'imageURL'=> $input['imageURL'] ?? '', // pour éviter des erreurs si imageURL est manquant
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s'),
            'likes' => 0
        ];
    
        // Ajoute la nouvelle recette à la liste
        $recipes[] = $newRecipe;
    
        // Sauvegarde la liste des recettes mise à jour
        $this->saveRecipes($recipes);
    
        // Répond avec un statut 201 (créé) et la recette ajoutée
        http_response_code(201);
        echo json_encode($newRecipe);
    }
    

    /**
     * Supprime une recette en fonction de son ID.
     * L'utilisateur doit être l'auteur ou admin.
     */
    public function deleteRecipe(int $id): void
    {
        header('Content-Type: application/json');
        $recipes = $this->getAllRecipes();
        
        foreach ($recipes as $key => $recipe) {
            if ($recipe['id'] === $id) {
                $this->checkRecipeOwnership($recipe);

                array_splice($recipes, $key, 1);
                $this->saveRecipes($recipes);
                
                echo json_encode(['message' => 'Recette supprimée avec succès']);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Recette non trouvée']);
    }

    /**
     * Met à jour les champs modifiables d'une recette.
     */
    public function updateRecipe(int $id): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $recipes = $this->getAllRecipes();

        foreach ($recipes as &$recipe) {
            if ($recipe['id'] === $id) {
                $this->checkRecipeOwnership($recipe);

                $updatableFields = ['name', 'nameFR', 'ingredients', 'stepsFR', 'imageURL'];
                $hasUpdates = false;

                foreach ($updatableFields as $field) {
                    if (isset($input[$field])) {
                        $recipe[$field] = $input[$field];
                        $hasUpdates = true;
                    }
                }

                if (!$hasUpdates) {
                    echo json_encode($recipe); // Pas de modification
                    return;
                }

                $recipe['updatedAt'] = date('Y-m-d H:i:s');
                $this->saveRecipes($recipes);
                
                echo json_encode($recipe);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Recette non trouvée']);
    }

    /**
     * Récupère toutes les recettes depuis le fichier JSON.
     */
    private function getAllRecipes(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $data = file_get_contents($this->filePath);
        return json_decode($data, true) ?: [];
    }

    /**
     * Sauvegarde la liste complète des recettes dans le fichier JSON.
     */
    private function saveRecipes(array $recipes): void
    {
        file_put_contents(
            $this->filePath,
            json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}

