<?php
class RoleController {
    
    private $filePath = 'roles.json';

    /**
     * Vérifie si l'utilisateur est authentifié et s'il est administrateur.
     */
    private function checkAdminAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["error" => "Action réservée aux administrateurs"]);
            exit;
        }
    }

    /**
     * Sauvegarde les données dans un fichier JSON.
     */
    private function save(string $filePath, array $data): void {
        file_put_contents(
            $filePath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Récupère tous les rôles depuis le fichier JSON.
     */
    public function getRoles() {
        if (!file_exists($this->filePath)) {
            echo json_encode(["error" => "File not found"]);
            return;
        }

        $roles = json_decode(file_get_contents($this->filePath), true);

        if ($roles === null) {
            echo json_encode(["error" => "Invalid JSON format"]);
            return;
        }

        echo json_encode($roles);
    }

    /**
     * Récupère toutes les demandes de rôle en attente.
     */
    public function getPendingRequests() {
        $this->checkAdminAuth(); // Vérifie que l'utilisateur est un administrateur

        if (!file_exists($this->filePath)) {
            echo json_encode(["error" => "File not found"]);
            return;
        }

        $roles = json_decode(file_get_contents($this->filePath), true);

        if ($roles === null || !isset($roles['requests'])) {
            echo json_encode(["error" => "Invalid JSON format"]);
            return;
        }

        // Filtrer les demandes avec le statut "pending"
        $pendingRequests = array_filter($roles['requests'], function ($request) {
            return $request['status'] === 'pending';
        });

        echo json_encode(array_values($pendingRequests)); // Réindexer le tableau
    }

    /**
     * Ajoute une demande de rôle pour un utilisateur.
     */
    public function requestRole($userId, $requestedRole) {
        if (!file_exists($this->filePath)) {
            echo json_encode(["error" => "File not found"]);
            return;
        }

        $roles = json_decode(file_get_contents($this->filePath), true);

        if (!in_array($requestedRole, ['chef', 'cuisinier', 'traducteur'])) {
            echo json_encode(["error" => "Invalid role requested"]);
            return;
        }

        // Vérifie si une demande existe déjà pour cet utilisateur
        foreach ($roles['requests'] as $request) {
            if ($request['userId'] === $userId) {
                echo json_encode(["error" => "Role request already exists"]);
                return;
            }
        }

        $newRequest = [
            'id' => count($roles['requests']) + 1,
            'userId' => $userId,
            'requestedRole' => $requestedRole,
            'status' => 'pending',
            'createdAt' => date('Y-m-d H:i:s')
        ];

        $roles['requests'][] = $newRequest;

        // Sauvegarde les données mises à jour
        $this->save($this->filePath, $roles);

        echo json_encode(["message" => "Role request submitted successfully"]);
    }

    /**
     * Accepte ou rejette une demande de rôle (admin uniquement).
     */
    public function handleRoleRequest($requestId, $action) {
        $this->checkAdminAuth(); // Vérifie que l'utilisateur est un administrateur

        if (!file_exists($this->filePath)) {
            echo json_encode(["error" => "File not found"]);
            return;
        }

        $roles = json_decode(file_get_contents($this->filePath), true);

        foreach ($roles['requests'] as &$request) {
            if ($request['id'] === $requestId) {
                if ($request['status'] !== 'pending') {
                    echo json_encode(["error" => "Request already processed"]);
                    return;
                }

                if (!in_array($action, ['accept', 'reject'])) {
                    echo json_encode(["error" => "Invalid action"]);
                    return;
                }

                $request['status'] = $action === 'accept' ? 'accepted' : 'rejected';
                $request['processedAt'] = date('Y-m-d H:i:s');

                if ($action === 'accept') {
                    // Ajoute le rôle à l'utilisateur
                    $roles['users'][$request['userId']] = $request['requestedRole'];
                }

                // Sauvegarde les données mises à jour
                $this->save($this->filePath, $roles);

                echo json_encode(["message" => "Request processed successfully"]);
                return;
            }
        }

        echo json_encode(["error" => "Request not found"]);
    }

}