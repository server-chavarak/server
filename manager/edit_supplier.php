<?php
require_once '../db.php';
session_start();

$Sup_ID = $_GET['Sup_ID'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $Sup_Name = $_POST['Sup_Name'];
    $Sup_Address = $_POST['Sup_Address'];
    $Email = $_POST['Email'];
    $Tell = $_POST['Tell'];

    // ดึงข้อมูลเดิมจากฐานข้อมูล
    $stmt = $conn->prepare("SELECT * FROM supplier WHERE Sup_ID = ?");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("i", $Sup_ID);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();

    $stmt->close();

    // ตรวจสอบว่ามีการเปลี่ยนแปลงข้อมูลหรือไม่
    if ($Sup_Name != $supplier['Sup_Name'] || $Sup_Address != $supplier['Sup_Address'] || $Email != $supplier['Email'] || $Tell != $supplier['Tell']) {
        $stmt = $conn->prepare("UPDATE supplier SET Sup_Name = ?, Sup_Address = ?, Email = ?, Tell = ? WHERE Sup_ID = ?");
        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }
        $stmt->bind_param("ssssi", $Sup_Name, $Sup_Address, $Email, $Tell, $Sup_ID);

        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "แก้ไขข้อมูลผู้ผลิตเรียบร้อยแล้ว";
            $_SESSION['alertType'] = "success";
        } else {
            $_SESSION['alertMessage'] = "ไม่สามารถแก้ไขข้อมูลผู้ผลิตได้";
            $_SESSION['alertType'] = "error";
        }

        $stmt->close();
        header("Location: edit_supplier.php?Sup_ID=$Sup_ID");
        exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM supplier WHERE Sup_ID = ?");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $Sup_ID);
$stmt->execute();
$result = $stmt->get_result();
$supplier = $result->fetch_assoc();

$stmt->close();
$conn->close();

include 'manager_index.html';
?>


<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/supplier.css">
    <title>แก้ไขข้อมูลผู้ผลิต</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body></body>
<a href="../manager/supplier.php" class="back-link">ย้อนกลับ</a>
<form method="POST" action="edit_supplier.php?Sup_ID=<?php echo htmlspecialchars($Sup_ID); ?>" id="addcustomer">
    <h2>แก้ไขข้อมูลผู้ผลิต</h2>

    <div class="form-group">
        <label for="Sup_Name">ชื่อผู้ผลิต:</label>
        <input type="text" id="Sup_Name" name="Sup_Name" value="<?php echo htmlspecialchars($supplier['Sup_Name']); ?>" required>
    </div>

    <div class="form-group">
        <label for="Sup_Address">ที่อยู่:</label>
        <input type="text" id="Sup_Address" name="Sup_Address" value="<?php echo htmlspecialchars($supplier['Sup_Address']); ?>" required>
    </div>

    <div class="form-group">
        <label for="Email">อีเมล:</label>
        <input type="email" id="Email" name="Email" value="<?php echo htmlspecialchars($supplier['Email']); ?>" required>
    </div>

    <div class="form-group">
        <label for="Tell">เบอร์โทร:</label>
        <input type="text" id="Tell" name="Tell" value="<?php echo htmlspecialchars($supplier['Tell']); ?>" required>
    </div>

    <div class="footer">
        <button type="submit" class="approve">บันทึก</button>
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
