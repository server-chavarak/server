<?php
require_once '../db.php';

function deleteUser($username) {
    global $conn;

    // ตรวจสอบจำนวนบัญชี Admin ที่ได้รับการอนุมัติ (Approve = 1)
    $query = "SELECT COUNT(*) as approved_admin_count FROM users WHERE R_ID = 0 AND Approve = 1";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();

    // ค้นหาว่าผู้ใช้ที่ต้องการลบคือ Admin ที่ได้รับการอนุมัติหรือไม่
    $adminQuery = "SELECT R_ID, Approve FROM users WHERE Username = ?";
    $stmt = $conn->prepare($adminQuery);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $adminResult = $stmt->get_result();
    $adminRow = $adminResult->fetch_assoc();

    // ถ้าเหลือ Admin ที่ได้รับการอนุมัติแล้วเพียง 1 บัญชี ห้ามลบ
    if ($row['approved_admin_count'] == 1 && $adminRow['R_ID'] == 0 && $adminRow['Approve'] == 1) {
        return array('success' => false, 'message' => 'ไม่สามารถลบบัญชี Admin ที่ได้รับการอนุมัติแล้วเพียงบัญชีเดียวได้');
    }

    // ลบผู้ใช้
    $deleteQuery = "DELETE FROM users WHERE Username = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("s", $username);
    if ($stmt->execute()) {
        return array('success' => true, 'message' => 'ลบผู้ใช้สำเร็จ');
    } else {
        return array('success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบผู้ใช้');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->username)) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'คำขอไม่ถูกต้อง'));
        exit;
    }

    $username = $data->username;
    $response = deleteUser($username);

    http_response_code($response['success'] ? 200 : 403);
    echo json_encode($response);
}

$conn->close();
?>