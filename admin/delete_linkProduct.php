<?php
require_once '../db.php'; // ตรวจสอบให้แน่ใจว่าเส้นทางถูกต้อง

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ID = isset($_POST['ID']) ? intval($_POST['ID']) : 0; // รับค่า ID ที่ต้องการลบ

    if ($ID > 0) {
        $stmt = $conn->prepare("DELETE FROM add_link_product WHERE ID = ?");
        $stmt->bind_param("i", $ID);

        if ($stmt->execute()) {
            echo "Product deleted successfully";
        } else {
            http_response_code(500);
            echo "Error deleting product: " . $stmt->error;
        }

        $stmt->close();
    } else {
        http_response_code(400);
        echo "Invalid ID";
    }
}

$conn->close();
?>
