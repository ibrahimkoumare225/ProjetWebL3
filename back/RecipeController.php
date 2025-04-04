<?php
class RecipeController
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function handleRequest(): void
    {
        ob_start();
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        try {
            preg_match('/\/recipes\/(\d+)$/', $path, $matches);
            $id = $matches[1] ?? null;

            switch (true) {
                case $method === 'GET' && preg_match('/\/recipes$/', $path):
                    $this->getRecipes();
                    break;
                case $method === 'POST' && $path === '/recipes':
                    $this->addRecipe();
                    break;
                case $method === 'PUT' && preg_match('/\/recipes\/\d+$/', $path):
                    $this->updateRecipe((int)$id);
                    break;
                case $method === 'DELETE' && preg_match('/\/recipes\/\d+$/', $path):
                    $this->deleteRecipe((int)$id);
                    break;
                default:
                    http_response_code(404);
                    echo json_encode(['error' => 'Endpoint non trouvé']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        ob_end_flush();
    }

    private function checkAuth(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentification requise']);
            exit;
        }

        return $_SESSION['user'];
    }

    private function checkRecipeOwnership(array $recipe): void
    {
        $user = $this->checkAuth();
        $authorId = $recipe['Author']['id_user'] ?? null;
        $userId = $user['id_user'] ?? null;

        if ($user['role'] !== 'admin' && (int)$userId !== (int)$authorId) {
            http_response_code(403);
            echo json_encode(['error' => 'Action réservée à l\'auteur ou administrateur']);
            exit;
        }
    }

    public function getRecipes(): void
    {
        header('Content-Type: application/json');
        try {
            $recipes = $this->getAllRecipes();
            echo json_encode($recipes);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function addRecipe(): void
    {
        header('Content-Type: application/json');
        $user = $this->checkAuth();

        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            http_response_code(400);
            echo json_encode(['error' => 'Content-Type doit être application/json']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $requiredFields = ['name', 'nameFR', 'ingredients', 'stepsFR'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Le champ $field est requis"]);
                return;
            }
        }

        $recipes = $this->getAllRecipes();
        
        $newRecipe = [
            'id' => count($recipes) + 1,
            'name' => $input['name'],
            'nameFR' => $input['nameFR'],
            'Author' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'ingredients' => $input['ingredients'],
            'stepsFR' => $input['stepsFR'],
            'imageURL'=> $input['imageURL'],
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s'),
            'likes' => 0
        ];

        $recipes[] = $newRecipe;
        $this->saveRecipes($recipes);

        http_response_code(201);
        echo json_encode($newRecipe);
    }

    public function deleteRecipe(int $id): void
    {
        header('Content-Type: application/json');
        $recipes = $this->getAllRecipes();
        
        foreach ($recipes as $key => $recipe) {
            if ($recipe['id'] === $id) {
                $this->checkRecipeOwnership($recipe);
                
                array_splice($recipes, $key, 1);
                $this->saveRecipes($recipes);
                
                echo json_encode(['message' => 'Recette supprimée avec succès']);
                return;
            }
        }
        
        http_response_code(404);
        echo json_encode(['error' => 'Recette non trouvée']);
    }

    public function updateRecipe(int $id): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $recipes = $this->getAllRecipes();

        foreach ($recipes as &$recipe) {
            if ($recipe['id'] === $id) {
                $this->checkRecipeOwnership($recipe);

                $updatableFields = ['name', 'nameFR', 'ingredients', 'stepsFR', 'imageURL'];
                $hasUpdates = false;
                
                foreach ($updatableFields as $field) {
                    if (isset($input[$field])) {
                        $recipe[$field] = $input[$field];
                        $hasUpdates = true;
                    }
                }
                
                if (!$hasUpdates) {
                    echo json_encode($recipe);
                    return;
                }
                
                $recipe['updatedAt'] = date('Y-m-d H:i:s');
                $this->saveRecipes($recipes);
                
                echo json_encode($recipe);
                return;
            }
        }
        
        http_response_code(404);
        echo json_encode(['error' => 'Recette non trouvée']);
    }

    private function getAllRecipes(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $data = file_get_contents($this->filePath);
        return json_decode($data, true) ?: [];
    }

    private function saveRecipes(array $recipes): void
    {
        file_put_contents(
            $this->filePath,
            json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}