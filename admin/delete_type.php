<?php
require_once '../db.php';

// ตรวจสอบข้อมูลที่ส่งมาจาก fetch API
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['T_ID'])) {
    $T_ID = $data['T_ID'];

    // ลบข้อมูลในตาราง type
    $sql = "DELETE FROM type WHERE T_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $T_ID);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid T_ID']);
}

$conn->close();
?>
