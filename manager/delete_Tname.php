<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ID = isset($_POST['ID']) ? intval($_POST['ID']) : 0; // รับค่า ID

    if ($ID > 0) {
        $stmt = $conn->prepare("DELETE FROM add_tname WHERE ID = ?");
        $stmt->bind_param("i", $ID);

        if ($stmt->execute()) {
            echo "Type deleted successfully";
        } else {
            http_response_code(500);
            echo "Error deleting type: " . $stmt->error;
        }

        $stmt->close();
    } else {
        http_response_code(400);
        echo "Invalid ID";
    }
}

$conn->close();
?>

