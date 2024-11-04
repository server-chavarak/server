<?php
session_start();
require_once '../db.php'; 

// ตรวจสอบว่ามีการส่งค่า Product_ID มาเพื่อแก้ไขข้อมูลหรือไม่
$product = null; // ประกาศตัวแปรให้มีค่าเริ่มต้นเป็น null
if (isset($_GET['Product_ID'])) {
    $productId = $_GET['Product_ID'];

    // ดึงข้อมูลผลิตภัณฑ์จากฐานข้อมูลเพื่อนำมาแสดงในฟอร์มแก้ไข
    $query = "SELECT P_ID, T_ID, Product_ID FROM product WHERE Product_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        $_SESSION['alertMessage'] = "ไม่พบข้อมูลผลิตภัณฑ์";
        $_SESSION['alertType'] = "error";
        header("Location: product.php");
        exit();
    }
    $stmt->close();
}

// Handle form submission for updating product
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['Product_ID'])) {
    $productId = $_POST['Product_ID'];  // รับค่า Product_ID จากแบบฟอร์มที่ถูกซ่อนไว้
    $newProductId = $_POST['P_ID'];     // รับค่าใหม่จากฟอร์ม
    $newTypeId = $_POST['T_ID'];

    // ตรวจสอบว่ามีการเปลี่ยนแปลงข้อมูลหรือไม่
    $query = "SELECT COUNT(*) FROM product WHERE P_ID = ? AND T_ID = ? AND Product_ID != ?";
    $checkStmt = $conn->prepare($query);
    $checkStmt->bind_param("iii", $newProductId, $newTypeId, $productId);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        // หากพบข้อมูลซ้ำ ให้แจ้งเตือน
        $_SESSION['alertMessage'] = "ผลิตภัณฑ์นี้มีอยู่แล้วในระบบ";
        $_SESSION['alertType'] = "error";
        header("Location: edit_product.php?Product_ID=" . $productId);
        exit();
    } else {
        // อัปเดตข้อมูลผลิตภัณฑ์ในฐานข้อมูล
        $updateQuery = "UPDATE product SET P_ID = ?, T_ID = ? WHERE Product_ID = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("iii", $newProductId, $newTypeId, $productId);

        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "แก้ไขผลิตภัณฑ์สำเร็จ";
            $_SESSION['alertType'] = "success";
        } else {
            $_SESSION['alertMessage'] = "ไม่สามารถแก้ไขผลิตภัณฑ์ได้";
            $_SESSION['alertType'] = "error";
        }

        $stmt->close();
        header("Location: edit_product.php?Product_ID=" . $productId);
        exit();
    }
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
    <title>Edit Product</title>
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
        <form action="edit_product.php" method="POST" id="addcustomer">
            <h2>แก้ไขผลิตภัณฑ์</h2>

            <input type="hidden" name="Product_ID" value="<?php echo htmlspecialchars($product['Product_ID'] ?? ''); ?>">

            <div class="form-group">
                <label for="P_ID" class="asterisk">ชื่อชนิด</label>
                <select id="P_ID" name="P_ID" required>
                    <option value="">เลือก...</option>
                    <?php
                    if ($result_pipetype->num_rows > 0) {
                        while ($row = $result_pipetype->fetch_assoc()) {
                            $selected = ($row['P_ID'] == $product['P_ID']) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($row['P_ID']) . "\" $selected>" . htmlspecialchars($row['P_Name']) . "</option>";
                        }
                    } else {
                        echo "<option value=\"\">ไม่พบข้อมูล</option>";
                    }
                    ?>
                </select>
                
            </div>

            <div class="form-group">
                <label for="T_ID" class="asterisk">ชื่อประเภทและขนาดท่อ</label>
                <select id="T_ID" name="T_ID" required>
                    <option value="">เลือก...</option>
                    <?php
                    if ($result_type->num_rows > 0) {
                        while ($row = $result_type->fetch_assoc()) {
                            $selected = ($row['T_ID'] == $product['T_ID']) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($row['T_ID']) . "\" $selected>" . htmlspecialchars($row['DisplayText']) . "</option>";
                        }
                    } else {
                        echo "<option value=\"\">ไม่พบข้อมูล</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="footer">
                <button type="submit" class="approve">บันทึก</button>
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
            echo "Swal.fire({
                icon: '{$alertType}',
                title: '{$_SESSION['alertMessage']}',
                showConfirmButton: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    if('{$alertType}' === 'success') {
                        window.location.href = 'product.php';
                    } else {
                        window.location.href = 'edit_product.php?Product_ID={$product['Product_ID']}';
                    }
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
