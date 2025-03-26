<?php

// Fichier JSON contenant les recettes
define("FILE_JSON", "recipe.json");

class RecetteController {
    // Charger les recettes depuis le fichier JSON
    private function loadRecettes() {
        if (!file_exists(FILE_JSON)) {
            file_put_contents(FILE_JSON, json_encode([]));
        }
        return json_decode(file_get_contents(FILE_JSON), true);
    }

    // Sauvegarder les recettes dans le fichier JSON
    private function saveRecettes($recettes) {
        file_put_contents(FILE_JSON, json_encode($recettes, JSON_PRETTY_PRINT));
    }

    // CRUD - Read All
    public function getAllRecettes() {
        echo json_encode($this->loadRecettes());
    }

    // CRUD - Read One
    public function getRecette($id) {
        $recettes = $this->loadRecettes();
        foreach ($recettes as $recette) {
            if ($recette['id'] == $id) {
                echo json_encode($recette);
                return;
            }
        }
        echo json_encode(["error" => "Recette non trouvée"]);
    }

    // CRUD - Create
    public function addRecette($titre, $description) {
        $recettes = $this->loadRecettes();
        $newRecette = [
            "id" => time(), // ID unique basé sur le timestamp
            "titre" => $titre,
            "description" => $description
        ];
        $recettes[] = $newRecette;
        $this->saveRecettes($recettes);
        echo json_encode(["success" => "Recette ajoutée"]);
    }

    // CRUD - Update
    public function updateRecette($id, $titre, $description) {
        $recettes = $this->loadRecettes();
        foreach ($recettes as &$recette) {
            if ($recette['id'] == $id) {
                $recette['titre'] = $titre;
                $recette['description'] = $description;
                $this->saveRecettes($recettes);
                echo json_encode(["success" => "Recette modifiée"]);
                return;
            }
        }
        echo json_encode(["error" => "Recette non trouvée"]);
    }

    // CRUD - Delete
    public function deleteRecette($id) {
        $recettes = $this->loadRecettes();
        $recettes = array_filter($recettes, function ($recette) use ($id) {
            return $recette['id'] != $id;
        });
        $this->saveRecettes(array_values($recettes));
        echo json_encode(["success" => "Recette supprimée"]);
    }
}

?>
