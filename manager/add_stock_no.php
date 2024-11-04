<?php
require_once '../db.php'; // เชื่อมต่อฐานข้อมูล
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $product_id = $_POST['Product_ID'];
    $amount = $_POST['Amount'];
    $location = $_POST['Location'];
    $date_time = $_POST['Date_Time']; // รับค่าจาก input วันที่ที่ผู้ใช้กรอก

    // สร้าง SQL สำหรับการเพิ่มข้อมูล
    $sql = "INSERT INTO stock_no_order (Product_ID, Amount, Location, Date_Time) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('iiss', $product_id, $amount, $location, $date_time);

    if ($stmt->execute()) {
        $_SESSION['alertMessage'] = "เพิ่มข้อมูล stock เรียบร้อยแล้ว";
    } else {
        $_SESSION['alertMessage'] = "ไม่สามารถเพิ่มข้อมูล stock ได้: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    // Redirect to the same page to process the alert message
    header('Location: add_stock_no.php');
    exit();
}

// ดึงข้อมูลผลิตภัณฑ์เพื่อสร้าง dropdown
$product_sql = "SELECT DISTINCT p.Product_ID, 
           CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.pipe_size, ' ', pe.PE_Name, '  ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS display_name
    FROM product p
    INNER JOIN product_detail pd ON p.P_ID = pd.P_ID
    INNER JOIN type t ON p.T_ID = t.T_ID
    INNER JOIN type_detail td ON t.TD_ID = td.TD_ID
    INNER JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID;
";


$product_result = mysqli_query($conn, $product_sql);

if (!$product_result) {
    die('Query failed: ' . mysqli_error($conn));
}

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
    <title>เพิ่ม Stock ที่ไม่มีเจ้าของ</title>
</head>
<body>
<a href="../manager/stock_no.php" class="back-link">ย้อนกลับ</a>
    <form method="POST" action="" id="addcustomer">
    <h2>เพิ่ม Stock ที่ไม่มีเจ้าของ</h2>

    <div class="form-group">
        <label for="Date_Time">วันที่:</label>
        <input type="date" name="Date_Time" required>
    </div>

    <div class="form-group">
        <label for="Product_ID">ผลิตภัณฑ์:</label>
        <select id="Product_ID" name="Product_ID" style="width: 100%;">
            <option value="">--เลือกผลิตภัณฑ์--</option>
            <?php 
            // สร้าง dropdown ของผลิตภัณฑ์
            if ($product_result) {
                while ($product = mysqli_fetch_assoc($product_result)) {
                    echo '<option value="' . htmlspecialchars($product['Product_ID']) . '">';
                    echo htmlspecialchars($product['display_name']);
                    echo '</option>';
                }
            } else {
                echo '<option value="">ไม่พบผลิตภัณฑ์</option>';
            }
            ?>
        </select>
    </div>

    <div class="form-group">
        <label for="Amount">จำนวน:</label>
        <input type="number" name="Amount" required>
    </div>

    <div class="form-group">  
        <label for="Location">สถานที่เก็บ:</label>
        <input type="text" name="Location" >
    </div>

    <div class="footer">
        <button type="submit" class="approve">เพิ่ม</button>
        <button type="reset" class="delete">ยกเลิก</button>
    </div>
    </form>
    
    <script>
    // เริ่มต้น Select2
    $(document).ready(function() {
        $('#Product_ID').select2({
            placeholder: '--เลือกผลิตภัณฑ์--',
            allowClear: true
        });

        // ตรวจสอบและแสดงข้อความแจ้งเตือน
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
