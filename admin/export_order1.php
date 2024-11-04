<?php
require_once '../db.php';
session_start();

// รับค่ากรองจากฟอร์ม (ถ้ามี)
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
// รับค่าจากฟอร์มกรอง
$product = isset($_GET['product']) ? $_GET['product'] : '';  // ตรวจสอบการกรองผลิตภัณฑ์
$status = isset($_GET['Status_ID']) ? $_GET['Status_ID'] : '';  // ตรวจสอบการกรองสถานะ

// สร้างคำสั่ง SQL เริ่มต้น
$sql = "SELECT 
        o.Date_Recevied, 
        CONCAT(c.Cus_Fname, ' ', c.Cus_Lname, ' - ', c.Project_Name) AS Product_name,
        o.WO_No,
        pd.P_Name,
        td.TD_Name,
        t.Pipe_Size,
        pe.PE_Name,
        IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(t.degree, ' องศา'), '') AS degree,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' ',t.pipe_size, ' ', pe.PE_Name, 
               IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS DisplayText,
        od.Amount,
        o.Sent_Date,
        s.Status_Name
    FROM orders o
    JOIN customer c ON o.Cus_ID = c.Cus_ID
    JOIN order_details od ON o.WO_No = od.WO_No
    JOIN product p ON od.Product_ID = p.Product_ID
    JOIN product_detail pd ON p.P_ID = pd.P_ID
    JOIN type t ON p.T_ID = t.T_ID
    JOIN type_detail td ON t.TD_ID = td.TD_ID
    JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
    JOIN status s ON o.Status_ID = s.Status_ID
    WHERE 
        (c.Cus_Fname LIKE ? 
        OR c.Cus_Lname LIKE ? 
        OR c.Project_Name LIKE ? 
        OR o.WO_No LIKE ? 
        OR o.Date_Recevied LIKE ? 
        OR o.Sent_Date LIKE ?)";

$params = ['ssssss', $search, $search, $search, $search, $search, $search];

// เงื่อนไขกรองสถานะ
if (!empty($status)) {
    $sql .= " AND o.Status_ID = ?";  // เพิ่มเงื่อนไขกรองสถานะ
    $params[0] .= 'i';  // เพิ่มประเภทข้อมูล integer สำหรับสถานะ
    $params[] = $status;


}

// เงื่อนไขกรองผลิตภัณฑ์
if (!empty($product)) {
    $sql .= " AND p.Product_ID = ?";  // เพิ่มเงื่อนไขกรองผลิตภัณฑ์
    $params[0] .= 'i';  // เพิ่มประเภทข้อมูล integer สำหรับ Product_ID
    $params[] = $product;
}

$sql .= " ORDER BY o.Date_Recevied, o.WO_No, p.Product_ID";

// เตรียม statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Error in SQL preparation: ' . $conn->error); // แสดงข้อผิดพลาด SQL
}

// bind_param ผ่านการใช้ dynamic binding
if (!$stmt->bind_param(...$params)) {
    die('Error in bind_param: ' . $stmt->error); // แสดงข้อผิดพลาดจากการ bind
}

// ดำเนินการ query
if (!$stmt->execute()) {
    die('Error in SQL execution: ' . $stmt->error); // แสดงข้อผิดพลาดการ execute
}

// ดึงผลลัพธ์จากฐานข้อมูล
$result = $stmt->get_result();
if (!$result) {
    die('Error in fetching result: ' . $stmt->error); // แสดงข้อผิดพลาดในการดึงข้อมูล
}
$order = $result->fetch_all(MYSQLI_ASSOC);
// บันทึกข้อมูลผลิตภัณฑ์จริงหลังกรองลงใน session
$_SESSION['orders'] = $order;
include 'admin_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/export_order.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
    <title>order</title>
</head>
<body>

<h2>คำสั่งซื้อสินค้า</h2>

<!-- ฟอร์มสำหรับการกรอง -->
<form method="GET" action="" class="filter-container">

<label for="product">ผลิตภัณฑ์:</label>
<select id="product" name="product">
    <option value="">-- เลือกผลิตภัณฑ์ --</option>
    <?php
    // แสดงผลิตภัณฑ์ที่มาจากคำสั่งซื้อ (orders) ผ่านการ JOIN กับ order_details และ product_detail
    $products = $conn->query("
        SELECT DISTINCT p.Product_ID, pd.P_Name
        FROM orders o
        JOIN order_details od ON o.WO_No = od.WO_No
        JOIN product p ON od.Product_ID = p.Product_ID
        JOIN product_detail pd ON p.P_ID = pd.P_ID
    ");
    
    while ($product_row = $products->fetch_assoc()) {
        $selected = ($product_row['Product_ID'] == $product) ? "selected" : "";
        echo '<option value="'.$product_row['Product_ID'].'" '.$selected.'>'.$product_row['P_Name'].'</option>';
    }
    ?>
</select>

<label for="Status_ID">สถานะการผลิต:</label>
<select id="Status_ID" name="Status_ID">  
    <option value="">-- เลือกสถานะ --</option>
    <?php
    // แสดงสถานะการผลิตจากตาราง status
    $statuses = $conn->query("SELECT Status_ID, Status_Name FROM status");
    while ($status_row = $statuses->fetch_assoc()) {
        $selected = ($status_row['Status_ID'] == $status) ? "selected" : "";
        echo '<option value="'.$status_row['Status_ID'].'" '.$selected.'>'.$status_row['Status_Name'].'</option>';
    }
    ?>
</select>

<button type="submit">กรองข้อมูล</button>
<button type="button" class="btn-reset" onclick="window.location.href='export_order1.php'">รีเซ็ต</button>
   
<a href="export_order1_excel.php" id="btn-export"><i class="fa-solid fa-file-excel"></i></a>
<a href="export_order1_pdf.php" id="btn-export"><i class="bx bxs-file-pdf"></i></a>

</form>
    
<!-- แสดงตารางข้อมูลคำสั่งซื้อ -->
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>วันที่รับเข้า</th>
                <th>ชื่อลูกค้า</th>
                <th>W/O</th>
                <th>ผลิตภัณฑ์</th>
                <th>จำนวน/ชิ้น</th>
                <th>วันที่จัดส่ง</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($order)): ?>
            <?php foreach ($order as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['Date_Recevied']); ?></td>
                        <td><?php echo htmlspecialchars($row['Product_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['WO_No']); ?></td>
                        <td>
                            <?php 
                            echo isset($row['P_Name']) ? htmlspecialchars($row['P_Name']) : ''; 
                            echo ' - '; // แทรกขีดกลางระหว่างข้อมูล
                            echo isset($row['TD_Name']) ? htmlspecialchars($row['TD_Name']) : '';
                            echo ' - ';
                            echo isset($row['Pipe_Size']) ? htmlspecialchars($row['Pipe_Size']) : ''; 
                            echo 'mm. '; // หน่วยขนาด
                            echo ' - ';
                            echo isset($row['PE_Name']) ? htmlspecialchars($row['PE_Name']) : ''; 
                            echo ' - '; // คั่นระหว่าง Pipe_End กับ degree
                            echo isset($row['degree']) ? htmlspecialchars($row['degree']) : ''; 
                            ?>
                        </td>

                        <td><?php echo htmlspecialchars($row['Amount']); ?></td>
                        <td><?php echo htmlspecialchars($row['Sent_Date']); ?></td>
                        <td><?php echo htmlspecialchars($row['Status_Name']); ?></td>

                        <?php endforeach; 
                    
                    ?>
            <?php else: ?>
                <tr>
                    <td colspan="12" class="no-data">ไม่มีข้อมูลการผลิตจริง</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
