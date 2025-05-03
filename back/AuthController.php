<?php

/**
 * Classe AuthController pour gérer l'authentification des utilisateurs.
 * Fournit des méthodes pour l'inscription, la connexion, la déconnexion,
 * la validation de l'authentification et la mise à jour des rôles.
 */
class AuthController
{
    /**
     * Chemin vers le fichier JSON contenant les données des utilisateurs.
     * @var string
     */
    private string $filePath;

    /**
     * Constructeur de la classe.
     * Initialise le chemin du fichier JSON des utilisateurs.
     *
     * @param string $filePath Chemin vers le fichier JSON.
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Sauvegarde les données dans un fichier JSON avec un formatage lisible.
     *
     * @param array $data Données à sauvegarder.
     * @return void
     */
    private function save(array $data): void
    {
        file_put_contents(
            $this->filePath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Gère l'inscription d'un nouvel utilisateur.
     * Valide les données, vérifie l'unicité de l'email, hache le mot de passe
     * et enregistre l'utilisateur dans le fichier JSON.
     *
     * @return void
     */
    public function handleRegister(): void
    {
        header('Content-Type: application/json');

        // Vérifie que le type de contenu est correct
        if ($_SERVER['CONTENT_TYPE'] !== 'application/x-www-form-urlencoded') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Content-Type header']);
            return;
        }

        // Récupère et valide les données du formulaire
        $idUtilisateur = uniqid();
        $username = $_POST['name'] ?? '';
        $userprename = $_POST['prenom'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = "utilisateur";

        // Validation de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['message' => 'email Invalid']);
            return;
        }

        // Validation de la longueur du mot de passe
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['message' => 'Le mot de passe doit comporter au moins 8 caractères.']);
            return;
        }

        // Validation de la longueur du nom
        if (strlen($username) < 2) {
            http_response_code(400);
            echo json_encode(['message' => 'Le nom doit comporter au moins 2 caractères.']);
            return;
        }

        // Validation de la longueur du prénom
        if (strlen($userprename) < 2) {
            http_response_code(400);
            echo json_encode(['message' => 'Le prénom doit comporter au moins 2 caractères.']);
            return;
        }

        // Récupère tous les utilisateurs
        $users = $this->getAllUsers();

        // Vérifie si l'email est déjà utilisé
        foreach ($users as $user) {
            if ($user['mail'] === $email) {
                http_response_code(400);
                echo json_encode([
                    'message' => 'Email déjà enregistré',
                    'redirect' => 'connexion.html'
                ]);
                return;
            }
        }

        // Hache le mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $user = [
            "id" => $idUtilisateur,
            "name" => $username,
            "prenom" => $userprename,
            "mail" => $email,
            "password" => $hashedPassword,
            "role" => $role,
        ];

        // Ajoute l'utilisateur à la liste
        $users[] = $user;

        // Sauvegarde les utilisateurs
        $this->save($users);

        // Réponse de succès
        http_response_code(201);
        echo json_encode([
            'message' => 'Utilisateur enregistré avec succès',
            'redirect' => 'http://localhost:3000/connexion.html'
        ]);
    }

    /**
     * Gère la connexion d'un utilisateur.
     * Valide les identifiants, vérifie le mot de passe et initialise la session.
     *
     * @return void
     */
    public function handleLogin(): void
    {
        header("Content-Type: application/json");

        // Vérifie que le type de contenu est correct
        if (!isset($_SERVER['CONTENT_TYPE']) || stripos($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Content-Type header']);
            return;
        }

        // Récupère les données du formulaire
        $email = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;

        // Vérifie la présence des champs requis
        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['message' => 'Email et mot de passe requis']);
            return;
        }

        // Récupère tous les utilisateurs
        $users = $this->getAllUsers();
        $userFound = null;

        // Recherche l'utilisateur par email
        foreach ($users as $user) {
            if ($user['mail'] === $email) {
                $userFound = $user;
                break;
            }
        }

        // Vérifie si l'utilisateur existe
        if (!$userFound) {
            http_response_code(404);
            echo json_encode(['message' => 'Utilisateur non trouvé']);
            return;
        }

        // Vérifie le mot de passe
        if (!password_verify($password, $userFound['password'])) {
            http_response_code(401);
            echo json_encode(['message' => 'Mot de passe incorrect']);
            return;
        }

        // Démarre la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Stocke les données de l'utilisateur dans la session
        $_SESSION['user'] = [
            'id' => $userFound['id'],
            'name' => $userFound['name'],
            'prenom' => $userFound['prenom'],
            'email' => $userFound['mail'],
            'role' => $userFound['role']
        ];

        // Réponse de succès
        http_response_code(200);
        echo json_encode([
            'message' => 'Connexion réussie',
            'user' => [
                'id' => $userFound['id'],
                'name' => $userFound['name'],
                'prenom' => $userFound['prenom'],
                'email' => $userFound['mail'],
                'role' => $userFound['role']
            ],
            'redirect' => 'index.html'
        ]);
    }

    /**
     * Gère la déconnexion d'un utilisateur.
     * Détruit la session et supprime le cookie de session.
     *
     * @return void
     */
    public function handleLogout(): void
    {
        // Configure les en-têtes pour CORS et JSON
        header('Access-Control-Allow-Origin: http://localhost:3000');
        header('Access-Control-Allow-Credentials: true');
        header('Content-Type: application/json');

        // Démarre la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vide la session
        $_SESSION = [];

        // Supprime le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Détruit la session et renvoie la réponse
        if (session_destroy()) {
            http_response_code(200);
            echo json_encode(['message' => 'Déconnexion réussie']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Erreur lors de la destruction de la session']);
        }
    }

    /**
     * Valide l'authentification de l'utilisateur.
     * Retourne l'email de l'utilisateur connecté ou null s'il n'est pas connecté.
     *
     * @return string|null Email de l'utilisateur ou null.
     */
    public function validateAuth(): ?string
    {
        // Démarre la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION['user']['email'] ?? null;
    }

    /**
     * Récupère tous les utilisateurs depuis le fichier JSON.
     * Retourne un tableau vide si le fichier n'existe pas ou est invalide.
     *
     * @return array Liste des utilisateurs.
     */
    private function getAllUsers(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $data = json_decode(file_get_contents($this->filePath), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Met à jour le rôle d'un utilisateur dans le fichier JSON et dans la session si nécessaire.
     *
     * @param string $userId ID de l'utilisateur.
     * @param string $newRole Nouveau rôle à assigner.
     * @return bool True si la mise à jour réussit, false sinon.
     */
    public function updateUserRole($userId, $newRole): bool
    {
        // Vérifie l'existence du fichier
        if (!file_exists($this->filePath)) {
            error_log("updateUserRole: Users file not found");
            return false;
        }

        // Récupère tous les utilisateurs
        $users = $this->getAllUsers();
        $userFound = false;

        // Recherche et met à jour l'utilisateur
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                $user['role'] = $newRole;
                $userFound = true;
                break;
            }
        }

        // Vérifie si l'utilisateur a été trouvé
        if (!$userFound) {
            error_log("updateUserRole: User not found - userId: $userId");
            return false;
        }

        // Sauvegarde les modifications
        try {
            $this->save($users);
        } catch (Exception $e) {
            error_log("updateUserRole: Failed to save user role: " . $e->getMessage());
            return false;
        }

        // Met à jour la session si l'utilisateur est connecté
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user']) && $_SESSION['user']['id'] === $userId) {
            $_SESSION['user']['role'] = $newRole;
        }

        return true;
    }
}
?>