<?php

class CommentController
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
    private function checkcommentOwnership(array $comment): void
    {
        $user = $this->checkAuth();
        $authorId = $comment['Author']['id'] ?? null;
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
    private function checkIfCanAddComment(): array
    {
        $user = $this->checkAuth();
    
        if (!in_array($user['role'], ['admin', 'chef','cusinier'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Seuls les administrateurs, les cusiniers ou chefs peuvent ajouter une recette']);
            exit;
        }
    
        return $user;
    }

       /**
     * Retourne toutes les recettes sous forme de JSON.
     */
    public function getComments(): void
    {
        header('Content-Type: application/json');
        try {
            $comments = $this->getAllComments();
    
            // Vérifier si un recipeId est passé dans les paramètres de l'URL
            $recipeId = isset($_GET['recipeId']) ? (int)$_GET['recipeId'] : null;
    
            // Filtrer les commentaires par recipeId si fourni
            if ($recipeId !== null) {
                $comments = array_filter($comments, function ($comment) use ($recipeId) {
                    return isset($comment['recipeId']) && $comment['recipeId'] === $recipeId;
                });
                $comments = array_values($comments); // Réindexer le tableau
            }
    
            echo json_encode($comments);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

     /**
     * Ajoute une nouvelle recette en validant les champs requis.
     */
    public function addComment(): void
    {
        header('Content-Type: application/json');
    
        // Vérifie l'authentification et les autorisations
        $user = $this->checkIfCanAddComment();
    
        // Vérifie le type de contenu attendu
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            http_response_code(400);
            echo json_encode(['error' => 'Content-Type doit être application/json']);
            return;
        }
    
        // Récupère les données envoyées dans le corps de la requête
        $input = json_decode(file_get_contents('php://input'), true);
    
        // Liste des champs requis pour un commentaire
        $requiredFields = ['message', 'recipeId'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Le champ $field est requis"]);
                return;
            }
        }
    
        // Charge tous les commentaires existants
        $comments = $this->getAllComments();
    
        // Prépare le nouveau commentaire à ajouter
        $newComment = [
            'id' => count($comments) + 1,
            'message' => $input['message'],
            'recipeId' => (int)$input['recipeId'], // Ajout du recipeId
            'Author' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'createdAt' => date('Y-m-d H:i:s'),
        ];
    
        // Ajoute le nouveau commentaire à la liste
        $comments[] = $newComment;
    
        // Sauvegarde la liste des commentaires mise à jour
        $this->saveComments($comments);
    
        // Répond avec un statut 201 (créé) et le commentaire ajouté
        http_response_code(201);
        echo json_encode($newComment);
    }

     /**
     * Supprime une recette en fonction de son ID.
     * L'utilisateur doit être l'auteur ou admin.
     */
    public function deleteComment(int $id): void
{
    header('Content-Type: application/json');
    $comments = $this->getAllComments(); 
    
    foreach ($comments as $key => $comment) {
        if ($comment['id'] === $id) {
            $this->checkCommentOwnership($comment);

            array_splice($comments, $key, 1);
            $this->saveComments($comments);
            
            echo json_encode(['message' => 'Commentaire supprimé avec succès']);
            return;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Commentaire non trouvé']);
}

public function updateComment(int $id): void
{
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $comments = $this->getAllComments(); 

    foreach ($comments as &$comment) {
        if ($comment['id'] === $id) {
            $this->checkCommentOwnership($comment);

            $updatableFields = ['message'];
            $hasUpdates = false;

            foreach ($updatableFields as $field) {
                if (isset($input[$field])) {
                    $comment[$field] = $input[$field];
                    $hasUpdates = true;
                }
            }

            if (!$hasUpdates) {
                echo json_encode($comment); // Pas de modification
                return;
            }

            $comment['updatedAt'] = date('Y-m-d H:i:s');
            $this->saveComments($comments);
            
            echo json_encode($comment);
            return;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Commentaire non trouvé']);
}
    /**
     * Récupère toutes les recettes depuis le fichier JSON.
     */
    private function getAllcomments(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $data = file_get_contents($this->filePath);
        return json_decode($data, true) ?: [];
    }

    /**
     * Sauvegarde la liste complète des commentaires dans le fichier JSON.
     */
    private function saveComments(array $comments): void
    {
        file_put_contents(
            $this->filePath,
            json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}



