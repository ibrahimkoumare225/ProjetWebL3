<?php

/**
 * Contrôleur chargé de la gestion des commentaires (CRUD).
 * Gère les opérations de lecture, création, suppression et mise à jour des commentaires
 * à partir d'un fichier JSON.
 */
class CommentController
{
    /**
     * Chemin vers le fichier JSON contenant les données des commentaires.
     * @var string
     */
    private string $filePath;

    /**
     * Constructeur de la classe.
     * Initialise le chemin du fichier JSON des commentaires.
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
     * Vérifie si l'utilisateur courant est propriétaire du commentaire ou administrateur.
     * Termine l'exécution avec une erreur 403 si non autorisé.
     *
     * @param array $comment Données du commentaire.
     * @return void
     */
    private function checkCommentOwnership(array $comment): void
    {
        $user = $this->checkAuth();
        // Extrait l'ID de l'auteur du commentaire
        $authorId = $comment['Author']['id'] ?? null;
        $userId = $user['id'] ?? null;

        // Vérifie les autorisations (admin ou auteur)
        if ($user['role'] !== 'admin' && (int)$userId !== (int)$authorId) {
            http_response_code(403);
            echo json_encode(['error' => 'Action réservée à l\'auteur ou administrateur']);
            exit;
        }
    }

    /**
     * Vérifie si l'utilisateur courant a le rôle 'admin', 'chef' ou 'cuisinier' pour ajouter un commentaire.
     * Termine l'exécution avec une erreur 403 si non autorisé.
     *
     * @return array Données de l'utilisateur connecté.
     */
    private function checkIfCanAddComment(): array
    {
        $user = $this->checkAuth();

        // Vérifie les rôles autorisés
        if (!in_array($user['role'], ['admin', 'chef', 'cuisinier'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Seuls les administrateurs, les cuisiniers ou chefs peuvent commenter une recette']);
            exit;
        }

        return $user;
    }

    /**
     * Récupère et retourne tous les commentaires sous forme de JSON.
     * Filtre par recipeId si spécifié dans les paramètres GET.
     *
     * @return void
     */
    public function getComments(): void
    {
        header('Content-Type: application/json');
        try {
            $comments = $this->getAllComments();

            // Vérifie si un recipeId est fourni dans les paramètres GET
            $recipeId = isset($_GET['recipeId']) ? (int)$_GET['recipeId'] : null;

            // Filtre les commentaires par recipeId si spécifié
            if ($recipeId !== null) {
                $comments = array_filter($comments, function ($comment) use ($recipeId) {
                    return isset($comment['recipeId']) && $comment['recipeId'] === $recipeId;
                });
                $comments = array_values($comments); // Réindexe le tableau
            }

            // Retourne les commentaires en JSON
            echo json_encode($comments);
        } catch (Exception $e) {
            error_log("Erreur dans getComments: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Ajoute un nouveau commentaire après validation des données et des autorisations.
     * Enregistre le commentaire dans le fichier JSON.
     *
     * @return void
     */
    public function addComment(): void
    {
        header('Content-Type: application/json');

        // Vérifie l'authentification et les autorisations
        $user = $this->checkIfCanAddComment();

        // Vérifie le type de contenu
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            http_response_code(400);
            echo json_encode(['error' => 'Content-Type doit être application/json']);
            return;
        }

        // Récupère et valide les données JSON
        $input = json_decode(file_get_contents('php://input'), true);

        // Vérifie les champs requis
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

        // Crée le nouveau commentaire
        $newComment = [
            'id' => count($comments) + 1, // Génère un nouvel ID
            'message' => $input['message'],
            'recipeId' => (int)$input['recipeId'],
            'Author' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'createdAt' => date('Y-m-d H:i:s'),
        ];

        // Ajoute le commentaire à la liste
        $comments[] = $newComment;

        // Sauvegarde les commentaires
        $this->saveComments($comments);

        // Réponse de succès
        http_response_code(201);
        echo json_encode($newComment);
    }

    /**
     * Supprime un commentaire en fonction de son ID.
     * Vérifie les autorisations (auteur ou admin) avant suppression.
     *
     * @param int $id ID du commentaire à supprimer.
     * @return void
     */
    public function deleteComment(int $id): void
    {
        header('Content-Type: application/json');
        $comments = $this->getAllComments();

        // Recherche et supprime le commentaire
        foreach ($comments as $key => $comment) {
            if ($comment['id'] === $id) {
                $this->checkCommentOwnership($comment);
                array_splice($comments, $key, 1);
                $this->saveComments($comments);
                echo json_encode(['message' => 'Commentaire supprimé avec succès']);
                return;
            }
        }

        // Commentaire non trouvé
        http_response_code(404);
        echo json_encode(['error' => 'Commentaire non trouvé']);
    }

    /**
     * Met à jour un commentaire en fonction de son ID.
     * Vérifie les autorisations (auteur ou admin) et met à jour les champs modifiables.
     *
     * @param int $id ID du commentaire à mettre à jour.
     * @return void
     */
    public function updateComment(int $id): void
    {
        header('Content-Type: application/json');
        // Récupère et valide les données JSON
        $input = json_decode(file_get_contents('php://input'), true);
        $comments = $this->getAllComments();

        // Recherche et met à jour le commentaire
        foreach ($comments as &$comment) {
            if ($comment['id'] === $id) {
                $this->checkCommentOwnership($comment);

                // Champs modifiables
                $updatableFields = ['message'];
                $hasUpdates = false;

                // Met à jour les champs fournis
                foreach ($updatableFields as $field) {
                    if (isset($input[$field])) {
                        $comment[$field] = $input[$field];
                        $hasUpdates = true;
                    }
                }

                // Retourne le commentaire inchangé si aucun champ n'est modifié
                if (!$hasUpdates) {
                    echo json_encode($comment);
                    return;
                }

                // Met à jour la date de modification
                $comment['updatedAt'] = date('Y-m-d H:i:s');
                $this->saveComments($comments);
                echo json_encode($comment);
                return;
            }
        }

        // Commentaire non trouvé
        http_response_code(404);
        echo json_encode(['error' => 'Commentaire non trouvé']);
    }

    /**
     * Récupère tous les commentaires depuis le fichier JSON.
     * Retourne un tableau vide si le fichier n'existe pas ou est invalide.
     *
     * @return array Liste des commentaires.
     */
    private function getAllComments(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $data = file_get_contents($this->filePath);
        return json_decode($data, true) ?: [];
    }

    /**
     * Sauvegarde la liste des commentaires dans le fichier JSON.
     * Utilise un formatage lisible avec JSON_PRETTY_PRINT.
     *
     * @param array $comments Liste des commentaires à sauvegarder.
     * @return void
     */
    private function saveComments(array $comments): void
    {
        file_put_contents(
            $this->filePath,
            json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
?>