<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่า TD_ID จาก POST
    $ID = isset($_POST['TD_ID']) ? intval($_POST['TD_ID']) : 0;

    if ($ID > 0) {
        // เตรียมคำสั่ง SQL สำหรับลบข้อมูล
        $stmt = $conn->prepare("DELETE FROM Type_Detail WHERE TD_ID = ?");
        $stmt->bind_param("i", $ID);

        // ตรวจสอบการลบข้อมูล
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
} else {
    http_response_code(405); // ส่งสถานะ 405 หากวิธีการร้องขอไม่ใช่ POST
    echo "Invalid request method";
}

$conn->close();
?>
