<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date_time = $_POST['Date_Time'];
    $wo_no = $_POST['WO_No'];
    $product_id = $_POST['Product_ID'];
    $amount = $_POST['Amount'];
    $location = $_POST['Location'];
    $cus_id = $_POST['Cus_ID'];
    $status_id = $_POST['Status_ID'];

    $query = "INSERT INTO stock (Date_Time, WO_No, Product_ID, Amount, Location, Cus_ID, Status_ID)
              VALUES ('$date_time', '$wo_no', '$product_id', '$amount', '$location', '$cus_id', '$status_id')";

    if ($conn->query($query) === TRUE) {
        $_SESSION['alertMessage'] = "เพิ่มข้อมูล stock เรียบร้อยแล้ว";
        header("Location: add_stock.php"); // Reload this page to show the alert
        exit();
    } else {
        $_SESSION['alertMessage'] = "ไม่สามารถเพิ่มข้อมูล stock ได้: " . $conn->error;
        header("Location: add_stock.php"); // Reload this page to show the alert
        exit();
    }
}

// ดึงข้อมูล WO_No จากตาราง orders
$orderSql = "SELECT WO_No FROM orders";
$orderResult = $conn->query($orderSql);

// ดึงข้อมูลลูกค้า (Cus_ID, Cus_Fname, Cus_Lname, Project_Name) จากตาราง customer
$customerSql = "SELECT Cus_ID, Cus_Fname, Cus_Lname, Project_Name FROM customer";
$customerResult = $conn->query($customerSql);

// ดึงข้อมูลผลิตภัณฑ์ (Product_ID, P_Name, และรายละเอียดอื่นๆ) จากตาราง product และ type
$productSql = "SELECT 
        p.Product_ID, 
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.Pipe_Size, ' ', pe.PE_Name, '  ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS ProductDetail 
    FROM 
        product p
    INNER JOIN 
        product_detail pd ON p.P_ID = pd.P_ID
    INNER JOIN 
        type t ON p.T_ID = t.T_ID
    INNER JOIN 
        type_detail td ON t.TD_ID = td.TD_ID
    INNER JOIN 
        pipeend_detail pe ON t.PE_ID = pe.PE_ID";
$productResult = $conn->query($productSql);

// ดึงข้อมูล Status_ID และ Status_Name จากตาราง status
$statusSql = "SELECT Status_ID, Status_Name FROM status";
$statusResult = $conn->query($statusSql);

$conn->close();

include 'admin_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/stock.css">
    <!-- เพิ่มลิงก์ CSS สำหรับ Select2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <!-- เพิ่มลิงก์ JavaScript สำหรับ Select2 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <title>Add Stock</title>
</head>
<body>

<a href="../admin/stock.php" class="back-link">ย้อนกลับ</a>
<form method="POST" action="add_stock.php" id="addcustomer">
    <h2>เพิ่ม Stock</h2>

    <div class="form-group">
        <label for="Date_Time">วันที่:</label>
        <input type="datetime-local" id="Date_Time" name="Date_Time" required>
    </div>

    <div class="form-group">
        <label for="WO_No">W/O:</label>
        <select id="WO_No" name="WO_No" required>
            <option value="">-- เลือก W/O --</option>
            <?php if ($orderResult->num_rows > 0): ?>
                <?php while ($orderRow = $orderResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($orderRow['WO_No']); ?>">
                        <?php echo htmlspecialchars($orderRow['WO_No']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="Product_ID">ผลิตภัณฑ์:</label>
        <select id="Product_ID" name="Product_ID" required>
            <option value="">-- เลือกผลิตภัณฑ์ --</option>
            <?php if ($productResult->num_rows > 0): ?>
                <?php while ($productRow = $productResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($productRow['Product_ID']); ?>">
                        <?php echo htmlspecialchars($productRow['ProductDetail']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="Amount">จำนวน/ชิ้น:</label>
        <input type="number" id="Amount" name="Amount" required>
    </div>

    <div class="form-group">
        <label for="Location">สถานที่เก็บ:</label>
        <input type="text" id="Location" name="Location">
    </div>

    <div class="form-group">
        <label for="Cus_ID">ลูกค้า:</label>
        <select id="Cus_ID" name="Cus_ID" required>
            <option value="">-- เลือกลูกค้า --</option>
            <?php if ($customerResult->num_rows > 0): ?>
                <?php while ($customerRow = $customerResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($customerRow['Cus_ID']); ?>">
                        <?php echo htmlspecialchars($customerRow['Cus_Fname'] . ' ' . $customerRow['Cus_Lname'] . ' - ' . $customerRow['Project_Name']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="Status_ID">สถานะ:</label>
        <select id="Status_ID" name="Status_ID" required>
            <option value="">-- เลือกสถานะ --</option>
            <?php if ($statusResult->num_rows > 0): ?>
                <?php while ($statusRow = $statusResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($statusRow['Status_ID']); ?>">
                        <?php echo htmlspecialchars($statusRow['Status_Name']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="footer">
        <button type="submit" class="approve">เพิ่ม</button>
        <button type="reset" class="delete">ยกเลิก</button>
    </div>
</form>

<script>
$(document).ready(function() {
    // เริ่มต้น Select2 สำหรับแต่ละ dropdown
    $('#Product_ID').select2({
        placeholder: '-- เลือกผลิตภัณฑ์ --',
        allowClear: true
    });

    $('#WO_No').select2({
        placeholder: '-- เลือก W/O --',
        allowClear: true
    });

    $('#Cus_ID').select2({
        placeholder: '-- เลือกลูกค้า --',
        allowClear: true
    });

    $('#Status_ID').select2({
        placeholder: '-- เลือกสถานะ --',
        allowClear: true
    });

    <?php if (isset($_SESSION['alertMessage'])): ?>
        Swal.fire({
            icon: 'success',
            title: '<?php echo $_SESSION['alertMessage']; ?>',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'stock.php';
            }
        });
        <?php unset($_SESSION['alertMessage']); ?>
    <?php endif; ?>
});
</script>

</body>
</html>
