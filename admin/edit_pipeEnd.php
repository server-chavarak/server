<?php
// เชื่อมต่อกับฐานข้อมูล
require_once '../db.php';
session_start(); // เริ่มต้น session เพื่อเก็บข้อความแจ้งเตือน

// ตรวจสอบว่ามีการส่งค่า ID มาเพื่อแก้ไขข้อมูลหรือไม่
$pipeEnd = null;
if (isset($_GET['ID'])) {
    $pipeEndID = $_GET['ID'];
    $stmt = $conn->prepare("SELECT * FROM add_pipeend WHERE ID = ?");
    $stmt->bind_param("i", $pipeEndID);
    $stmt->execute();
    $result = $stmt->get_result();
    $pipeEnd = $result->fetch_assoc();
    $stmt->close();

    // Debugging: Check if data is fetched
    if ($pipeEnd) {
        echo "<script>console.log('Data fetched: " . json_encode($pipeEnd) . "');</script>";
    } else {
        echo "<script>console.log('No data found for ID: $pipeEndID');</script>";
    }
}

// โค้ดอัปเดตข้อมูลในฐานข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $pipeEndID = $_POST['ID']; // รับค่า ID จากฟอร์มที่ถูกซ่อนไว้
    $pipeEndName = $_POST['Name_pipeEnd']; // รับค่า Name_pipeEnd จากฟอร์ม

    if (!empty($pipeEndName)) {
        $stmt = $conn->prepare("UPDATE add_pipeend SET Name_pipeEnd = ? WHERE ID = ?");
        $stmt->bind_param("si", $pipeEndName, $pipeEndID);

        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "แก้ไขลักษณะปลายท่อสำเร็จ!";
            $_SESSION['alertType'] = "success";
        } else {
            $_SESSION['alertMessage'] = "เกิดข้อผิดพลาดในการแก้ไข!";
            $_SESSION['alertType'] = "error";
        }

        $stmt->close();
        header("Location: edit_pipeEnd.php?ID=" . $pipeEndID);
        exit;
    }
}

// โค้ดลบข้อมูลจากฐานข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $pipeEndID = $_POST['ID'];

    $stmt = $conn->prepare("DELETE FROM add_pipeend WHERE ID = ?");
    $stmt->bind_param("i", $pipeEndID);

    if ($stmt->execute()) {
        $_SESSION['alertMessage'] = "ลบลักษณะปลายท่อสำเร็จ!";
        $_SESSION['alertType'] = "success";
    } else {
        $_SESSION['alertMessage'] = "เกิดข้อผิดพลาดในการลบ!";
        $_SESSION['alertType'] = "error";
    }

    $stmt->close();
    header("Location: add_link_pipeEnd.php");
    exit;
}

include 'admin_index.html';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pipe End</title>
    <link rel="stylesheet" href="../css/type.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
</head>
<body>
    <a href="../admin/add_link_pipeEnd.php" class="back-link">ย้อนกลับ</a>
    <div class="edit">
        <form id="editTypeForm" action="edit_pipeEnd.php?ID=<?php echo htmlspecialchars($pipeEnd['ID']); ?>" method="post">
            <input type="hidden" name="ID" value="<?php echo htmlspecialchars($pipeEnd['ID']); ?>">

            <h2>แก้ไขลักษณะปลายท่อ</h2>

            <div class="form-group">
                <label for="typeName">ลักษณะปลายท่อ</label>
                <input type="text" id="pipeEndName" name="Name_pipeEnd" value="<?php echo htmlspecialchars($pipeEnd['Name_pipeEnd']); ?>" required>
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
                        window.location.href = '../admin/add_link_pipeEnd.php';
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
