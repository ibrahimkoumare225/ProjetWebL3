<?php
class Recipe {
    private $file = "data/recipe.json";

    // 🔹 Récupérer toutes les recettes
    public function getAllRecipes() {
        $recipes = array();
        if (file_exists($this->file)) {
            $recipes = json_decode(file_get_contents($this->file), true);
        }
        return $recipes;
    }

    // 🔹 Ajouter une recette
    public function addRecipe($data) {
        $recipes = json_decode(file_get_contents($this->file), true);
        $data['id'] = count($recipes) + 1; // Ajouter un ID unique
        $recipes[] = $data;
        file_put_contents($this->file, json_encode($recipes));
        return $data;
    }

    // 🔹 Modifier une recette
    public function updateRecipe($id, $data) {
        $recipes = json_decode(file_get_contents($this->file), true);
        foreach ($recipes as &$recipe) {
            if ($recipe['id'] == $id) {
                $recipe = array_merge($recipe, $data); // Mettre à jour la recette
                file_put_contents($this->file, json_encode($recipes));
                return $recipe;
            }
        }
        return null;
    }

    // 🔹 Supprimer une recette
    public function deleteRecipe($id){
        $recipes = json_decode(file_get_contents($this->file), true);
        foreach ($recipes as $index => $recipe) {
            if ($recipe['id'] == $id) {
                unset($recipes[$index]); // Supprimer la recette
                file_put_contents($this->file, json_encode(array_values($recipes)));
                return true;
            }
        }
        return false;
    }
}
?>
