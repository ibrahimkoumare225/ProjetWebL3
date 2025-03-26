<?php
class Router
{
    private array $routes = [];

    public function register(string $method, string $path, callable $handler): void
    {
        // Ajout de la route avec méthode, chemin et fonction associée
        $this->routes[] = [
            'method' => strtoupper($method), 
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function handleRequest(): void
    {
        // Récupération de la méthode HTTP et du chemin demandé
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // En-têtes pour les requêtes CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        // Vérification si une route correspond
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                call_user_func($route['handler']);  // Appel de la fonction associée
                return;
            }
        }

        // Si aucune route correspond, renvoi d'une erreur 404
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}
