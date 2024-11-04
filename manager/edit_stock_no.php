<?php
require_once '../db.php'; // เชื่อมต่อฐานข้อมูล
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์มเพื่อแก้ไข
    $stock_no_id = $_POST['StockNo_ID'];
    $amount = $_POST['Amount'];
    $location = $_POST['Location'];

    // สร้าง SQL สำหรับการอัปเดตข้อมูล
    $sql = "UPDATE stock_no_order SET Amount = ?, Location = ? WHERE StockNo_ID = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('dsi', $amount, $location, $stock_no_id);

    if ($stmt->execute()) {
        $_SESSION['alertMessage'] = "แก้ไขข้อมูล stock เรียบร้อยแล้ว";
    } else {
        $_SESSION['alertMessage'] = "ไม่สามารถแก้ไขข้อมูล stock ได้: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    // Redirect to the same page to process the alert message
    header('Location: edit_stock_no.php?StockNo_ID=' . $stock_no_id);
    exit();
}

// รับค่า StockNo_ID จาก URL
$stock_no_id = isset($_GET['StockNo_ID']) ? $_GET['StockNo_ID'] : '';

// ดึงข้อมูลผลิตภัณฑ์เพื่อแสดงข้อมูล
$sql = "SELECT sno.StockNo_ID, sno.Product_ID, sno.Amount, sno.Location, sno.Date_Time,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.pipe_size, ' ', pe.PE_Name ,'  ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS display_name
        FROM stock_no_order sno
        INNER JOIN product p ON sno.Product_ID = p.Product_ID
        INNER JOIN product_detail pd ON p.P_ID = pd.P_ID
        INNER JOIN type t ON p.T_ID = t.T_ID
        INNER JOIN type_detail td ON t.TD_ID = td.TD_ID
        INNER JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
        WHERE sno.StockNo_ID = ?";


$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $stock_no_id);
$stmt->execute();
$result = $stmt->get_result();
$stock_no_order = $result->fetch_assoc();

$stmt->close();
include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/stock_no.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>แก้ไข Stock ที่ไม่มีเจ้าของ</title>
</head>
<body>
<a href="../manager/stock_no.php" class="back-link">ย้อนกลับ</a>
    <form method="POST" action="" id="addcustomer">
    <h2>แก้ไข Stock ที่ไม่มีเจ้าของ</h2>

    <!-- แสดงฟิลด์ที่ไม่สามารถแก้ไขได้ -->
    <input type="hidden" name="StockNo_ID" value="<?php echo htmlspecialchars($stock_no_order['StockNo_ID']); ?>">

    <div class="form-group">
        <label for="Date_Time">วันที่:</label>
        <input type="text" name="Date_Time" value="<?php echo htmlspecialchars($stock_no_order['Date_Time']); ?>" readonly>
    </div>

    <div class="form-group">
        <label for="Product_ID">ผลิตภัณฑ์:</label>
        <input type="text" value="<?php echo htmlspecialchars($stock_no_order['display_name']); ?>" readonly>
    </div>

    <!-- ฟิลด์ที่สามารถแก้ไขได้ -->
    <div class="form-group">
        <label for="Amount">จำนวน:</label>
        <input type="number" name="Amount" value="<?php echo htmlspecialchars($stock_no_order['Amount']); ?>" required>
    </div>

    <div class="form-group">  
        <label for="Location">สถานที่เก็บ:</label>
        <input type="text" name="Location" value="<?php echo htmlspecialchars($stock_no_order['Location']); ?>" required>
    </div>

    <div class="footer">
        <button type="submit" class="approve">แก้ไข</button>
        <button type="reset" class="delete">ยกเลิก</button>
    </div>
    </form>
    
    <script>
    $(document).ready(function() {
        <?php
        if (isset($_SESSION['alertMessage'])) {
            echo "Swal.fire({
                icon: 'success',
                title: '" . $_SESSION['alertMessage'] . "',
                confirmButtonText: 'ตกลง'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'stock_no.php';
                }
            });";
            unset($_SESSION['alertMessage']);
        }
        ?>
    });
    </script>

</body>
</html>
