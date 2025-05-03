<?php

/**
 * Classe Router pour gérer le routage des requêtes HTTP.
 * Permet d'enregistrer des routes, de les associer à des gestionnaires,
 * et de traiter les requêtes entrantes en fonction de la méthode HTTP et du chemin.
 */
class Router
{
    /**
     * Tableau associatif stockant les routes enregistrées.
     * Chaque route contient la méthode HTTP, le chemin, le gestionnaire et le motif regex.
     * @var array
     */
    private array $routes = [];

    /**
     * Enregistre une nouvelle route avec sa méthode HTTP, son chemin et son gestionnaire.
     *
     * @param string   $method  Méthode HTTP (GET, POST, etc.).
     * @param string   $path    Chemin de la route (ex. : '/users/{id}').
     * @param callable $handler  Fonction à exécuter lorsque la route correspond.
     * @return void
     */
    public function register(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method), // Normalise la méthode en majuscules
            'path'    => $path,
            'handler' => $handler,
            'pattern' => $this->createPattern($path) // Génère le motif regex pour la correspondance
        ];
    }

    /**
     * Traite la requête entrante en recherchant une route correspondante.
     * Exécute le gestionnaire associé si une correspondance est trouvée,
     * sinon renvoie une réponse 404.
     *
     * @return void
     */
    public function handleRequest(): void
    {
        // Extrait le chemin de l'URI de la requête
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = $this->sanitizePath($path); // Nettoie le chemin
        $method = $_SERVER['REQUEST_METHOD']; // Récupère la méthode HTTP

        // Gère les requêtes CORS préliminaires (OPTIONS)
        $this->handleCorsPreflight();

        // Parcourt les routes enregistrées
        foreach ($this->routes as $route) {
            if ($this->isMatchingRoute($route, $path, $method)) {
                // Extrait les paramètres du chemin (ex. : {id})
                $params = $this->extractParams($route['pattern'], $path);
                // Appelle le gestionnaire avec les paramètres
                call_user_func_array($route['handler'], $params);
                return; // Termine après avoir traité la route
            }
        }

        // Aucune route trouvée, envoie une réponse 404
        $this->sendNotFoundResponse($path);
    }

    /**
     * Crée un motif regex à partir du chemin de la route.
     * Transforme les paramètres (ex. : {id}) en groupes nommés regex.
     *
     * @param string $path Chemin de la route.
     * @return string Motif regex correspondant.
     */
    private function createPattern(string $path): string
    {
        // Échappe les slashes pour le regex
        $pattern = str_replace('/', '\/', $path);
        // Remplace {param} par un groupe nommé (?<param>[^\/]+)
        $pattern = preg_replace('/\{(\w+)\}/', '(?<$1>[^\/]+)', $pattern);
        return '/^' . $pattern . '$/'; // Encadre le motif pour une correspondance exacte
    }

    /**
     * Vérifie si une route correspond à la méthode HTTP et au chemin de la requête.
     *
     * @param array  $route Route à vérifier.
     * @param string $path  Chemin de la requête.
     * @param string $method Méthode HTTP de la requête.
     * @return bool True si la route correspond, false sinon.
     */
    private function isMatchingRoute(array $route, string $path, string $method): bool
    {
        return $route['method'] === $method && preg_match($route['pattern'], $path);
    }

    /**
     * Extrait les paramètres du chemin en utilisant le motif regex de la route.
     *
     * @param string $pattern Motif regex de la route.
     * @param string $path   Chemin de la requête.
     * @return array Paramètres extraits (clés nommées).
     */
    private function extractParams(string $pattern, string $path): array
    {
        preg_match($pattern, $path, $matches);
        // Ne retourne que les clés nommées (exclut les indices numériques)
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Nettoie le chemin de la requête en supprimant le préfixe '/back'.
     *
     * @param string $path Chemin brut de la requête.
     * @return string Chemin nettoyé.
     */
    private function sanitizePath(string $path): string
    {
        return str_replace('/back', '', $path);
    }

    /**
     * Gère les requêtes CORS préliminaires (OPTIONS).
     * Renvoie une réponse 204 No Content et arrête l'exécution.
     *
     * @return void
     */
    private function handleCorsPreflight(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header("HTTP/1.1 204 No Content");
            exit();
        }
    }

    /**
     * Envoie une réponse 404 lorsque aucune route ne correspond.
     * Inclut des informations sur le chemin demandé et les routes disponibles.
     *
     * @param string $path Chemin de la requête.
     * @return void
     */
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