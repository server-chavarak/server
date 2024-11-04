<?php
require_once '../db.php';
session_start();
 
// รับค่าการค้นหาจากฟอร์ม (ถ้ามี)
$search = $_GET['search'] ?? '';  // เปลี่ยนเป็น GET
$startDate = $_GET['start_date'] ?? '';  // เปลี่ยนเป็น GET
$endDate = $_GET['end_date'] ?? '';  // เปลี่ยนเป็น GET
$location = $_GET['location'] ?? '';  // เปลี่ยนเป็น GET
 
// SQL สำหรับดึงข้อมูลสถานที่
$locationSql = "SELECT DISTINCT Location FROM stock WHERE Location IS NOT NULL";
$locationResult = $conn->query($locationSql);
$locations = [];
if ($locationResult) {
    while ($row = $locationResult->fetch_assoc()) {
        $locations[] = $row['Location'];
    }
}
 
// เริ่มสร้างคำสั่ง SQL สำหรับดึงข้อมูล stock รวมถึง `Status_ID`
$sql = "SELECT
        s.Stock_ID,
        s.Date_Time,
        s.WO_No,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' ',t.pipe_size, ' ', pe.PE_Name,
               IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS Product_name,
        s.Amount AS Stock_Amount,
        s.Location,
        CONCAT(c.Cus_Fname, ' ', c.Cus_Lname, ' - ', c.Project_Name) AS Customer_Name,
        COALESCE(pp.Plan, 0) AS Plan,
        s.Status_ID,  
        COALESCE(st.Status_Name, 'สถานะไม่ทราบ') AS Status_Name
    FROM
        stock s
    LEFT JOIN product pr ON s.Product_ID = pr.Product_ID
    LEFT JOIN product_detail pd ON pr.P_ID = pd.P_ID
    LEFT JOIN type t ON pr.T_ID = t.T_ID
    LEFT JOIN type_detail td ON t.TD_ID = td.TD_ID
    LEFT JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
    LEFT JOIN orders o ON s.WO_No = o.WO_No
    LEFT JOIN customer c ON o.Cus_ID = c.Cus_ID
    LEFT JOIN production_plan pp ON s.WO_No = pp.WO_No AND s.Product_ID = pp.Product_ID
    LEFT JOIN status st ON s.Status_ID = st.Status_ID
    WHERE s.Amount > 0"; // เงื่อนไขเริ่มต้น
 
// เงื่อนไขการค้นหาข้อมูล
$hasSearchCondition = false; // ตัวแปรสำหรับเช็คว่ามีเงื่อนไขการค้นหาหรือไม่
if (!empty($search)) {
    $sql .= " AND (c.Cus_Fname LIKE ?
                   OR c.Cus_Lname LIKE ?
                   OR c.Project_Name LIKE ?
                   OR s.WO_No LIKE ?
                   OR pd.P_Name LIKE ?
                   OR td.TD_Name LIKE ?
                   OR t.pipe_size LIKE ?
                   OR pe.PE_Name LIKE ?
                   OR t.degree LIKE ?
                   OR s.Location LIKE ?
                   OR st.Status_Name LIKE ?
                   OR s.Date_Time LIKE ?
                   OR s.Amount = ?)";
    $hasSearchCondition = true; // มีเงื่อนไขการค้นหา
}
 
// เงื่อนไขกรองวันที่
if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND s.Date_Time BETWEEN ? AND ?";
    $hasSearchCondition = true; // มีเงื่อนไขการค้นหา
}
 
// เงื่อนไขกรองสถานที่เก็บ
if (!empty($location)) {
    $sql .= " AND s.Location LIKE ?";
    $hasSearchCondition = true; // มีเงื่อนไขการค้นหา
}
 
// จัดการกับการจัดกลุ่มข้อมูล
$sql .= " GROUP BY DATE(s.Date_Time), s.WO_No, s.Product_ID  
          ORDER BY s.Stock_ID ASC";
 
// เตรียมคำสั่ง SQL
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Error preparing the SQL statement: ' . htmlspecialchars($conn->error));
}
 
// กำหนดค่าให้กับตัวแปรที่ใช้ในคำสั่ง SQL
$params = [];
$searchTermLike = '%' . $search . '%';
 
// ถ้าต้องการกรองการค้นหา
if ($hasSearchCondition) {
    if (!empty($search)) {
        for ($i = 0; $i < 12; $i++) {
            $params[] = $searchTermLike; // สำหรับ LIKE
        }
        $params[] = $search; // สำหรับ s.Amount
    }
 
    // ถ้ามีเงื่อนไขกรองวันที่
    if (!empty($startDate) && !empty($endDate)) {
        $params[] = $startDate;
        $params[] = $endDate;
    }
 
    // ถ้ามีเงื่อนไขกรองสถานที่เก็บ
    if (!empty($location)) {
        $params[] = '%' . $location . '%'; // ใช้ LIKE สำหรับสถานที่
    }
}
 
// ผูกตัวแปรกับคำสั่ง SQL
if (!empty($params)) {
    $types = str_repeat('s', count($params)); // สร้างสตริงประเภทตามจำนวนตัวแปร
    $stmt->bind_param($types, ...$params);
}
 
// เรียกใช้งานคำสั่ง SQL
if (!$stmt->execute()) {
    die('Error executing the SQL statement: ' . htmlspecialchars($stmt->error));
}
 
// ดึงข้อมูลผลลัพธ์
$result = $stmt->get_result();
if ($result === false) {
    die('Error getting result set: ' . htmlspecialchars($stmt->error));
}
 
// เก็บข้อมูลแต่ละแถวในอาเรย์ $stocks
$stocks = $result->fetch_all(MYSQLI_ASSOC);
$_SESSION['stocks'] = $stocks;
$stmt->close();
$conn->close();
 
// นำข้อมูลมาแสดงในหน้า HTML
include 'manager_index.html';
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/export_stock1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Stock</title>
</head>
<body>
 
<h2>รายงานสินค้าคงคลังที่มีเจ้าของ</h2>
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
    <button type="button" class="btn-reset" onclick="window.location.href='export_stock1.php'">รีเซ็ต</button>
 
    <a href="export_stock_excel.php" id="btn-export"><i class="fa-solid fa-file-excel"></i></a>
    <a href="export_stock_pdf.php" id="btn-export"><i class="bx bxs-file-pdf"></i></a>
</form>
 
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>วันที่</th>
                <th>W/O</th>
                <th>ผลิตภัณฑ์</th>
                <th>จำนวน/ชิ้น</th>
                <th>สถานที่เก็บ</th>
                <th>ลูกค้า</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($stocks)): ?>
            <?php foreach ($stocks as $stock): ?>
                <tr id="stock-<?php echo htmlspecialchars($stock['Stock_ID']); ?>">
                    <td><?php echo date('d/m/Y ', strtotime($stock['Date_Time'])); ?></td>
                    <td><?php echo htmlspecialchars($stock['WO_No']); ?></td>
                    <td><?php echo htmlspecialchars($stock['Product_name']); ?></td>
                    <td><?php echo htmlspecialchars($stock['Stock_Amount']); ?></td>
                    <td><?php echo htmlspecialchars($stock['Location']); ?></td>
                    <td><?php echo htmlspecialchars($stock['Customer_Name']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">ไม่พบข้อมูลที่ตรงกับการค้นหา</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
 
</body>
</html>