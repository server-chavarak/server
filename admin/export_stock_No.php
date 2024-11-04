<?php
require_once '../db.php'; // เชื่อมต่อฐานข้อมูล
session_start();
 
// รับค่าการค้นหาจากฟอร์ม (ถ้ามี)
$startDate = $_GET['start_date'] ?? '';  // วันที่เริ่มต้น
$endDate = $_GET['end_date'] ?? '';      // วันที่สิ้นสุด
$location = $_GET['location'] ?? '';      // สถานที่เก็บ
 
// ดึงข้อมูลสถานที่เก็บ (Location)
$locations = [];
$locationSql = "SELECT DISTINCT Location FROM stock_no_order"; // ใช้คำสั่งเพื่อดึงสถานที่เก็บที่ไม่ซ้ำ
$locationResult = $conn->query($locationSql);
if ($locationResult) {
    while ($row = $locationResult->fetch_assoc()) {
        $locations[] = $row['Location'];
    }
} else {
    echo "Error retrieving locations: " . mysqli_error($conn); // แสดงข้อผิดพลาดหากมีการดึงข้อมูลล้มเหลว
}
 
// ดึงข้อมูลจากตาราง stock_no_order พร้อมการกรองหากมีการค้นหา
$sql = "SELECT
        sno.StockNo_ID,
        sno.Date_Time,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' ', t.pipe_size, ' ', pe.PE_Name,
               IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS Product_name,
        sno.Amount AS stock_no_order_Amount,
        sno.Location
    FROM
        stock_no_order sno
    INNER JOIN
        product pr ON sno.Product_ID = pr.Product_ID
    INNER JOIN
        product_detail pd ON pr.P_ID = pd.P_ID -- เชื่อม product_detail เพื่อดึง P_Name
    INNER JOIN
        type t ON pr.T_ID = t.T_ID
    INNER JOIN
        type_detail td ON t.TD_ID = td.TD_ID -- เชื่อม type_detail เพื่อดึง TD_Name
    INNER JOIN
        pipeend_detail pe ON t.PE_ID = pe.PE_ID -- เชื่อม pipeend_detail เพื่อดึง PE_Name
    WHERE
        1=1"; // สร้างเงื่อนไขพื้นฐานเพื่อเพิ่มเงื่อนไขเพิ่มเติมได้ง่าย
       
 
// เพิ่มเงื่อนไขตามวันที่และสถานที่เก็บ
if (!empty($startDate)) {
    $sql .= " AND sno.Date_Time >= ?";
}
if (!empty($endDate)) {
    $sql .= " AND sno.Date_Time <= ?";
}
if (!empty($location)) {
    $sql .= " AND sno.Location = ?";
}
 
$sql .= " ORDER BY sno.StockNo_ID ASC;";
 
// เตรียมคำสั่ง SQL
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Error preparing the SQL statement: ' . htmlspecialchars($conn->error));
}
 
// กำหนดค่าที่จะผูกกับคำสั่ง SQL
$params = [];
$paramTypes = '';
 
if (!empty($startDate)) {
    $params[] = $startDate;
    $paramTypes .= 's'; // ค่าประเภทวันที่
}
if (!empty($endDate)) {
    $params[] = $endDate;
    $paramTypes .= 's'; // ค่าประเภทวันที่
}
if (!empty($location)) {
    $params[] = $location;
    $paramTypes .= 's'; // ค่าประเภทสถานที่
}
 
// ผูกตัวแปรกับคำสั่ง SQL
if (!empty($paramTypes)) {
    $stmt->bind_param($paramTypes, ...$params);
}
 
// ดำเนินการคำสั่ง SQL
$stmt->execute();
$result = $stmt->get_result();
 
 
 
$stock_no_orders = $result->fetch_all(MYSQLI_ASSOC);
$_SESSION["stock_no_orders"] = $stock_no_orders;
// ปิดการเชื่อมต่อฐานข้อมูล
 
// นำข้อมูลมาแสดงในหน้า HTML
include 'admin_index.html';
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/export_stock2.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>รายงานสินค้าคงคลังที่ไม่มีเจ้าของ</title>
</head>
<body>
 
<h2>รายงานสินค้าคงคลังที่ไม่มีเจ้าของ</h2>
<!-- ฟอร์มสำหรับการกรอง -->
<form method="GET" action="" class="filter-container">
    <label for="date_from">วันที่เก็บ:</label>
    <input type="date" id="date_from" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
 
    <label for="date_to">ถึง:</label>
    <input type="date" id="date_to" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
 
    <label for="location">สถานที่เก็บ:</label>
    <select id="location" name="location">
        <option value="">เลือกสถานที่เก็บ</option>
        <?php foreach ($locations as $loc): ?>
            <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($loc === $location) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($loc); ?>
            </option>
        <?php endforeach; ?>
    </select>
 
    <button type="submit">กรองข้อมูล</button>
    <button type="button" class="btn-reset" onclick="window.location.href='export_stock_No.php'">รีเซ็ต</button>
 
    <a href="export_stock_No_excel.php" id="btn-export"><i class="fa-solid fa-file-excel"></i></a>
    <a href="export_stock_No_pdf.php" id="btn-export"><i class="bx bxs-file-pdf"></i></a>
</form>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>วันที่</th>
                <th>ผลิตภัณฑ์</th>
                <th>จำนวน/ชิ้น</th>
                <th>สถานที่เก็บ</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($stock_no_orders)): ?>
            <?php foreach ($stock_no_orders as $stock_no_order): ?>
                <tr id="stock_no_order-<?php echo htmlspecialchars($stock_no_order['StockNo_ID']); ?>">
                    <td><?php echo date('d/m/Y ', strtotime($stock_no_order['Date_Time'])); ?></td>
                    <td><?php echo htmlspecialchars($stock_no_order['Product_name']); ?></td>
                    <td><?php echo htmlspecialchars($stock_no_order['stock_no_order_Amount']); ?></td>
                    <td><?php echo htmlspecialchars($stock_no_order['Location']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">ไม่มีข้อมูลที่จะแสดง</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
 
</body>
</html>