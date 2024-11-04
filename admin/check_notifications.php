<?php
require_once '../db.php'; // เชื่อมต่อฐานข้อมูล

// ตัวอย่างการตรวจสอบสถานะการแจ้งเตือนจากฐานข้อมูล
$sql = "SELECT COUNT(*) as count FROM notification WHERE Status IN ('red', 'orange')";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

// ส่งผลลัพธ์กลับไปในรูปแบบ JSON
echo json_encode(['needsNotification' => ($row['count'] > 0)]);
?>
