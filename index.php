<?php
require_once "controllers/Recipe.php";
$controller = new Recipe();

$method = $_SERVER["REQUEST_METHOD"];

// 🔹 Récupérer toutes les recettes
if ($method == "GET") {
    echo json_encode($controller->getAllRecipes());
}

// 🔹 Ajouter une recette
elseif ($method == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    // Vérification des champs
    if (!isset($data['name']) || !isset($data['author']) || !isset($data['description'])) {
        echo json_encode(["error" => "Les champs name, author et description sont requis."]);
        exit;
    }

    echo json_encode($controller->addRecipe($data));
}

// 🔹 Modifier une recette (PUT)
elseif ($method == "PUT") {
    parse_str(file_get_contents("php://input"), $data);

    if (!isset($data['id'])) {
        echo json_encode(["error" => "L'ID de la recette est requis pour la modification."]);
        exit;
    }

    echo json_encode($controller->updateRecipe($data['id'], $data));
}

// 🔹 Supprimer une recette (DELETE)
elseif ($method == "DELETE") {
    parse_str(file_get_contents("php://input"), $data);

    if (!isset($data['id'])) {
        echo json_encode(["error" => "L'ID de la recette est requis pour la suppression."]);
        exit;
    }

    echo json_encode(["success" => $controller->deleteRecipe($data['id'])]);
}
?>
