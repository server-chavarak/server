<?php
require_once '../db.php';
session_start();

// ตรวจสอบว่ามีการส่งค่า Stock_ID มาหรือไม่
if (!isset($_GET['Stock_ID']) || empty($_GET['Stock_ID'])) {
    $_SESSION['alertMessage'] = "Stock ID is required.";
    header("Location: ../manager/stock.php"); // เปลี่ยนเส้นทางไปยังหน้า stock.php
    exit();
}

$stock_id = $_GET['Stock_ID'];

// ดึงข้อมูลเดิมของ stock จากฐานข้อมูล
$sql = "SELECT * FROM stock WHERE Stock_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $stock_id);
$stmt->execute();
$result = $stmt->get_result();
$stock = $result->fetch_assoc();

if (!$stock) {
    $_SESSION['alertMessage'] = "Stock not found.";
    header("Location: ../manager/stock.php"); // เปลี่ยนเส้นทางไปยังหน้า stock.php
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['Amount'];
    $location = $_POST['Location'];

    // อัปเดตข้อมูลในฐานข้อมูล
    $query = "UPDATE stock SET Amount = ?, Location = ? WHERE Stock_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('dsi', $amount, $location, $stock_id);

    if ($stmt->execute()) {
        $_SESSION['alertMessage'] = "แก้ไขข้อมูล stock เรียบร้อยแล้ว";
        header("Location: edit_stock.php?Stock_ID=" . $stock_id); // Reload the page to show the alert
        exit();
    } else {
        $_SESSION['alertMessage'] = "ไม่สามารถแก้ไขข้อมูล stock ได้: " . $stmt->error;
        header("Location: edit_stock.php?Stock_ID=" . $stock_id); // Reload the page to show the alert
        exit();
    }
}

// ดึงข้อมูลที่ต้องการใช้ในการแสดงผล
$orderSql = "SELECT WO_No FROM orders";
$orderResult = $conn->query($orderSql);

$customerSql = "SELECT Cus_ID, CONCAT(Cus_Fname, ' ', Cus_Lname, ' - ', Project_Name) AS CustomerDetail FROM customer";
$customerResult = $conn->query($customerSql);

$productSql = "SELECT 
        p.Product_ID,
        pd.P_Name,  -- ดึงชื่อผลิตภัณฑ์
        t.T_ID,
        td.TD_Name,  -- ดึงชื่อประเภท
        t.PE_ID,
        pe.PE_Name,  -- ดึงชื่อปลายท่อ
        t.Pipe_Size,
        t.degree
    FROM 
        product p 
    JOIN 
        product_detail pd ON p.P_ID = pd.P_ID
    JOIN 
        type t ON p.T_ID = t.T_ID
    JOIN 
        type_detail td ON t.TD_ID = td.TD_ID
    JOIN 
        pipeend_detail pe ON t.PE_ID = pe.PE_ID";

$productResult = $conn->query($productSql);

if ($productResult === false) {
    die('Error fetching products: ' . htmlspecialchars($conn->error));
}


$statusSql = "SELECT Status_ID, Status_Name FROM status";
$statusResult = $conn->query($statusSql);

$conn->close();

include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/stock.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <title>Edit Stock</title>
</head>
<body>

<a href="../manager/stock.php" class="back-link">ย้อนกลับ</a>
<form method="POST" action="edit_stock.php?Stock_ID=<?php echo htmlspecialchars($stock_id); ?>" id="addcustomer">
    <h2>แก้ไข Stock</h2>

    <div class="form-group">
        <label for="Date_Time">วันที่:</label>
        <input type="datetime-local" id="Date_Time" name="Date_Time" value="<?php echo htmlspecialchars($stock['Date_Time']); ?>" readonly>
    </div>

    <div class="form-group">
        <label for="WO_No">W/O:</label>
        <select id="WO_No" name="WO_No" disabled>
            <option value="<?php echo htmlspecialchars($stock['WO_No']); ?>" selected><?php echo htmlspecialchars($stock['WO_No']); ?></option>
        </select>
    </div>

    <div class="form-group">
    <label for="Product_ID">ผลิตภัณฑ์:</label>
    <select id="Product_ID" name="Product_ID">
        <option value="">--เลือกผลิตภัณฑ์--</option>
        <?php foreach ($products as $product): ?>
            <option value="<?php echo htmlspecialchars($product['Product_ID']); ?>">
                <?php 
                echo htmlspecialchars(
                    $product['P_Name'] . ' - ' . 
                    $product['TD_Name'] . ' - ' . 
                    ' Ø ' . $product['Pipe_Size'] . 'mm. - ' . 
                    $product['PE_Name'] . 
                    (!empty($product['degree']) ? ' - ' . $product['degree'] . ' องศา' : '')
                ); 
                ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>





    <div class="form-group">
        <label for="Amount">จำนวน/ชิ้น:</label>
        <input type="number" id="Amount" name="Amount" value="<?php echo htmlspecialchars($stock['Amount']); ?>" required>
    </div>

    <div class="form-group">
        <label for="Location">สถานที่เก็บ:</label>
        <input type="text" id="Location" name="Location" value="<?php echo htmlspecialchars($stock['Location']); ?>">
    </div>

    <div class="form-group">
        <label for="Cus_ID">ลูกค้า:</label>
        <select id="Cus_ID" name="Cus_ID" disabled>
            <?php if ($customerResult->num_rows > 0): ?>
                <?php while ($customerRow = $customerResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($customerRow['Cus_ID']); ?>" <?php echo ($customerRow['Cus_ID'] == $stock['Cus_ID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($customerRow['CustomerDetail']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="Status_ID">สถานะ:</label>
        <select id="Status_ID" name="Status_ID" disabled>
            <?php if ($statusResult->num_rows > 0): ?>
                <?php while ($statusRow = $statusResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($statusRow['Status_ID']); ?>" <?php echo ($statusRow['Status_ID'] == $stock['Status_ID']) ? 'selected' : ''; ?>>
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
    $('#Product_ID, #WO_No, #Cus_ID, #Status_ID').select2({
        disabled: true
    });

    <?php if (isset($_SESSION['alertMessage'])): ?>
        Swal.fire({
            icon: 'info',
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
