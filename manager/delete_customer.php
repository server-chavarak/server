<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->Cus_ID)) {
        http_response_code(400);
        echo "Invalid request";
        exit;
    }

    $Cus_ID = $data->Cus_ID;

    $stmt = $conn->prepare("DELETE FROM customer WHERE Cus_ID = ?");
    $stmt->bind_param("i", $Cus_ID); 
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo "Customer deleted successfully";
    } else {
        http_response_code(500);
        echo "Error deleting customer";
    }

    $stmt->close();
}

$conn->close();
?>













