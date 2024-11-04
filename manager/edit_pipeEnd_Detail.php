<?php
session_start();
require_once '../db.php';

// ตรวจสอบว่ามีการส่ง PE_ID มาหรือไม่
$PipeendId = isset($_GET['PE_ID']) ? $_GET['PE_ID'] : null;
$PipeendName = '';

// ดึงข้อมูลลักษณะปลายท่อสำหรับการแก้ไข
if ($PipeendId) {
    $sql = "SELECT PE_ID, PE_Name FROM pipeend_detail WHERE PE_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $PipeendId);
    $stmt->execute();
    $stmt->bind_result($PipeendId, $PipeendName);
    $stmt->fetch();
    $stmt->close();
    
}

// โค้ดอัปเดตข้อมูลในฐานข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $PipeendId = isset($_POST['PE_ID']) ? $_POST['PE_ID'] : null;  // ดึงค่า PE_ID จากฟอร์ม
    $PipeendName = $_POST['PE_Name'];  // ดึงค่า PE_Name จากฟอร์ม

    // ตรวจสอบว่าชื่อลักษณะปลายท่อซ้ำหรือไม่ (ยกเว้นลักษณะปลายท่อที่กำลังแก้ไข)
    $checkQuery = "SELECT PE_ID FROM pipeend_detail WHERE PE_Name = ? AND PE_ID != ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("si", $PipeendName, $PipeendId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // ถ้าข้อมูลซ้ำ
        $_SESSION['alertMessage'] = "ไม่สามารถบันทึกได้ ชื่อลักษณะปลายท่อนี้มีอยู่แล้ว";
        $_SESSION['alertType'] = "error";
    } else {
        // ถ้าข้อมูลไม่ซ้ำ ทำการแก้ไขข้อมูล
        $stmt->close();
        $query = "UPDATE pipeend_detail SET PE_Name = ? WHERE PE_ID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $PipeendName, $PipeendId);
      
        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "แก้ไขข้อมูลลักษณะปลายท่อสำเร็จ";
            $_SESSION['alertType'] = "success";
        } else {
            $_SESSION['alertMessage'] = "ไม่สามารถแก้ไขข้อมูลลักษณะปลายท่อได้";
            $_SESSION['alertType'] = "error";
        }
    }
    $stmt->close();
    header("Location: edit_Pipeend_detail.php?PE_ID=" . $PipeendId);
    exit();
}

// ดึงข้อมูลลักษณะปลายท่อเพื่อใช้ในฟอร์ม
$query = "SELECT PE_ID, PE_Name FROM pipeend_detail";
$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

include 'manager_index.html';
?>



<!DOCType html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pipeend</title>
    <link rel="stylesheet" href="../css/type.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
    <a href="../manager/add_Pipeend_detail.php" class="back-link">ย้อนกลับ</a>
    <div class="edit">
        <form action="edit_Pipeend_detail.php" method="POST" id="editPipeendForm">
            <h2>แก้ไขลักษณะปลายท่อ</h2>

            <input type="hidden" name="PE_ID" value="<?php echo htmlspecialchars($PipeendId); ?>">

            <div class="form-group">
                <label for="PE_Name">ลักษณะปลายท่อ</label>
                <input type="text" id="PE_Name" name="PE_Name" value="<?php echo htmlspecialchars($PipeendName); ?>" required>
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
                    window.location.href = 'add_Pipeend_detail.php';
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
