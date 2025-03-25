<?php

require_once 'RecetteController.php';

$controller = new RecetteController();

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    switch ($action) {
        case 'getAll':
            $controller->getAllRecettes();
            break;
        case 'get':
            if (isset($_GET['id'])) {
                $controller->getRecette($_GET['id']);
            } else {
                echo json_encode(["error" => "ID requis"]);
            }
            break;
        case 'add':
            if (isset($_POST['titre']) && isset($_POST['description'])) {
                $controller->addRecette($_POST['titre'], $_POST['description']);
            } else {
                echo json_encode(["error" => "Titre et description requis"]);
            }
            break;
        case 'update':
            if (isset($_POST['id']) && isset($_POST['titre']) && isset($_POST['description'])) {
                $controller->updateRecette($_POST['id'], $_POST['titre'], $_POST['description']);
            } else {
                echo json_encode(["error" => "ID, titre et description requis"]);
            }
            break;
        case 'delete':
            if (isset($_POST['id'])) {
                $controller->deleteRecette($_POST['id']);
            } else {
                echo json_encode(["error" => "ID requis"]);
            }
            break;
        default:
            echo json_encode(["error" => "Action inconnue"]);
            break;
    }
} else {
    echo json_encode(["error" => "Aucune action spécifiée"]);
}

?>
