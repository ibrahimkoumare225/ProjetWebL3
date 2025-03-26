<?php
class AuthController {
    public function login() {
        echo json_encode(["message" => "User logged in"]);
    }
}
?>