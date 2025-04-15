<?php
class AuthController
{
	private string $filePath;
	public function __construct(string $filePath)
	{
		$this->filePath = $filePath;
	}
// TODO: Implement the handleRegister method
	public function handleRegister(): void
	{
		header('Content-Type: application/json');

		if ($_SERVER['CONTENT_TYPE'] !== 'application/x-www-form-urlencoded') {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid Content-Type header']);
			return;
		}

		$idUtilisateur = uniqid();
		$username = $_POST['name'] ?? '';
		$userprename = $_POST['prenom'] ?? '';
		$email = $_POST['email'] ?? '';
		$password = $_POST['password'] ?? '';
		$role = "utilisateur";

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			http_response_code(400);
			echo json_encode(['message' => 'email Invalid  : ']);
			return;
		}

		if (strlen($password) < 8) {
			http_response_code(400);
			echo json_encode(['message' => 'Le mot de passe doit comporter au moins 8 caractères.']);
			return;
		}
		if (strlen($username) < 2) {
			http_response_code(400);
			echo json_encode(['message' => 'Le nom doit comporter au moins 2 caractères.']);
			return;
		}
		if (strlen($userprename) < 2) {
			http_response_code(400);
			echo json_encode(['message' => 'Le prenom doit comporter au moins 2 caractères.']);
			return;
		}

		$users = $this->getAllUsers();
		
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
		
		$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
		$user = [
			"id" => $idUtilisateur,
			"name" =>$username,
			"prenom" =>$userprename,
			"mail" => $email,
			"password" => $hashedPassword,
			"role" => $role,
		];

		$users[] = $user;

		file_put_contents($this->filePath, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

		http_response_code(201);
		// Après l'enregistrement réussi
		echo json_encode([
			'message' => 'Utilisateur enregistré avec succès',
			'redirect' => 'http://localhost:3000/connexion.html' // URL complète
]);

	}
	// TODO: Implement the handleLogin method
	public function handleLogin(): void
	{
		// Définir le Content-Type en JSON
		header("Content-Type: application/json");
	
		// Vérifier le type de contenu
		if (!isset($_SERVER['CONTENT_TYPE']) || stripos($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') === false) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid Content-Type header']);
			return;
		}
	
		// Récupérer les données
		$email = $_POST['email'] ?? null;
		$password = $_POST['password'] ?? null;
	
		// Vérifier si les champs sont vides
		if (!$email || !$password) {
			http_response_code(400);
			echo json_encode(['message' => 'Email et mot de passe requis']);
			return;
		}
	
		// Récupérer la liste des utilisateurs
		$users = $this->getAllUsers();
		$userFound = null;
	
		// Vérifier si l'utilisateur existe
		foreach ($users as $user) {
			if ($user['mail'] === $email) {
				$userFound = $user;
				break;
			}
		}
	
		if (!$userFound) {
			http_response_code(404); // Utilisateur non trouvé
			echo json_encode(['message' => 'Utilisateur non trouvé']);
			return;
		}
	
		// Vérifier le mot de passe
		if (!password_verify($password, $userFound['password'])) {
			http_response_code(401); // Unauthorized
			echo json_encode(['message' => 'Mot de passe incorrect']);
			return;
		}
	
		// Démarrer la session si elle n'est pas active
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
	
		// Stocker l'utilisateur en session
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
	


	public function handleLogout(): void
{
    // Ajouter les headers CORS
    header('Access-Control-Allow-Origin: http://localhost:3000');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = [];
    
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

    if (session_destroy()) {
        http_response_code(200);
        echo json_encode(['message' => 'Déconnexion réussie']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Erreur lors de la destruction de la session']);
    }
}

	public function validateAuth(): ?string
	{
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		return $_SESSION['user']['email'] ?? null;
	}

	private function getAllUsers(): array
	{
		if (!file_exists($this->filePath)) {
			return [];
		}

		$data = json_decode(file_get_contents($this->filePath), true);
		
		return is_array($data) ? $data : [];
	}

}
