<?php
require_once '../db.php';

$input = file_get_contents('php://input');
$data = json_decode($input);

if (!isset($data->st_id)) {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

$st_id = $data->st_id;

$stmt = $conn->prepare("DELETE FROM work_step WHERE st_id = ?");
$stmt->bind_param("i", $st_id);

if ($stmt->execute()) {
    http_response_code(200);
    echo "Work step deleted successfully";
} else {
    http_response_code(500);
    echo "Error deleting work step";
}

$stmt->close();
$conn->close();
?>