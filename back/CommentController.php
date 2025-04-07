<?php
class CommentController
{
    private string $commentsFile;
    private string $recipesFile; 

    // Constructeur pour initialiser les chemins des fichiers

    public function __construct(string $commentsFile, string $recipesFile)
    {
        $this->commentsFile = $commentsFile;
        $this->recipesFile = $recipesFile;
    }

    // Ajouter un commentaire
    public function addComment(): void
    {
        header('Content-Type: application/json'); // Définir le type de contenu de la réponse en JSON

        // Vérifier que le type de contenu de la requête est JSON
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Content-Type header']);
            return;
        }

        // Démarrer une session si elle n'est pas déjà active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            http_response_code(401); 
            echo json_encode(['error' => 'Veuillez vous authentifier pour commenter.']);
            return;
        }

        // Récupérer les données JSON envoyées dans la requête
        $input = json_decode(file_get_contents('php://input'), true);
        $recipeId = $input['recipe_id'] ?? null; 
        $content = trim($input['content'] ?? '');

        // Vérifier que tous les champs requis sont remplis
        if (!$recipeId || empty($content)) {
            http_response_code(400); 
            echo json_encode(['error' => 'Veuillez renseigner tous les champs.']);
            return;
        }

        // Charger toutes les recettes
        $recipes = $this->getAllRecipes();
        // Vérifier si la recette existe
        $recipeExists = array_filter($recipes, fn($recipe) => (int) $recipe['id'] === (int) $recipeId);

        if (empty($recipeExists)) {
            http_response_code(404); 
            echo json_encode(['error' => 'Recette introuvable']);
            return;
        }

        // Charger tous les commentaires existants
        $comments = $this->getAllComments();
        $user = $_SESSION['user']; // Récupérer les informations de l'utilisateur connecté

        // Créer un nouveau commentaire
        $newComment = [
            'id' => count($comments) + 1, // Générer un nouvel ID pour le commentaire
            'recipe_id' => (int) $recipeId,
            'author' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s') // Ajouter une date de création
        ];

        // Ajouter le nouveau commentaire à la liste
        $comments[] = $newComment;
        // Sauvegarder les commentaires dans le fichier JSON
        $this->saveComments($comments);

        http_response_code(201); 
        echo json_encode(['message' => 'Commentaire ajouté avec succès', 'comment' => $newComment]);
    }

    // Supprimer un commentaire
    public function deleteComment(): void
    {
        header('Content-Type: application/json'); 

        // Démarrer une session si elle n'est pas déjà active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Veuillez vous authentifier pour supprimer un commentaire.']);
            return;
        }

        // Récupérer les données JSON envoyées dans la requête
        $input = json_decode(file_get_contents('php://input'), true);
        $commentId = $input['comment_id'] ?? null; 

        // Vérifier que l'ID du commentaire est fourni
        if (!$commentId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID du commentaire requis.']);
            return;
        }

        // Charger tous les commentaires existants
        $comments = $this->getAllComments();
        $commentIndex = null;

        // Rechercher le commentaire à supprimer
        foreach ($comments as $index => $comment) {
            if ((int) $comment['id'] === (int) $commentId) {
                // Vérifier si l'utilisateur connecté est l'auteur du commentaire
                if ($comment['author']['id'] !== $_SESSION['user']['id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Vous ne pouvez supprimer que vos propres commentaires.']);
                    return;
                }
                $commentIndex = $index;
                break;
            }
        }

        // Si le commentaire n'est pas trouvé
        if ($commentIndex === null) {
            http_response_code(404); 
            echo json_encode(['error' => 'Commentaire introuvable.']);
            return;
        }

        // Supprimer le commentaire de la liste
        array_splice($comments, $commentIndex, 1);
        // Sauvegarder les commentaires mis à jour
        $this->saveComments($comments);

        http_response_code(200); 
        echo json_encode(['message' => 'Commentaire supprimé avec succès.']);
    }

    // Modifier un commentaire
    public function updateComment(): void
    {
        header('Content-Type: application/json');

        // Démarrer une session si elle n'est pas déjà active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user'])) {
            http_response_code(401); 
            echo json_encode(['error' => 'Veuillez vous authentifier pour modifier un commentaire.']);
            return;
        }

        // Récupérer les données JSON envoyées dans la requête
        $input = json_decode(file_get_contents('php://input'), true);
        $commentId = $input['comment_id'] ?? null; 
        $newContent = trim($input['content'] ?? ''); 

        // Vérifier que l'ID du commentaire et le contenu sont fournis
        if (!$commentId || empty($newContent)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID du commentaire et contenu requis.']);
            return;
        }

        // Charger tous les commentaires existants
        $comments = $this->getAllComments();

        // Rechercher le commentaire à modifier
        foreach ($comments as &$comment) {
            if ((int) $comment['id'] === (int) $commentId) {
                // Vérifier si l'utilisateur connecté est l'auteur du commentaire
                if ($comment['author']['id'] !== $_SESSION['user']['id']) {
                    http_response_code(403); 
                    echo json_encode(['error' => 'Vous ne pouvez modifier que vos propres commentaires.']);
                    return;
                }

                // Mettre à jour le contenu du commentaire
                $comment['content'] = $newContent;
                $comment['updated_at'] = date('Y-m-d H:i:s'); 
                $this->saveComments($comments);

                http_response_code(200); 
                echo json_encode(['message' => 'Commentaire modifié avec succès.', 'comment' => $comment]);
                return;
            }
        }

        // Si le commentaire n'est pas trouvé
        http_response_code(404);
        echo json_encode(['error' => 'Commentaire introuvable.']);
    }

    // Récupérer tous les commentaires
    public function getAllComments(): array
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

    // Charger toutes les recettes
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