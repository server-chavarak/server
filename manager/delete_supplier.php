
<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->Sup_ID)) {
        http_response_code(400);
        echo "Invalid request";
        exit;
    }

    $Sup_ID = $data->Sup_ID;

    $stmt = $conn->prepare("DELETE FROM supplier WHERE Sup_ID = ?");
    $stmt->bind_param("i", $Sup_ID); 
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo "supplier deleted successfully";
    } else {
        http_response_code(500);
        echo "Error deleting supplier";
    }

    $stmt->close();
}

$conn->close();
?>
