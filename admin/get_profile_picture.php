<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'User not logged in.']);
    exit;
}

$username = $_SESSION['username'];

// Query ดึงข้อมูลผู้ใช้จากฐานข้อมูล
$query = "
    SELECT 
        u.ProfilePicture 
    FROM 
        users u
    WHERE 
        u.Username = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

if (!$userData) {
    echo json_encode(['error' => 'User not found.']);
    exit;
}

// ตรวจสอบว่าผู้ใช้มีรูปโปรไฟล์หรือไม่
$profilePicture = $userData['ProfilePicture'] ? "../uploads/" . $userData['ProfilePicture'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';

// ส่งข้อมูลกลับในรูปแบบ JSON
echo json_encode(['profilePicture' => $profilePicture]);
