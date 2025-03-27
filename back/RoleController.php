<?php
class RoleController {
    
    private $filePath = 'roles.json';

    public function getRoles() {
        if (!file_exists($this->filePath)) {
            echo json_encode(["error" => "File not found"]);
            return;
        }

        $roles = json_decode(file_get_contents($this->filePath), true);

        if ($roles === null) {
            echo json_encode(["error" => "Invalid JSON format"]);
            return;
        }

        echo json_encode($roles);
    }
}
