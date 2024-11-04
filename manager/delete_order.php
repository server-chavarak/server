<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['WO_No'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    $WO_No = $data['WO_No'];

    $stmt = $conn->prepare("DELETE FROM orders WHERE WO_No = ?");
    $stmt->bind_param("i", $WO_No); 
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error deleting order']);
    }

    $stmt->close();
}

$conn->close();
?>
