<?php
session_start();
require_once '../db.php';

// ตรวจสอบว่ามีการส่ง TD_ID มาหรือไม่
$typeId = isset($_GET['TD_ID']) ? $_GET['TD_ID'] : null;
$typeName = '';

// ดึงข้อมูลประเภทท่อสำหรับการแก้ไข
if ($typeId) {
    $sql = "SELECT TD_ID, TD_Name FROM Type_Detail WHERE TD_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $typeId);
    $stmt->execute();
    $stmt->bind_result($typeId, $typeName);
    $stmt->fetch();
    $stmt->close();
}

// โค้ดอัปเดตข้อมูลในฐานข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typeId = isset($_POST['TD_ID']) ? $_POST['TD_ID'] : null;  // ดึงค่า TD_ID จากฟอร์ม
    $typeName = $_POST['TD_Name'];  // ดึงค่า TD_Name จากฟอร์ม

    // ตรวจสอบว่าชื่อประเภทท่อซ้ำหรือไม่ (ยกเว้นประเภทท่อที่กำลังแก้ไข)
    $checkQuery = "SELECT TD_ID FROM Type_Detail WHERE TD_Name = ? AND TD_ID != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("si", $typeName, $typeId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // ถ้าข้อมูลซ้ำ
        $_SESSION['alertMessage'] = "ไม่สามารถบันทึกได้ ชื่อประเภทท่อนี้มีอยู่แล้ว";
        $_SESSION['alertType'] = "error";
    } else {
        // ถ้าข้อมูลไม่ซ้ำ ทำการแก้ไขข้อมูล
        $stmt->close();
        $query = "UPDATE Type_Detail SET TD_Name = ? WHERE TD_ID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $typeName, $typeId);
      
        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "แก้ไขข้อมูลประเภทท่อสำเร็จ";
            $_SESSION['alertType'] = "success";
        } else {
            $_SESSION['alertMessage'] = "ไม่สามารถแก้ไขข้อมูลประเภทท่อได้";
            $_SESSION['alertType'] = "error";
        }
    }
    $stmt->close();
    header("Location: edit_type_detail.php?TD_ID=" . $typeId);
    exit();
}

// ดึงข้อมูลประเภทท่อเพื่อใช้ในฟอร์ม
$query = "SELECT TD_ID, TD_Name FROM Type_Detail";
$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

include 'admin_index.html';
?>



<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pipe Type</title>
    <link rel="stylesheet" href="../css/type.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
    <a href="../admin/add_type_detail.php" class="back-link">ย้อนกลับ</a>
    <div class="edit">
        <form action="edit_type_detail.php" method="POST" id="editTypeForm">
            <h2>แก้ไขประเภทท่อ</h2>

            <input type="hidden" name="TD_ID" value="<?php echo htmlspecialchars($typeId); ?>">

            <div class="form-group">
                <label for="TD_Name">ชื่อประเภทท่อ</label>
                <input type="text" id="TD_Name" name="TD_Name" value="<?php echo htmlspecialchars($typeName); ?>" required>
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
            echo "Swal.fire({
                icon: '{$alertType}',
                title: '{$_SESSION['alertMessage']}',
                showConfirmButton: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'add_type_detail.php';
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
