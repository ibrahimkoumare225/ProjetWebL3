<?php

/**
 * Contrôleur chargé de la gestion des rôles et des demandes de rôles.
 * Permet de récupérer les rôles, gérer les demandes de rôles (soumission, acceptation, rejet)
 * et interagir avec le fichier JSON pour la persistance des données.
 */
class RoleController
{
    /**
     * Chemin vers le fichier JSON contenant les données des rôles et des demandes.
     * @var string
     */
    private $filePath;

    /**
     * Instance du contrôleur d'authentification pour gérer les mises à jour des rôles utilisateurs.
     * @var AuthController
     */
    private $authController;

    /**
     * Constructeur de la classe.
     * Initialise le chemin du fichier JSON et le contrôleur d'authentification.
     *
     * @param string $filePath Chemin vers le fichier JSON.
     * @param AuthController $authController Instance du contrôleur d'authentification.
     */
    public function __construct(string $filePath, AuthController $authController)
    {
        $this->filePath = $filePath;
        $this->authController = $authController;
    }

    /**
     * Vérifie si l'utilisateur est authentifié et possède le rôle d'administrateur.
     * Termine l'exécution avec une erreur 403 si non autorisé.
     *
     * @return void
     */
    private function checkAdminAuth(): void
    {
        // Démarre la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifie la présence et le rôle de l'utilisateur
        if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            error_log("checkAdminAuth: Échec - Utilisateur non admin ou non connecté");
            http_response_code(403);
            echo json_encode(["error" => "Action réservée aux administrateurs"]);
            exit;
        }
    }

    /**
     * Sauvegarde les données dans le fichier JSON avec un formatage lisible.
     *
     * @param string $filePath Chemin du fichier JSON.
     * @param array $data Données à sauvegarder.
     * @return void
     * @throws Exception Si l'écriture échoue.
     */
    private function save(string $filePath, array $data): void
    {
        file_put_contents(
            $filePath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Récupère et retourne les données des rôles et des demandes de rôles.
     * Filtre les demandes pour les utilisateurs non-administrateurs.
     *
     * @return void
     */
    public function getRoles(): void
    {
        header('Content-Type: application/json');

        // Démarre la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifie la connexion de l'utilisateur
        if (!isset($_SESSION['user'])) {
            error_log("getRoles: Échec - Utilisateur non connecté");
            http_response_code(401);
            echo json_encode(["error" => "Utilisateur non connecté"]);
            return;
        }

        $userId = $_SESSION['user']['id'];
        $userRole = $_SESSION['user']['role'];

        // Initialise le fichier JSON s'il n'existe pas
        if (!file_exists($this->filePath)) {
            $this->save($this->filePath, ["requests" => [], "users" => []]);
        }

        // Charge les données du fichier JSON
        $roles = json_decode(file_get_contents($this->filePath), true);
        if ($roles === null) {
            error_log("getRoles: Erreur - Format JSON invalide dans le fichier des rôles");
            http_response_code(500);
            echo json_encode(["error" => "Invalid JSON format in roles file"]);
            return;
        }

        // Filtre les demandes pour les utilisateurs non-administrateurs
        if ($userRole !== 'admin') {
            $roles['requests'] = array_filter($roles['requests'] ?? [], function ($request) use ($userId) {
                return $request['userId'] === $userId;
            });
            $roles['requests'] = array_values($roles['requests']);
        }

        // Retourne les données en JSON
        echo json_encode($roles);
    }

    /**
     * Récupère et retourne les demandes de rôles en attente (pour les administrateurs).
     *
     * @return void
     */
    public function getPendingRequests(): void
    {
        header('Content-Type: application/json');

        // Vérifie les autorisations administratives
        $this->checkAdminAuth();

        // Initialise le fichier JSON s'il n'existe pas
        if (!file_exists($this->filePath)) {
            $this->save($this->filePath, ["requests" => [], "users" => []]);
        }

        // Charge les données du fichier JSON
        $roles = json_decode(file_get_contents($this->filePath), true);
        if ($roles === null || !isset($roles['requests'])) {
            error_log("getPendingRequests: Erreur - Format JSON invalide dans le fichier des rôles");
            http_response_code(500);
            echo json_encode(["error" => "Invalid JSON format in roles file"]);
            return;
        }

        // Filtre les demandes en attente
        $pendingRequests = array_filter($roles['requests'], function ($request) {
            return $request['status'] === 'pending';
        });

        // Retourne les demandes en attente en JSON
        echo json_encode(array_values($pendingRequests));
    }

    /**
     * Soumet une demande de rôle pour un utilisateur.
     * Vérifie les autorisations et valide la demande avant enregistrement.
     *
     * @param mixed $userId ID de l'utilisateur faisant la demande.
     * @param string $requestedRole Rôle demandé (chef, cuisinier, traducteur).
     * @return void
     */
    public function requestRole($userId, $requestedRole): void
    {
        header('Content-Type: application/json');

        // Démarre la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifie la connexion de l'utilisateur
        if (!isset($_SESSION['user'])) {
            error_log("requestRole: Échec - Utilisateur non connecté");
            http_response_code(401);
            echo json_encode(["error" => "Utilisateur non connecté"]);
            return;
        }

        // Forcer la comparaison en chaînes
        $userIdStr = (string)$userId;
        $sessionUserIdStr = (string)$_SESSION['user']['id'];

        // Journalisation pour débogage
        error_log("requestRole - userId from request: " . $userIdStr . " (type: " . gettype($userIdStr) . ")");
        error_log("requestRole - session user id: " . $sessionUserIdStr . " (type: " . gettype($sessionUserIdStr) . ")");

        // Vérifie que l'utilisateur fait une demande pour lui-même
        if ($userIdStr !== $sessionUserIdStr) {
            error_log("requestRole: Échec - Tentative de demande pour un autre utilisateur");
            http_response_code(403);
            echo json_encode(["error" => "Vous ne pouvez pas faire une demande pour un autre utilisateur"]);
            return;
        }

        // Initialise le fichier JSON s'il n'existe pas
        if (!file_exists($this->filePath)) {
            $this->save($this->filePath, ["requests" => [], "users" => []]);
        }

        // Charge les données du fichier JSON
        $roles = json_decode(file_get_contents($this->filePath), true);
        if ($roles === null) {
            error_log("requestRole: Erreur - Format JSON invalide dans le fichier des rôles");
            http_response_code(500);
            echo json_encode(["error" => "Invalid JSON format in roles file"]);
            return;
        }

        // Valide le rôle demandé
        if (!in_array($requestedRole, ['chef', 'cuisinier', 'traducteur'])) {
            error_log("requestRole: Échec - Rôle demandé invalide: $requestedRole");
            http_response_code(400);
            echo json_encode(["error" => "Invalid role requested"]);
            return;
        }

        // Vérifie l'absence de demandes en attente
        if (!isset($roles['requests'])) {
            $roles['requests'] = [];
        }
        foreach ($roles['requests'] as $request) {
            if ($request['userId'] === $userId && $request['status'] === 'pending') {
                error_log("requestRole: Échec - Demande de rôle déjà en attente pour userId=$userId");
                http_response_code(400);
                echo json_encode(["error" => "Vous avez déjà une demande de rôle en attente"]);
                return;
            }
        }

        // Crée une nouvelle demande de rôle
        $newRequest = [
            'id' => count($roles['requests']) + 1,
            'userId' => $userId,
            'userName' => $_SESSION['user']['name'],
            'userPrenom' => $_SESSION['user']['prenom'],
            'userEmail' => $_SESSION['user']['email'],
            'requestedRole' => $requestedRole,
            'status' => 'pending',
            'createdAt' => date('Y-m-d H:i:s')
        ];

        // Ajoute la demande à la liste
        $roles['requests'][] = $newRequest;

        // Sauvegarde les données
        try {
            $this->save($this->filePath, $roles);
        } catch (Exception $e) {
            error_log("requestRole: Erreur - Échec de la sauvegarde: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["error" => "Failed to save role request: " . $e->getMessage()]);
            return;
        }

        // Réponse de succès
        http_response_code(200);
        echo json_encode(["message" => "Role request submitted successfully"]);
    }

    /**
     * Traite une demande de rôle (accepte ou rejette).
     * Met à jour le rôle de l'utilisateur si accepté et enregistre les modifications.
     *
     * @param int $requestId ID de la demande de rôle.
     * @param string $action Action à effectuer (accept ou reject).
     * @return void
     */
    public function handleRoleRequest($requestId, $action): void
    {
        header('Content-Type: application/json');

        // Vérifie les autorisations administratives
        $this->checkAdminAuth();

        // Initialise le fichier JSON s'il n'existe pas
        if (!file_exists($this->filePath)) {
            $this->save($this->filePath, ["requests" => [], "users" => []]);
        }

        // Charge les données du fichier JSON
        $roles = json_decode(file_get_contents($this->filePath), true);
        if ($roles === null) {
            error_log("handleRoleRequest: Erreur - Format JSON invalide dans le fichier des rôles");
            http_response_code(500);
            echo json_encode(["error" => "Invalid JSON format in roles file"]);
            return;
        }

        if (!isset($roles['requests'])) {
            $roles['requests'] = [];
        }

        // Recherche et traite la demande
        foreach ($roles['requests'] as &$request) {
            if ($request['id'] === $requestId) {
                // Vérifie que la demande est en attente
                if ($request['status'] !== 'pending') {
                    error_log("handleRoleRequest: Échec - Demande déjà traitée, requestId=$requestId");
                    http_response_code(400);
                    echo json_encode(["error" => "Request already processed"]);
                    return;
                }

                // Valide l'action
                if (!in_array($action, ['accept', 'reject'])) {
                    error_log("handleRoleRequest: Échec - Action invalide: $action");
                    http_response_code(400);
                    echo json_encode(["error" => "Invalid action"]);
                    return;
                }

                // Met à jour le statut de la demande
                $request['status'] = $action === 'accept' ? 'accepted' : 'rejected';
                $request['processedAt'] = date('Y-m-d H:i:s');

                // Si accepté, met à jour le rôle de l'utilisateur
                if ($action === 'accept') {
                    $userId = $request['userId'];
                    $newRole = $request['requestedRole'];
                    $success = $this->authController->updateUserRole($userId, $newRole);
                    if (!$success) {
                        error_log("handleRoleRequest: Échec - Impossible de mettre à jour le rôle pour userId=$userId");
                        http_response_code(500);
                        echo json_encode(["error" => "Failed to update user role"]);
                        return;
                    }

                    // Met à jour la liste des utilisateurs avec le nouveau rôle
                    if (!isset($roles['users'])) {
                        $roles['users'] = [];
                    }
                    $roles['users'][$userId] = $newRole;
                }

                // Sauvegarde les données
                try {
                    $this->save($this->filePath, $roles);
                } catch (Exception $e) {
                    error_log("handleRoleRequest: Erreur - Échec de la sauvegarde: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(["error" => "Failed to save role request: " . $e->getMessage()]);
                    return;
                }

                // Réponse de succès
                http_response_code(200);
                echo json_encode(["message" => "Request processed successfully"]);
                return;
            }
        }

        // Demande non trouvée
        error_log("handleRoleRequest: Échec - Demande non trouvée, requestId=$requestId");
        http_response_code(404);
        echo json_encode(["error" => "Request not found"]);
    }
}
?>