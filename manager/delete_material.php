<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->Raw_ID)) {
        http_response_code(400);
        echo "Invalid request";
        exit;
    }

    $Raw_ID = $data->Raw_ID;

    $stmt = $conn->prepare("DELETE FROM raw_material WHERE Raw_ID = ?");
    $stmt->bind_param("i", $Raw_ID);

    if ($stmt->execute()) {
        http_response_code(200);
        echo "raw_material deleted successfully";
    } else {
        http_response_code(500);
        echo "Error deleting raw_material";
    }

    $stmt->close();
}

$conn->close();
?>
