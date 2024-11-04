<?php
session_start();
require_once '../db.php';

// ตรวจสอบว่ามีการส่ง Product_ID มาหรือไม่
$productId = isset($_GET['Product_ID']) ? $_GET['Product_ID'] : null;
$productName = '';
$typeId = '';

// ดึงข้อมูลผลิตภัณฑ์สำหรับการแก้ไข
if ($productId) {
    $sql = "SELECT Product_ID, Product_name, T_ID FROM product WHERE Product_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $stmt->bind_result($productId, $productName, $typeId);
    $stmt->fetch();
    $stmt->close();
}

// โค้ดอัปเดตข้อมูลในฐานข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบว่าต้องการอัปเดตผลิตภัณฑ์หรือประเภทท่อ
    if (isset($_POST['update'])) {
        $T_ID = $_POST['ID']; // รับค่า ID จากฟอร์มที่ถูกซ่อนไว้
        $pipeTypeName = $_POST['Name_type']; // รับค่า Name_type จากฟอร์ม

        if (!empty($pipeTypeName)) {
            $stmt = $conn->prepare("UPDATE add_tname SET Name_type = ? WHERE ID = ?");
            $stmt->bind_param("si", $pipeTypeName, $T_ID);

            if ($stmt->execute()) {
                $_SESSION['alertMessage'] = "แก้ไขประเภทท่อสำเร็จ!";
                $_SESSION['alertType'] = "success";
            } else {
                $_SESSION['alertMessage'] = "เกิดข้อผิดพลาดในการแก้ไข!";
                $_SESSION['alertType'] = "error";
            }

            $stmt->close();
            header("Location: edit_Tname.php?ID=".$T_ID);
            exit;
        }
    }

    $productId = isset($_POST['Product_ID']) ? $_POST['Product_ID'] : null;
    $productName = $_POST['Product_name'];
    $typeId = $_POST['T_ID'];

    // ตรวจสอบว่าผลิตภัณฑ์นี้มีอยู่ในระบบหรือไม่
    $query = "SELECT COUNT(*) FROM product WHERE Product_name = ? AND T_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $productName, $typeId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0 && !$productId) {
        $_SESSION['alertMessage'] = "ผลิตภัณฑ์นี้มีอยู่แล้วในระบบ";
        $_SESSION['alertType'] = "error";
        header("Location: edit_product.php");
        exit();
    }

    if ($productId) {
        // แก้ไขผลิตภัณฑ์ในฐานข้อมูล
        $query = "UPDATE product SET Product_name = ?, T_ID = ? WHERE Product_ID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sii", $productName, $typeId, $productId);
        $action = "แก้ไข";
    } else {
        // เพิ่มผลิตภัณฑ์ใหม่ในฐานข้อมูล
        $query = "INSERT INTO product (Product_name, T_ID) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $productName, $typeId);
        $action = "เพิ่ม";
    }

    if ($stmt->execute()) {
        $_SESSION['alertMessage'] = "{$action}ผลิตภัณฑ์สำเร็จ";
        $_SESSION['alertType'] = "success";
    } else {
        $_SESSION['alertMessage'] = "ไม่สามารถ{$action}ผลิตภัณฑ์ได้";
        $_SESSION['alertType'] = "error";
    }

    $stmt->close();
    header("Location: edit_product.php");
    exit();
}

// ดึงข้อมูลประเภทสินค้าเพื่อใช้ใน dropdown
$query = "SELECT T_ID, CONCAT(T_Name, ' - Ø', Pipe_Size, 'mm -', Pipe_End, ' - ', degree, '°') AS DisplayText FROM type";
$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

// ดึงข้อมูลชื่อผลิตภัณฑ์จากตาราง add_linkproduct เพื่อใช้ใน dropdown
$productQuery = "SELECT ID, product_name FROM add_linkproduct";
$productResult = $conn->query($productQuery);

if (!$productResult) {
    die("Query failed: " . $conn->error);
}

include 'admin_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add/Edit Product</title>
    <link rel="stylesheet" href="../css/product.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
    <a href="../admin/product.php" class="back-link">ย้อนกลับ</a>
    <div class="add">
        <form action="edit_product.php" method="POST" id="addcustomer">
            <h2>แก้ไขผลิตภัณฑ์</h2>

            <input type="hidden" name="Product_ID" value="<?php echo htmlspecialchars($productId); ?>">

            <div class="form-group">
                <label for="Product_name">ชื่อผลิตภัณฑ์</label>
                <select id="Product_name" name="Product_name" required>
                    <option value="">--เลือกชื่อผลิตภัณฑ์--</option>
                    <?php
                    if ($productResult->num_rows > 0) {
                        while ($row = $productResult->fetch_assoc()) {
                            $selected = ($productName == $row['product_name']) ? 'selected' : ''; // เลือกค่าโดยอัตโนมัติ
                            echo "<option value=\"" . htmlspecialchars($row['product_name']) . "\" {$selected}>" . htmlspecialchars($row['product_name']) . "</option>";
                        }
                    } else {
                        echo "<option value=\"\">ไม่พบข้อมูล</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="T_ID" class="asterisk">ประเภทและขนาดท่อ</label>
                <select id="T_ID" name="T_ID" required>
                    <option value="">--เลือกประเภทและขนาดท่อ--</option>
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $selected = ($typeId == $row['T_ID']) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($row['T_ID']) . "\" {$selected}>" . htmlspecialchars($row['DisplayText']) . "</option>";
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
        <?php
        if (isset($_SESSION['alertMessage'])) {
            $alertType = isset($_SESSION['alertType']) ? $_SESSION['alertType'] : 'info';
            $redirectUrl = $alertType === 'success' ? 'product.php' : 'edit_product.php';
            echo "Swal.fire({
                icon: '{$alertType}',
                title: '{$_SESSION['alertMessage']}',
                showConfirmButton: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '{$redirectUrl}';
                }
            });";
            unset($_SESSION['alertMessage']);
            unset($_SESSION['alertType']);
        }
        ?>
        
        // Initialize Select2 on the dropdowns
        $('#T_ID, #Product_name').select2({
            placeholder: '--เลือก--',
            width: '100%',
            language: {
                noResults: function() {
                    return 'ไม่พบข้อมูล';
                }
            }
        });
    });
    </script>
</body>
</html>
