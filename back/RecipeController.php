<?php
class RecipeController
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    // Récupérer toutes les recettes
    public function getRecipes(): void
    {
        header('Content-Type: application/json');

        $recipes = $this->getAllRecipes();
        echo json_encode($recipes);
    }

    // Ajouter une nouvelle recette
    public function addRecipe(): void
    {
        header('Content-Type: application/json');

        // Vérifier le type de contenu
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Content-Type header']);
            return;
        }

        // Récupérer les données JSON
        $input = json_decode(file_get_contents('php://input'), true);

        // Vérifier les champs obligatoires
        if (!isset($input['name'], $input['nameFR'], $input['Without'], $input['ingredients'], $input['steps'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input. Missing required fields.']);
            return;
        }

        // Vérifier si l'utilisateur est connecté
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized. Please log in to add a recipe.']);
            return;
        }

        // Récupérer l'auteur depuis la session
        $author = [
            'id' => $_SESSION['user']['id'],
            'name' => $_SESSION['user']['name'],
            'prenom' => $_SESSION['user']['prenom'],
            'email' => $_SESSION['user']['email'],
            'role' => $_SESSION['user']['role'] // Inclure le rôle de l'utilisateur
        ];

        // Charger les recettes existantes
        $recipes = $this->getAllRecipes();

        // Créer une nouvelle recette
        $newRecipe = [
            'id' => count($recipes) + 1,
            'name' => $input['name'],
            'nameFR' => $input['nameFR'],
            'Author' => $author,
            'Without' => $input['Without'], // Restrictions alimentaires
            'ingredients' => $input['ingredients'], // Liste des ingrédients
            'steps' => $input['steps'], // Étapes de préparation
            'timers' => $input['timers'] ?? [], // Timers (facultatif)
            'imageURL' => $input['imageURL'] ?? null, // URL de l'image (facultatif)
            'originalURL' => $input['originalURL'] ?? null // URL originale (facultatif)
        ];

        // Ajouter la recette à la liste
        $recipes[] = $newRecipe;

        // Sauvegarder les recettes
        $this->saveRecipes($recipes);

        http_response_code(201);
        echo json_encode(['message' => 'Recette ajoutée avec succès', 'recipe' => $newRecipe]);
    }

    // Supprimer une recette par ID
    public function deleteRecipe(int $id): void
    {
        header('Content-Type: application/json');

        $recipes = $this->getAllRecipes();
        $filteredRecipes = array_filter($recipes, fn($recipe) => $recipe['id'] !== $id);

        if (count($recipes) === count($filteredRecipes)) {
            http_response_code(404);
            echo json_encode(['error' => 'Recette non trouvée']);
            return;
        }

        $this->saveRecipes(array_values($filteredRecipes));

        http_response_code(200);
        echo json_encode(['message' => 'Recette supprimée avec succès']);
    }

    // Modifier une recette par ID
    public function updateRecipe(int $id): void
    {
        header('Content-Type: application/json');

        // Vérifier le type de contenu
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Content-Type header']);
            return;
        }

        // Récupérer les données JSON
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['name'], $input['nameFR'], $input['Without'], $input['ingredients'], $input['steps'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input. Missing required fields.']);
            return;
        }

        $recipes = $this->getAllRecipes();
        $recipeFound = false;

        foreach ($recipes as &$recipe) {
            if ($recipe['id'] === $id) {
                $recipe['name'] = $input['name'];
                $recipe['nameFR'] = $input['nameFR'];
                $recipe['Without'] = $input['Without'];
                $recipe['ingredients'] = $input['ingredients'];
                $recipe['steps'] = $input['steps'];
                $recipe['timers'] = $input['timers'] ?? [];
                $recipe['imageURL'] = $input['imageURL'] ?? null;
                $recipe['originalURL'] = $input['originalURL'] ?? null;
                $recipeFound = true;
                break;
            }
        }

        if (!$recipeFound) {
            http_response_code(404);
            echo json_encode(['error' => 'Recette non trouvée']);
            return;
        }

        $this->saveRecipes($recipes);

        http_response_code(200);
        echo json_encode(['message' => 'Recette modifiée avec succès']);
    }

    // Charger toutes les recettes depuis le fichier JSON
    public function getAllRecipes(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $data = json_decode(file_get_contents($this->filePath), true);
        return is_array($data) ? $data : [];
    }

    // Sauvegarder les recettes dans le fichier JSON
    private function saveRecipes(array $recipes): void
    {
        file_put_contents($this->filePath, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

}