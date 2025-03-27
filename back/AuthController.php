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
		// Hints:
		// 1. Check if the request Content-Type is 'application/x-www-form-urlencoded'
		// 2. Get the email and password from the POST data
		// 3. Validate the email and password
		// 4. Check if the user exists and the password is correct
		// 5. Store the user session
		// 6. Return a success message with HTTP status code 200
		// Additional hints:
		// If any error occurs, return an error message with the appropriate HTTP status code
		// Make sure to set the Content-Type header to 'application/json' in the response
		// You can use the getAllUsers method to get the list of registered users
		// You can use the password_verify function to verify the password
		// You can use the $_SESSION superglobal to store the user session
		// You can use the json_encode function to encode an array as JSON
		// You can use the http_response_code function to set the HTTP status code
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
