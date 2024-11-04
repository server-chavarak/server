<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $Sup_Name = $_POST['Sup_Name'];
    $Sup_Address = $_POST['Sup_Address'];
    $Email = $_POST['Email'];
    $Tell = $_POST['Tell'];

    $sql = "INSERT INTO supplier (Sup_Name, Sup_Address, Email, Tell) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("ssss", $Sup_Name, $Sup_Address, $Email, $Tell);

    if ($stmt->execute()) {
        $_SESSION['alertMessage'] = "เพิ่มข้อมูลผู้ผลิตเรียบร้อยแล้ว";
        $_SESSION['alertType'] = "success";
    } else {
        $_SESSION['alertMessage'] = "ไม่สามารถเพิ่มข้อมูลผู้ผลิตได้";
        $_SESSION['alertType'] = "error";
    }

    $stmt->close();
    header("Location: add_supplier.php");
    exit;
}

include 'admin_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/supplier.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>เพิ่มผู้ผลิต</title>
</head>
<body>
<a href="../admin/supplier.php" class="back-link">ย้อนกลับ</a>
<form method="POST" action="add_supplier.php" id="addcustomer">
<h2>เพิ่มผู้ผลิตใหม่</h2>

<div class="form-group">
    <label for="Sup_Name">ชื่อผู้ผลิต:</label>
    <input type="text" id="Sup_Name" name="Sup_Name" required>
</div>

<div class="form-group">
    <label for="Sup_Address">ที่อยู่:</label>
    <input type="text" id="Sup_Address" name="Sup_Address" required>
</div>

<div class="form-group">
    <label for="Email">อีเมล:</label>
    <input type="email" id="Email" name="Email" required>
</div>

<div class="form-group">
    <label for="Tell">เบอร์โทร:</label>
    <input type="text" id="Tell" name="Tell" required>
</div>

<div class="footer">
    <button type="submit" class="approve">เพิ่ม</button>
    <button type="reset" class="delete">ยกเลิก</button>
</div>
</form>

<?php if (isset($_SESSION['alertMessage'])): ?>
<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: '<?php echo $_SESSION['alertMessage']; ?>',
            icon: '<?php echo $_SESSION['alertType']; ?>',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'supplier.php';
            }
        });
    });
</script>
<?php unset($_SESSION['alertMessage']); unset($_SESSION['alertType']); endif; ?>
</body>
</html>
