<?php
class Router
{
    private array $routes = []; 

    
    public function register(string $method, string $path, callable $handler): void
    {
        // On ajoute la route dans le tableau avec sa méthode HTTP, son chemin et sa fonction associée
        $this->routes[] = [
            'method' => strtoupper($method), 
            'path' => $path,
            'handler' => $handler 
        ];
    }

   
    public function handleRequest(): void
    {
        // On récupère la méthode HTTP utilisée par l'utilisateur 
        $method = $_SERVER['REQUEST_METHOD'];

        // On récupère le chemin demandé 
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // On définit les en-têtes pour les requêtes CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        // On cherche si une route correspond
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                // Si on trouve une route qui correspond, on exécute la fonction associée
                call_user_func($route['handler']);
                return; 
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}
