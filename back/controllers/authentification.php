<?php
session_start(); // Démarrer la session pour gérer la connexion

class AuthController {
    private $file = "data/users.json";

    // Lire les données JSON
    private function readJson() {
        return json_decode(file_get_contents($this->file), true);
    }

    // Sauvegarder les données JSON
    private function writeJson($data) {
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));
    }

    // Inscription d'un utilisateur
    public function register($name, $surname, $email, $password) {
        $data = $this->readJson();

        // Vérifier si l'email existe déjà
        foreach ($data["users"] as $user) {
            if ($user["email"] == $email) {
                echo "Cet email est déjà utilisé.";
                return;
            }
        }

        // Ajouter le nouvel utilisateur
        $newUser = [
            "id" => count($data["users"]) + 1,
            "surname" => $surname,
            "name" => $name,
            "email" => $email,
            "password" => password_hash($password, PASSWORD_DEFAULT),
            "role" => "Cuisinier" // Rôle par défaut
        ];
        $data["users"][] = $newUser;
        $this->writeJson($data);

        echo "Inscription réussie. Vous pouvez maintenant vous connecter.";
    }

    // Connexion de l'utilisateur
    public function login($email, $password) {
        $data = $this->readJson();

        foreach ($data["users"] as $user) {
            if ($user["email"] == $email && password_verify($password, $user["password"])) {
                $_SESSION["user"] = $user;
                echo "Connexion réussie. Bienvenue, " . $user["name"] . "!";
                return;
            }
        }
        echo "Email ou mot de passe incorrect.";
    }

    // Déconnexion
    public function logout() {
        session_destroy();
        echo "Déconnexion réussie.";
    }

    // Vérifier si un utilisateur est connecté
    public function isAuthenticated() {
        if (isset($_SESSION["user"])) {
            echo "Utilisateur connecté : " . $_SESSION["user"]["name"];
        } else {
            echo "Aucun utilisateur connecté.";
        }
    }
}
?>
