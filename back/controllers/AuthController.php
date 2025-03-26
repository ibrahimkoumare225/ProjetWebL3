<?php

class AuthController
{
    private $usersFile = 'data/users.json';

    public function __construct()
    {
        if (!file_exists($this->usersFile)) {
            file_put_contents($this->usersFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Inscription d'un utilisateur
     */
    public function register()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['nom'], $data['email'], $data['password'])) {
            echo json_encode(["error" => "Nom, email et mot de passe requis"]);
            return;
        }

        // Charger les utilisateurs existants
        $users = json_decode(file_get_contents($this->usersFile), true);
        $newId = count($users) + 1; // Générer un nouvel ID unique

        // Vérifier si l'email est déjà utilisé
        foreach ($users as $user) {
            if ($user['email'] === $data['email']) {
                echo json_encode(["error" => "Email déjà utilisé"]);
                return;
            }
        }

        // Hacher le mot de passe
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        // Ajouter le nouvel utilisateur
        $users[$newId] = [
            "id" => $newId,
            "nom" => $data['nom'],
            "email" => $data['email'],
            "password" => $hashedPassword,
            "role" => "utilisateur",
            "role_demande" => null,
            "token" => null
        ];

        // Sauvegarder dans le fichier JSON
        file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));

        echo json_encode(["message" => "Inscription réussie"]);
    }

    /**
     * Connexion et génération de token
     */
    public function login()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['email'], $data['password'])) {
            echo json_encode(["error" => "Email et mot de passe requis"]);
            return;
        }

        // Charger les utilisateurs
        $users = json_decode(file_get_contents($this->usersFile), true);

        foreach ($users as &$user) {
            if ($user['email'] === $data['email'] && password_verify($data['password'], $user['password'])) {
                // Générer un token unique
                $token = bin2hex(random_bytes(32));
                $user['token'] = $token;

                // Sauvegarder la mise à jour
                file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));

                echo json_encode(["message" => "Connexion réussie", "token" => $token]);
                return;
            }
        }

        echo json_encode(["error" => "Identifiants incorrects"]);
    }

    /**
     * Vérification du token et récupération des infos utilisateur
     */
    public function getUserByToken()
    {
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            echo json_encode(["error" => "Token manquant"]);
            return;
        }

        $token = trim(str_replace("Bearer", "", $headers['Authorization']));
        $users = json_decode(file_get_contents($this->usersFile), true);

        foreach ($users as $user) {
            if ($user['token'] === $token) {
                echo json_encode([
                    "id" => $user['id'],
                    "nom" => $user['nom'],
                    "email" => $user['email'],
                    "role" => $user['role'],
                    "role_demande" => $user['role_demande']
                ]);
                return;
            }
        }

        echo json_encode(["error" => "Utilisateur non trouvé ou token invalide"]);
    }
}

?>
