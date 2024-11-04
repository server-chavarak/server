<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ID = isset($_POST['ID']) ? intval($_POST['ID']) : 0; // รับค่า ID

    if ($ID > 0) {
        // ตรวจสอบการเชื่อมต่อฐานข้อมูล
        if ($conn->connect_error) {
            http_response_code(500);
            echo "Connection failed: " . $conn->connect_error;
            exit;
        }

        // เตรียมคำสั่ง SQL สำหรับลบข้อมูล
        $stmt = $conn->prepare("DELETE FROM add_pipeEnd WHERE ID = ?");
        if (!$stmt) {
            http_response_code(500);
            echo "Failed to prepare statement: " . $conn->error;
            exit;
        }

        $stmt->bind_param("i", $ID); // ผูกค่าตัวแปร ID เข้ากับคำสั่ง SQL

        if ($stmt->execute()) {
            echo "Type deleted successfully"; // แสดงข้อความเมื่อการลบสำเร็จ
        } else {
            http_response_code(500);
            echo "Error deleting type: " . $stmt->error; // แสดงข้อความเมื่อการลบผิดพลาด
        }

        $stmt->close(); // ปิดการใช้งาน statement
    } else {
        http_response_code(400);
        echo "Invalid ID"; // แสดงข้อความเมื่อค่า ID ไม่ถูกต้อง
    }
} else {
    http_response_code(405);
    echo "Method not allowed"; // แสดงข้อความเมื่อการร้องขอไม่ใช่ POST
}

$conn->close(); // ปิดการเชื่อมต่อฐานข้อมูล
?>
