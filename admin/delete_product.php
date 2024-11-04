<?php
require_once '../db.php';

// ตรวจสอบว่ามีการส่ง Product_ID มาหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productID = isset($_POST['Product_ID']) ? $_POST['Product_ID'] : null;

    if ($productID) {
        // เตรียมคำสั่ง SQL เพื่อลบข้อมูล
        $stmt = $conn->prepare("DELETE FROM product WHERE Product_ID = ?");
        $stmt->bind_param("i", $productID);

        if ($stmt->execute()) {
            echo 'success'; // ส่งข้อความตอบกลับหากลบสำเร็จ
        } else {
            echo 'ไม่สามารถลบข้อมูลได้: ' . $stmt->error; // ส่งข้อความตอบกลับหากลบไม่สำเร็จ
        }

        $stmt->close();
    } else {
        echo 'ข้อมูลไม่ถูกต้อง';
    }
} else {
    echo 'วิธีการส่งข้อมูลไม่ถูกต้อง';
}

$conn->close();
?>
