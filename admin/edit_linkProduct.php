<?php
require_once '../db.php';
session_start(); 

// ตรวจสอบว่ามีการส่งค่า ID มาเพื่อแก้ไขข้อมูลหรือไม่
$product = null;
if (isset($_GET['ID'])) {
    $ID = $_GET['ID'];
    $stmt = $conn->prepare("SELECT * FROM add_link_product WHERE ID = ?");
    $stmt->bind_param("i", $ID);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
}

// ดึงข้อมูลรายชื่อผลิตภัณฑ์ทั้งหมดจากฐานข้อมูล
$stmt = $conn->prepare("SELECT ID, product_name FROM add_link_product");
$stmt->execute();
$result = $stmt->get_result();
$allProducts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// โค้ดอัปเดตข้อมูลในฐานข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $ID = $_POST['ID']; // รับค่า ID จากฟอร์มที่ถูกซ่อนไว้
    $productName = $_POST['product_name']; // รับค่า product_name จากฟอร์ม

    if (!empty($productName)) {
        $stmt = $conn->prepare("UPDATE add_link_product SET product_name = ? WHERE ID = ?");
        $stmt->bind_param("si", $productName, $ID);

        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "แก้ไขชื่อผลิตภัณฑ์สำเร็จ!";
            $_SESSION['alertType'] = "success";
        } else {
            $_SESSION['alertMessage'] = "เกิดข้อผิดพลาดในการแก้ไข!";
            $_SESSION['alertType'] = "error";
        }

        $stmt->close();
        header("Location: edit_linkProduct.php?ID=".$ID);
        exit;
    }
}

// โค้ดลบข้อมูลจากฐานข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $ID = $_POST['ID'];

    $stmt = $conn->prepare("DELETE FROM add_link_product WHERE ID = ?");
    $stmt->bind_param("i", $ID);

    if ($stmt->execute()) {
        $_SESSION['alertMessage'] = "ลบชื่อผลิตภัณฑ์สำเร็จ!";
        $_SESSION['alertType'] = "success";
    } else {
        $_SESSION['alertMessage'] = "เกิดข้อผิดพลาดในการลบ!";
        $_SESSION['alertType'] = "error";
    }

    $stmt->close();
    header("Location: add_link_product.php");
    exit;
}

// รวมไฟล์ admin_index.html
include 'admin_index.html';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขชื่อผลิตภัณฑ์</title>
    <link rel="stylesheet" href="../css/product.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
</head>
<body>
    <a href="../admin/add_link_product.php" class="back-link">ย้อนกลับ</a>
    <div class="edit">
        <form id="editProductForm" action="edit_linkProduct.php?ID=<?php echo htmlspecialchars($product['ID']); ?>" method="post">
            <input type="hidden" name="ID" value="<?php echo htmlspecialchars($product['ID']); ?>">

            <h2>แก้ไขชื่อผลิตภัณฑ์</h2>

            <div class="form-group">
                <label for="productName">ชื่อผลิตภัณฑ์</label>
                <select id="productName" name="product_name" required>
                    <?php foreach ($allProducts as $prod): ?>
                        <option value="<?php echo htmlspecialchars($prod['product_name']); ?>" <?php if ($prod['product_name'] == $product['product_name']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($prod['product_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                        window.location.href = '../admin/add_link_product.php';
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
