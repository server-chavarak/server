<?php
session_start();
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profilePicture'])) {
    // Validate the file
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $fileName = $_FILES['profilePicture']['name'];
    $fileSize = $_FILES['profilePicture']['size'];
    $fileTmp = $_FILES['profilePicture']['tmp_name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Check if the extension is allowed
    if (!in_array($fileExt, $allowedExtensions)) {
        echo json_encode(['status' => 'error', 'message' => 'File type not allowed']);
        exit();
    }

    // Define the upload directory and new filename
    $uploadDir = '../uploads/';
    $newFileName = uniqid() . '.' . $fileExt; // Create a unique filename
    $uploadFile = $uploadDir . $newFileName;

    // Move the uploaded file to the uploads directory
    if (move_uploaded_file($fileTmp, $uploadFile)) {
        // Update the user's profile picture in the database
        $username = $_SESSION['username'];
        $sql = "UPDATE users SET ProfilePicture = ? WHERE Username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $newFileName, $username);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'profilePicture' => $newFileName]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update profile picture in database']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload the image']);
    }
}
?>
