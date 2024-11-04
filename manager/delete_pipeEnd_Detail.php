<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ID'])) {
    $pe_id = $_POST['ID'];

    // เตรียมคำสั่ง SQL เพื่อลบข้อมูลจาก PipeEnd_Detail
    $stmt = $conn->prepare("DELETE FROM PipeEnd_Detail WHERE PE_ID = ?");
    $stmt->bind_param("i", $pe_id);

    if ($stmt->execute()) {
        echo "ลบข้อมูลสำเร็จ"; // ส่งข้อความกลับไปยัง Fetch API
    } else {
        echo "เกิดข้อผิดพลาดในการลบข้อมูล: " . $stmt->error; // ส่งข้อความข้อผิดพลาดกลับไป
    }

    $stmt->close();
} else {
    echo "ไม่พบข้อมูลที่จะลบ";
}

$conn->close(); // ปิดการเชื่อมต่อฐานข้อมูล
?>
