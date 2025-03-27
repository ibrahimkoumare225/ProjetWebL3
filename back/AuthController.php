<?php
class AuthController
{
	private string $filePath;
	public function __construct(string $filePath)
	{
		$this->filePath = $filePath;
	}
// TODO: Implement the handleRegister method
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
		$email = $_POST['email'] ?? '';
		$password = $_POST['password'] ?? '';
		$role = "cuisinier";

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			http_response_code(400);
			echo json_encode(['message' => 'Invalid email : ']);
			return;
		}

		if (strlen($password) < 8) {
			http_response_code(400);
			echo json_encode(['message' => 'Password must be at least 8 characters']);
			return;
		}

		$users = $this->getAllUsers();
		
		foreach ($users as $user) { 
			if ($user['mail'] === $email) {  
				http_response_code(400);
				echo json_encode([
					'message' => 'Email already registered',
					'redirect' => 'connexion.html'
				]);
				return;
			}
		}
		
		$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
		$user = [
			"id_user" => $idUtilisateur,
			"mail" => $email,
			"password" => $hashedPassword,
			"role" => $role,
		];

		$users[] = $user;

		file_put_contents($this->filePath, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

		http_response_code(201);
		echo json_encode(['message' => 'User registered successfully',
						'redirect' => 'connexion.html']);

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
			'id' => $userFound['id_user'],
			'email' => $userFound['mail'],
			'role' => $userFound['role']
		];
	
		// Réponse de succès
		http_response_code(200);
		echo json_encode([
			'message' => 'Connexion réussie',
			'user' => [
				'id' => $userFound['id_user'],
				'email' => $userFound['mail'],
				'role' => $userFound['role']
			]
		]);
	}
	


	public function handleLogout(): void
	{
		session_destroy(); // Clear session
		http_response_code(200);
		echo json_encode(['message' => 'Logged out successfully']);
	}
	public function validateAuth(): ?string
	{
		return $_SESSION['user'] ?? null;
	}
	private function getAllUsers(): array
	{
		return file_exists($this->filePath) ? json_decode(file_get_contents($this->filePath), true) ?? [] : [];
	}
}
