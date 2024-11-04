<?php
session_start();
require_once '../db.php';

$username = $_SESSION['username'] ?? '';

if ($username) {
    $defaultProfilePicture = null; // ลบค่ารูปภาพ
    $updateQuery = "UPDATE users SET ProfilePicture = ? WHERE Username = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('ss', $defaultProfilePicture, $username);
    $stmt->execute();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
