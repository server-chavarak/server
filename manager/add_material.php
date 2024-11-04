<?php
require_once '../db.php';
session_start();

// ดึงข้อมูลผู้ผลิตทั้งหมด
$sql = "SELECT Sup_ID, Sup_Name, Sup_Address FROM supplier";
$result = $conn->query($sql);

$suppliers = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

if (isset($_POST['add_material'])) {
    // ดึงข้อมูลจากฟอร์ม
    $raw_name = $_POST['raw_name'];
    $coil_no = $_POST['coil_no'];
    $amount = $_POST['amount'];
    $price = $_POST['price'];
    $date_received = $_POST['date_received']; // รับวันที่ในรูปแบบ datetime-local
    $sup_id = $_POST['sup_id'];

    // เตรียมคำสั่ง SQL สำหรับการเพิ่มข้อมูล
    $sql = "INSERT INTO raw_material (Raw_Name, Coil_No, Amount, Price, Date_Recevied, Sup_ID) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    // ผูกค่าพารามิเตอร์กับคำสั่ง SQL
    $stmt->bind_param("ssddsi", $raw_name, $coil_no, $amount, $price, $date_received, $sup_id);

    // ดำเนินการคำสั่ง SQL และจัดการข้อผิดพลาด
    if ($stmt->execute()) {
        $_SESSION['alertMessage'] = "เพิ่มข้อมูล Stock วัตถุดิบ เรียบร้อยแล้ว";
        $_SESSION['alertType'] = "success";
    } else {
        $_SESSION['alertMessage'] = "ไม่สามารถเพิ่มข้อมูล Stock วัตถุดิบได้: " . htmlspecialchars($stmt->error);
        $_SESSION['alertType'] = "error";
    }

    $stmt->close();
    header("Location: add_material.php");
    exit;
}

include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/material.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> 
    <title>เพิ่มวัตถุดิบ</title>
</head>
<body>
<a href="../manager/raw_material.php" class="back-link">ย้อนกลับ</a>
    <form method="POST" action="add_material.php" id="addcustomer">
    <h2>เพิ่มวัตถุดิบ</h2>

    <div class="form-group">
        <label for="raw_name">ชื่อวัตถุดิบ:</label>
        <input type="text" id="raw_name" name="raw_name" required><br>
    </div>

    <div class="form-group">
        <label for="coil_no">เลขล็อตคอยล์:</label>
        <input type="text" id="coil_no" name="coil_no" required><br>
    </div>

    <div class="form-group">
        <label for="amount">จำนวน/ชิ้น:</label>
        <input type="number" id="amount" name="amount" required><br>
    </div>

    <div class="form-group">
        <label for="price">ราคา/ชิ้น:</label>
        <input type="number" id="price" name="price" required><br>
    </div>

    <div class="form-group">
        <label for="date_received">วันที่รับวัตถุดิบ:</label>
        <input type="datetime-local" id="date_received" name="date_received" required><br>
    </div>

    <div class="form-group">
        <label for="sup_id">ผู้ผลิตวัตถุดิบ:</label>
        <select id="sup_id" name="sup_id" required>
            <option value="">--เลือกผู้ผลิตวัตถุดิบ--</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?php echo htmlspecialchars($supplier['Sup_ID']); ?>">
                    <?php echo htmlspecialchars($supplier['Sup_Name']) . " - " . htmlspecialchars($supplier['Sup_Address']); ?>
                </option>
            <?php endforeach; ?>
        </select><br>
    </div>

    <div class="footer">
        <button type="submit" class="approve" name="add_material">เพิ่ม</button>
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
                    window.location.href = 'raw_material.php';
                }
            });
        });
    </script>
    <?php unset($_SESSION['alertMessage']); unset($_SESSION['alertType']); endif; ?>
</body>
</html>
