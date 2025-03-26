<?php
class RecipeController {
    public function getRecipes() {
        echo json_encode(["message" => "List of recipes"]);
    }
}
?>

