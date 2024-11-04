<?php

require '../db.php'; // เชื่อมต่อฐานข้อมูล

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบการมีอยู่ของ POST variables
    if (isset($_POST['username']) && isset($_POST['approve'])) {
        $username = trim($_POST['username']);
        $approve = trim($_POST['approve']);

        // ตรวจสอบการมีอยู่ของผู้ใช้
        $check_user_sql = "SELECT R_ID, Approve FROM users WHERE username = ?";
        $stmt = $conn->prepare($check_user_sql);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) {
            $response['message'] = 'ไม่พบผู้ใช้';
            echo json_encode($response);
            exit();
        }

        // ตรวจสอบจำนวนบัญชี Admin ที่ได้รับการอนุมัติ (Approve = 1)
        $approved_admin_query = "SELECT COUNT(*) as approved_admin_count FROM users WHERE R_ID = 0 AND Approve = 1";
        $approved_admin_result = $conn->query($approved_admin_query);
        $approved_admin_row = $approved_admin_result->fetch_assoc();

        // ตรวจสอบว่าเป็น Admin ที่ได้รับการอนุมัติหรือไม่ และห้ามเปลี่ยนสถานะเป็นไม่อนุมัติถ้าเหลือ Admin เพียงบัญชีเดียว
        if ($user['R_ID'] == 0 && $user['Approve'] == 1 && $approve == '0' && $approved_admin_row['approved_admin_count'] == 1) {
            $response['message'] = 'บัญชี Admin ไม่สามารถเปลี่ยนเป็นสถานะไม่อนุมัติได้ หากเหลือเพียง 1 บัญชีที่ได้รับการอนุมัติ';
            echo json_encode($response);
            exit();
        }

        // อัปเดตสถานะผู้ใช้
        $update_sql = "UPDATE users SET Approve = ? WHERE username = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('is', $approve, $username);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'สถานะผู้ใช้ได้รับการอัปเดตแล้ว';
        } else {
            $response['message'] = 'เกิดข้อผิดพลาดในการอัปเดตสถานะ';
        }

        $stmt->close();
    } else {
        $response['message'] = 'ข้อมูลไม่สมบูรณ์';
    }
}

$conn->close();
echo json_encode($response);



?>
