<?php
require_once '../db.php'; // ตรวจสอบให้แน่ใจว่าเส้นทางถูกต้อง

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deleteID'])) {
    $ID = isset($_POST['deleteID']) ? intval($_POST['deleteID']) : 0; // รับค่า ID ที่ต้องการลบ

    if ($ID > 0) {
        // เตรียมคำสั่ง SQL สำหรับการลบข้อมูล
        $stmt = $conn->prepare("DELETE FROM Product_Detail WHERE P_ID = ?");
        if ($stmt === false) {
            // แสดงข้อผิดพลาดหาก prepare statement ไม่สำเร็จ
            echo "Error preparing statement: " . $conn->error;
            exit();
        }
        
        $stmt->bind_param("i", $ID);

        if ($stmt->execute()) {
            // หากลบสำเร็จ ส่งข้อความสำเร็จกลับไป
            echo "Product deleted successfully";
        } else {
            // หากเกิดข้อผิดพลาดในการลบ ส่งข้อความแสดงข้อผิดพลาดกลับไป
            http_response_code(500);
            echo "Error deleting product: " . $stmt->error;
        }

        $stmt->close();
    } else {
        // ส่งข้อความเมื่อ ID ไม่ถูกต้อง
        http_response_code(400);
        echo "Invalid ID";
    }
}

$conn->close();
?>
