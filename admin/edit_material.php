<?php
require_once '../db.php';
session_start();

$raw_id = $_GET['Raw_ID'];

if (isset($_POST['edit_material'])) {
    $raw_id = $_POST['raw_id'];
    $raw_name = $_POST['raw_name'];
    $coil_no = $_POST['coil_no'];
    $amount = $_POST['amount'];
    $price = $_POST['price'];
    $date_received = $_POST['date_received'];
    $sup_id = $_POST['sup_id'];

    // Fetch existing data
    $stmt = $conn->prepare("SELECT * FROM raw_material WHERE Raw_ID=?");
    $stmt->bind_param('i', $raw_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_data = $result->fetch_assoc();
    $stmt->close();

    // Check if any data has changed
    if ($raw_name != $existing_data['Raw_Name'] || $coil_no != $existing_data['Coil_No'] || $amount != $existing_data['Amount'] || $price != $existing_data['Price'] || $date_received != $existing_data['Date_Recevied'] || $sup_id != $existing_data['Sup_ID']) {
        $sql = "UPDATE raw_material SET Raw_Name=?, Coil_No=?, Amount=?, Price=?, Date_Recevied=?, Sup_ID=? WHERE Raw_ID=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssidsii', $raw_name, $coil_no, $amount, $price, $date_received, $sup_id, $raw_id);

        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "แก้ไขข้อมูล Stock วัตถุดิบ เรียบร้อยแล้ว";
            $_SESSION['alertType'] = "success";
        } else {
            $_SESSION['alertMessage'] = "ไม่สามารถแก้ไขข้อมูล Stock วัตถุดิบได้";
            $_SESSION['alertType'] = "error";
        }
        $stmt->close();
        header("Location: edit_material.php?Raw_ID=$raw_id");
        exit;
    }
}

if (isset($_GET['Raw_ID'])) {
    $raw_id = $_GET['Raw_ID'];
    $sql = "SELECT * FROM raw_material WHERE Raw_ID=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $raw_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $raw_material = $result->fetch_assoc();
    $stmt->close();

    // Fetch suppliers for the dropdown
    $supplier_sql = "SELECT * FROM supplier";
    $supplier_result = $conn->query($supplier_sql);
    $suppliers = [];
    if ($supplier_result->num_rows > 0) {
        while ($supplier = $supplier_result->fetch_assoc()) {
            $suppliers[] = $supplier;
        }
    }
}

$conn->close();
include 'admin_index.html';
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
    <title>แก้ไขวัตถุดิบ</title>
</head>
<body>
<a href="../admin/raw_material.php" class="back-link">ย้อนกลับ</a>
<form method="POST" action="edit_material.php" id="addcustomer">
    <h2>แก้ไขวัตถุดิบ</h2>
    <input type="hidden" name="raw_id" value="<?php echo htmlspecialchars($raw_material['Raw_ID']); ?>">

    <div class="form-group">
        <label for="raw_name">ชื่อวัตถุดิบ:</label>
        <input type="text" id="raw_name" name="raw_name" value="<?php echo htmlspecialchars($raw_material['Raw_Name']); ?>" required><br>
    </div>
    
    <div class="form-group">
        <label for="coil_no">เลขล็อตคอยล์:</label>
        <input type="text" id="coil_no" name="coil_no" value="<?php echo htmlspecialchars($raw_material['Coil_No']); ?>" required><br>
    </div>

    <div class="form-group">
        <label for="amount">จำนวน/ชิ้น:</label>
        <input type="number" id="amount" name="amount" value="<?php echo htmlspecialchars($raw_material['Amount']); ?>" required><br>
    </div>

    <div class="form-group">
        <label for="price">ราคา/ชิ้น:</label>
        <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($raw_material['Price']); ?>" required><br>
    </div>

    <div class="form-group">
        <label for="date_received">วันที่รับวัตถุดิบ:</label>
        <input type="datetime-local" id="date_received" name="date_received" value="<?php echo htmlspecialchars($raw_material['Date_Recevied']); ?>" required><br>
    </div>
    
    <div class="form-group">
        <label for="sup_id">ผู้ผลิตวัตถุดิบ:</label>
        <select id="sup_id" name="sup_id" required>
            <option value="">--เลือกผู้ผลิตวัตถุดิบ--</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?php echo htmlspecialchars($supplier['Sup_ID']); ?>" <?php echo ($supplier['Sup_ID'] == $raw_material['Sup_ID']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($supplier['Sup_Name']) . " - " . htmlspecialchars($supplier['Sup_Address']); ?>
                </option>
            <?php endforeach; ?>
        </select><br>
    </div>

    <div class="footer">
        <button type="submit" class="approve" name="edit_material">บันทึก</button>
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
