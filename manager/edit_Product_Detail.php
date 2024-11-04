<?php
session_start();
require_once '../db.php';

// ตรวจสอบว่ามีการส่งค่า P_ID มาเพื่อแก้ไขข้อมูลหรือไม่
$product = null;
if (isset($_GET['P_ID'])) {
    $P_ID = $_GET['P_ID'];
    
    // ดึงข้อมูลจากตาราง product_detail ที่ต้องการแก้ไข
    $stmt = $conn->prepare("SELECT P_Name FROM product_detail WHERE P_ID = ?");
    $stmt->bind_param("i", $P_ID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();  // ข้อมูลที่จะนำมาแสดงในฟอร์ม
    } else {
        $_SESSION['alertMessage'] = "ไม่พบข้อมูลชนิดที่ต้องการแก้ไข!";
        $_SESSION['alertType'] = "error";
        header("Location: add_Product_Detail.php");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['alertMessage'] = "ไม่พบข้อมูลชนิดที่ต้องการแก้ไข!";
    $_SESSION['alertType'] = "error";
    header("Location: add_Product_Detail.php");
    exit();
}

// โค้ดอัปเดตข้อมูลในฐานข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $P_ID = $_POST['P_ID'];  // รับค่า P_ID จากฟอร์มที่ถูกซ่อนไว้
    $productName = $_POST['P_Name'];  // รับค่า P_Name จากฟอร์ม

    if (!empty($productName)) {
        // ตรวจสอบชื่อชนิดว่ามีอยู่ในระบบแล้วหรือไม่
        $checkStmt = $conn->prepare("SELECT P_ID FROM product_detail WHERE P_Name = ? AND P_ID != ?");
        $checkStmt->bind_param("si", $productName, $P_ID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // ถ้าข้อมูลซ้ำให้แสดงแจ้งเตือน
            $_SESSION['alertMessage'] = "ข้อมูลซ้ำ: ชื่อชนิดนี้มีอยู่แล้วในระบบ";
            $_SESSION['alertType'] = "error";
        } else {
            // อัปเดต P_Name ในตาราง product_detail
            $stmt = $conn->prepare("UPDATE product_detail SET P_Name = ? WHERE P_ID = ?");
            $stmt->bind_param("si", $productName, $P_ID);

            if ($stmt->execute()) {
                $_SESSION['alertMessage'] = "แก้ไขชื่อชนิดสำเร็จ!";
                $_SESSION['alertType'] = "success";
            } else {
                $_SESSION['alertMessage'] = "เกิดข้อผิดพลาดในการแก้ไข!";
                $_SESSION['alertType'] = "error";
            }
            $stmt->close();
        }

        $checkStmt->close();
        header("Location: edit_Product_Detail.php?P_ID=".$P_ID);
        exit;
    }
}

// โค้ดลบข้อมูลจากฐานข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $P_ID = $_POST['P_ID'];

    $stmt = $conn->prepare("DELETE FROM product_detail WHERE P_ID = ?");
    $stmt->bind_param("i", $P_ID);

    if ($stmt->execute()) {
        $_SESSION['alertMessage'] = "ลบชื่อชนิดสำเร็จ!";
        $_SESSION['alertType'] = "success";
    } else {
        $_SESSION['alertMessage'] = "เกิดข้อผิดพลาดในการลบ!";
        $_SESSION['alertType'] = "error";
    }

    $stmt->close();
    header("Location: add_Product_Detail.php");
    exit;
}

// รวมไฟล์ manager_index.html
include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขชื่อชนิด</title>
    <link rel="stylesheet" href="../css/product.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
</head>
<body>
    <a href="../manager/add_Product_Detail.php" class="back-link">ย้อนกลับ</a>
    <div class="edit">
        <form id="editProductForm" action="edit_Product_Detail.php?P_ID=<?php echo htmlspecialchars($P_ID); ?>" method="post">
            <input type="hidden" name="P_ID" value="<?php echo htmlspecialchars($P_ID); ?>">

            <h2>แก้ไขชื่อชนิด</h2>

            <div class="form-group">
                <label for="P_Name">ชื่อชนิด</label>
                <!-- แสดงชื่อชนิดที่ถูกดึงจากฐานข้อมูล -->
                <input type="text" id="P_Name" name="P_Name" value="<?php echo isset($product['P_Name']) ? htmlspecialchars($product['P_Name']) : ''; ?>" required>
            </div>

            <div class="footer">
                <button type="submit" name="update" class="approve">บันทึก</button>
                <button type="reset" class="delete">ยกเลิก</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php
            if (isset($_SESSION['alertMessage'])) {
                echo "Swal.fire({
                    icon: '{$_SESSION['alertType']}',
                    title: '{$_SESSION['alertMessage']}',
                    showConfirmButton: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../manager/add_Product_Detail.php';
                    }
                });";
                unset($_SESSION['alertMessage']);
                unset($_SESSION['alertType']);
            }
            ?>
        });
    </script>
</body>
</html>
