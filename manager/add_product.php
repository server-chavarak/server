<?php
session_start();
require_once '../db.php'; 

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $productId = $_POST['P_ID'];  // ใช้ฟิลด์ P_ID จากแบบฟอร์ม
    $typeId = $_POST['T_ID'];

    // Query to check for duplicate product
    $checkQuery = "SELECT COUNT(*) FROM product WHERE P_ID = ? AND T_ID = ?";
    $checkStmt = $conn->prepare($checkQuery);

    if (!$checkStmt) {
        die("Error preparing statement: " . $conn->error);  // แสดงข้อผิดพลาดจาก MySQL
    }

    $checkStmt->bind_param("ii", $productId, $typeId);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        // หากพบข้อมูลซ้ำ ให้แจ้งเตือน แต่ยังคงอยู่หน้า add_product.php
        $_SESSION['alertMessage'] = "ผลิตภัณฑ์นี้มีอยู่แล้วในระบบ";
        $_SESSION['alertType'] = "error";
        header("Location: add_product.php");  // คงอยู่หน้า add_product.php
        exit();
    } else {
        // Insert product into database
        $query = "INSERT INTO product (P_ID, T_ID) VALUES (?, ?)";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            die("Error preparing insert statement: " . $conn->error);
        }

        $stmt->bind_param("ii", $productId, $typeId);

        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "เพิ่มผลิตภัณฑ์สำเร็จ";
            $_SESSION['alertType'] = "success";
        } else {
            $_SESSION['alertMessage'] = "ไม่สามารถเพิ่มผลิตภัณฑ์ได้";
            $_SESSION['alertType'] = "error";
        }

        $stmt->close();
    }

    // แสดงการแจ้งเตือนที่หน้า add_product.php
    header("Location: add_product.php");
    exit();
}

// Query to fetch product types from the database
$query_pipetype = "SELECT P_ID, P_Name FROM Product_Detail";
$result_pipetype = $conn->query($query_pipetype);

if (!$result_pipetype) {
    die("Error in fetching product details: " . $conn->error);
}

// Query to fetch pipe types from the database
$query_type = "SELECT t.T_ID, 
                CONCAT(td.TD_Name, ' - Ø', t.Pipe_Size, 'mm. - ', pe.PE_Name, 
                IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS DisplayText
               FROM type t
               JOIN type_detail td ON t.TD_ID = td.TD_ID
               JOIN Pipeend_detail pe ON t.PE_ID = pe.PE_ID";

$result_type = $conn->query($query_type);

if (!$result_type) {
    die("Error retrieving type data: " . $conn->error);
}

include 'manager_index.html';
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
    <link rel="stylesheet" href="../css/product.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
    <a href="../manager/product.php" class="back-link">ย้อนกลับ</a>
    <div class="add">
        <form action="add_product.php" method="POST" id="addcustomer">
            <h2>เพิ่มผลิตภัณฑ์</h2>

            <div class="form-group">
                <label for="P_ID" class="asterisk">ชื่อชนิด</label>
                <select id="P_ID" name="P_ID" required>
                    <option value="">เลือก...</option>
                    <?php
                    if ($result_pipetype->num_rows > 0) {
                        while ($row = $result_pipetype->fetch_assoc()) {
                            echo "<option value=\"" . htmlspecialchars($row['P_ID']) . "\">" . htmlspecialchars($row['P_Name']) . "</option>";
                        }
                    } else {
                        echo "<option value=\"\">ไม่พบข้อมูล</option>";
                    }
                    ?>
                </select>
                <div class="add_1"><a href="../manager/add_Product_Detail.php" class="add-link"> +</a></div>
            </div>

            <div class="form-group">
                <label for="T_ID" class="asterisk">ชื่อประเภทและขนาดท่อ</label>
                <select id="T_ID" name="T_ID" required>
                    <option value="">เลือก...</option>
                    <?php
                    if ($result_type->num_rows > 0) {
                        while ($row = $result_type->fetch_assoc()) {
                            echo "<option value=\"" . htmlspecialchars($row['T_ID']) . "\">" . htmlspecialchars($row['DisplayText']) . "</option>";
                        }
                    } else {
                        echo "<option value=\"\">ไม่พบข้อมูล</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="footer">
                <button type="submit" class="approve">เพิ่ม</button>
                <button type="reset" class="delete">ยกเลิก</button>
            </div>
        </form>
    </div>

    

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Alert handling
        <?php
        if (isset($_SESSION['alertMessage'])) {
            $alertType = isset($_SESSION['alertType']) ? $_SESSION['alertType'] : 'info';
            // แจ้งเตือน ถ้าเพิ่มข้อมูลสำเร็จ จะเปลี่ยนหน้าไป product.php ถ้าเกิดข้อผิดพลาดหรือข้อมูลซ้ำจะคงอยู่ที่หน้า add_product.php
            echo "Swal.fire({
                icon: '{$alertType}',
                title: '{$_SESSION['alertMessage']}',
                showConfirmButton: true,
            }).then((result) => {
                if (result.isConfirmed && '{$alertType}' === 'success') {
                    window.location.href = 'product.php';  // ถ้าสำเร็จ จะเปลี่ยนไปที่หน้า product.php
                }
            });";
            unset($_SESSION['alertMessage']);
            unset($_SESSION['alertType']);
        }
        ?>

        // Initialize Select2 for dropdowns
        $('#P_ID').select2();
        $('#T_ID').select2();
    });
    </script>

</body>
</html>
