<?php
class CommentController
{
    private string $commentsFile;
    private string $recipesFile;

    public function __construct(string $commentsFile, string $recipesFile)
    {
        $this->commentsFile = $commentsFile;
        $this->recipesFile = $recipesFile;
    }

    // Ajouter un commentaire
    public function addComment(): void
    {
        header('Content-Type: application/json');

        // Vérifier le type de contenu
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Content-Type header']);
            return;
        }

        // Vérifier si l'utilisateur est connecté
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Veuillez vous authentifier pour commenter.']);
            return;
        }

        // Récupérer les données JSON
        $input = json_decode(file_get_contents('php://input'), true);

        // Vérifier les champs obligatoires
        $recipeId = $input['recipe_id'] ?? null;
        $content = trim($input['content'] ?? '');

        if (!$recipeId || empty($content)) {
            http_response_code(400);
            echo json_encode(['error' => 'Veuillez renseigner tous les champs.']);
            return;
        }

        // Charger les recettes
        $recipes = $this->getAllRecipes();
        
        // Vérification de l'existence de la recette avec la clé "id"
        $recipeExists = array_filter($recipes, fn($recipe) => (int) $recipe['id'] === (int) $recipeId);

        if (empty($recipeExists)) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Recette introuvable',
                'debug' => [
                    'input_recipe_id' => $recipeId,
                    'all_recipes' => $recipes
                ]
            ]);
            return;
        }

        // Charger les commentaires existants
        $comments = $this->getAllComments();

        // Récupérer l'utilisateur connecté
        $user = $_SESSION['user'];

        // Créer un nouveau commentaire
        $newComment = [
            'id' => count($comments) + 1,
            'recipe_id' => (int) $recipeId,
            'author' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Ajouter le commentaire et sauvegarder
        $comments[] = $newComment;
        $this->saveComments($comments);

        http_response_code(201);
        echo json_encode(['message' => 'Commentaire ajouté avec succès', 'comment' => $newComment]);
    }

    // Charger tous les commentaires depuis le fichier JSON
    private function getAllComments(): array
    {
        if (!file_exists($this->commentsFile)) {
            return [];
        }

        $data = json_decode(file_get_contents($this->commentsFile), true);
        return is_array($data) ? $data : [];
    }

    // Sauvegarder les commentaires dans le fichier JSON
    private function saveComments(array $comments): void
    {
        file_put_contents($this->commentsFile, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // Charger toutes les recettes depuis le fichier JSON
    public function getAllRecipes(): array
    {
        if (!file_exists($this->recipesFile)) {
            error_log("Fichier des recettes introuvable : " . $this->recipesFile);
            return [];
        }
    
        $data = file_get_contents($this->recipesFile);
    
        if ($data === false) {
            error_log("Impossible de lire le fichier des recettes.");
            return [];
        }
    
        $recipes = json_decode($data, true);
    
        if (!is_array($recipes)) {
            error_log("Erreur de décodage JSON dans recipes.json : " . json_last_error_msg());
            return [];
        }
    
        return $recipes;
    }
    
}
