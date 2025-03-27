<?php
class CommentController {
    public function getComments() {
        echo json_encode(["message" => "List of comments"]);
    }
}
?>
