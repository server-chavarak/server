<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->StockNo_ID)) {
        http_response_code(400);
        echo "Invalid request";
        exit;
    }

    $StockNo_ID = $data->StockNo_ID;

    $stmt = $conn->prepare("DELETE FROM stock_no_order WHERE StockNo_ID = ?");
    $stmt->bind_param("i", $StockNo_ID); 
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo "stock_no_order deleted successfully";
    } else {
        http_response_code(500);
        echo "Error deleting stock_no_order";
    }

    $stmt->close();
}

$conn->close();
?>
