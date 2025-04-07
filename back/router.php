<?php
class Router
{
    private array $routes = [];

    public function register(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'pattern' => $this->createPattern($path)
        ];
    }

    public function handleRequest(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = $this->sanitizePath($path);
        $method = $_SERVER['REQUEST_METHOD'];

        $this->handleCorsPreflight();

        foreach ($this->routes as $route) {
            if ($this->isMatchingRoute($route, $path, $method)) {
                $params = $this->extractParams($route['pattern'], $path);
                call_user_func_array($route['handler'], $params);
                return;
            }
        }

        $this->sendNotFoundResponse($path);
    }

    private function createPattern(string $path): string
    {
        $pattern = str_replace('/', '\/', $path);
        return '/^' . preg_replace('/\{(\w+)\}/', '(?<$1>[^\/]+)', $pattern) . '$/';
    }

    private function isMatchingRoute(array $route, string $path, string $method): bool
    {
        return $route['method'] === $method && preg_match($route['pattern'], $path);
    }

    private function extractParams(string $pattern, string $path): array
    {
        preg_match($pattern, $path, $matches);
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function sanitizePath(string $path): string
    {
        return str_replace('/back', '', $path);
    }

    private function handleCorsPreflight(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header("HTTP/1.1 204 No Content");
            exit();
        }
    }

    private function sendNotFoundResponse(string $path): void
    {
        http_response_code(404);
        echo json_encode([
            'error' => 'Route non trouvée',
            'requested_path' => $path,
            'available_routes' => array_column($this->routes, 'path')
        ]);
    }
}
?>