<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->Stock_ID)) {
        http_response_code(400);
        echo "Invalid request";
        exit;
    }

    $Stock_ID = $data->Stock_ID;

    $stmt = $conn->prepare("DELETE FROM stock WHERE Stock_ID = ?");
    $stmt->bind_param("i", $Stock_ID); 
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo "Stock deleted successfully";
    } else {
        http_response_code(500);
        echo "Error deleting customer";
    }

    $stmt->close();
}

$conn->close();
?>
