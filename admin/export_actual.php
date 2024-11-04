<?php
require_once '../db.php';
session_start();

// รับค่าที่ส่งมาจากฟอร์ม
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$section = isset($_GET['section']) ? $_GET['section'] : null;
$product = isset($_GET['product']) ? $_GET['product'] : null;

// สร้าง SQL query เบื้องต้น
$sql = "SELECT ap.Act_ID, ap.WO_No, 
         pd.P_Name,
        td.TD_Name,
        t.Pipe_Size,
        pe.PE_Name,
        IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(t.degree, ' องศา'), '') AS degree,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' ',t.pipe_size, ' ', pe.PE_Name, 
               IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS DisplayText,
        s.S_Name AS Section_Name, 
        ap.St_ID, 
        ap.PD_ID, 
        ap.Product_queue, ap.Date_Time, ap.Actual, ap.NoGood, ap.FinishGood, ap.Losstime, ap.Diff, 
        pp.Plan, ws.St_Name, ws.cycletime, c.Working_Hours, ap.currentPlan
        FROM actual_production ap
        JOIN production_plan pp ON ap.PD_ID = pp.PD_ID
        JOIN product pr ON pr.Product_ID = ap.Product_ID
        JOIN product_detail pd ON pr.P_ID = pd.P_ID
        JOIN type t ON pr.T_ID = t.T_ID
        JOIN type_detail td ON t.TD_ID = td.TD_ID
        JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
        JOIN section s ON ap.S_ID = s.S_ID
        JOIN work_step ws ON ap.St_ID = ws.St_ID
        JOIN calculate c ON ws.Product_ID = c.Product_ID
        WHERE 1=1";

// เงื่อนไขกรองวันที่
if ($date_from && $date_to) {
    $sql .= " AND ap.Date_Time BETWEEN ? AND ?";
} elseif ($date_from) {
    $sql .= " AND ap.Date_Time >= ?";
} elseif ($date_to) {
    $sql .= " AND ap.Date_Time <= ?";
}

// เงื่อนไขกรองแผนก
if ($section) {
    $sql .= " AND ap.S_ID = ?";
}

// เงื่อนไขกรองผลิตภัณฑ์
if ($product) {
    $sql .= " AND ap.Product_ID = ?";
}

$sql .= " ORDER BY ap.Act_ID";

// เตรียม statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('SQL Prepare Error: ' . $conn->error);
}

// การ bind ค่าตัวแปรในการกรอง
$bind_types = "";
$bind_values = [];

if ($date_from && $date_to) {
    $bind_types .= "ss"; // วันที่ใช้ 's' เพราะเป็น string
    $bind_values[] = $date_from;
    $bind_values[] = $date_to;
} elseif ($date_from) {
    $bind_types .= "s";
    $bind_values[] = $date_from;
} elseif ($date_to) {
    $bind_types .= "s";
    $bind_values[] = $date_to;
}

if ($section) {
    $bind_types .= "i"; // section เป็น integer
    $bind_values[] = $section;
}

if ($product) {
    $bind_types .= "i"; // product เป็น integer
    $bind_values[] = $product;
}

// ตรวจสอบว่ามีค่าที่ต้อง bind หรือไม่
if (!empty($bind_values)) {
    $stmt->bind_param($bind_types, ...$bind_values);
}

// ดำเนินการ query และดึงผลลัพธ์
$stmt->execute();
$result = $stmt->get_result();
$actual_productions = $result->fetch_all(MYSQLI_ASSOC);

// บันทึกข้อมูลผลิตภัณฑ์จริงหลังกรองลงใน session
$_SESSION['actual_productions'] = $actual_productions;

include 'admin_index.html';
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/export_actual1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
    <title>Spiral</title>

</head>
<body>

<h2>Actual Production</h2>

<!-- ฟอร์มสำหรับการกรอง -->
<form method="GET" action="" class="filter-container">

    <label for="date_from">เริ่มวันที่:</label>
    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">

    <label for="date_to">ถึงวันที่:</label>
    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">

    <label for="section">แผนก:</label>
    <select id="section" name="section">
        <option value="">-- เลือกแผนก --</option>
        <?php
        // แสดงแผนกที่มาจากฐานข้อมูล
        $sections = $conn->query("SELECT S_ID, S_Name FROM section");
        while ($section_row = $sections->fetch_assoc()) {
            $selected = ($section_row['S_ID'] == $section) ? "selected" : "";
            echo '<option value="'.$section_row['S_ID'].'" '.$selected.'>'.$section_row['S_Name'].'</option>';
        }
        ?>
    </select>

    <label for="product">ผลิตภัณฑ์:</label>
<select id="product" name="product">
    <option value="">-- เลือกผลิตภัณฑ์ --</option>
    <?php
    // แสดงผลิตภัณฑ์ที่มาจากฐานข้อมูล product_detail ผ่านการ JOIN กับ product
    $products = $conn->query("
        SELECT DISTINCT p.Product_ID, pd.P_Name 
        FROM product p
        JOIN product_detail pd ON p.P_ID = pd.P_ID
    ");

    $displayed_products = []; // เก็บชื่อผลิตภัณฑ์ที่เคยแสดงแล้ว
    while ($product_row = $products->fetch_assoc()) {
        if (!in_array($product_row['P_Name'], $displayed_products)) {
            $selected = ($product_row['Product_ID'] == $product) ? "selected" : "";
            echo '<option value="'.$product_row['Product_ID'].'" '.$selected.'>'.$product_row['P_Name'].'</option>';
            $displayed_products[] = $product_row['P_Name']; // เก็บชื่อผลิตภัณฑ์ใน array เพื่อไม่ให้แสดงซ้ำ
        }
    }
    ?>
</select>

    <button type="submit">กรองข้อมูล</button>
    <button type="button" class="btn-reset" onclick="window.location.href='export_actual.php'">รีเซ็ต</button> 
    <a href="export_actual_excel.php" id="btn-export"><i class="fa-solid fa-file-excel"></i></a>
    <a href="export_actual_pdf.php" id="btn-export"><i class='bx bxs-file-pdf'></i></a>
</form>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>วัน/เวลา</th>
                <th>W/O</th>
                <th>ผลิตภัณฑ์</th>
                <th>แผนก</th>
                <th>ชื่อขั้นตอน</th>
                <th>แผนผลิต</th>
                <th>รอผลิต</th>
                <th>ผลิตจริง</th>
                <th>ของเสีย/กก.</th>
                <th>เวลาที่สูญเสีย/นาที</th>
                <th>ยอดรวม</th>
                <th>ยอด+,-</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($actual_productions)): ?>
                <?php foreach ($actual_productions as $actual_production): ?>
                    <tr id="actual_production-<?php echo htmlspecialchars($actual_production['Act_ID']); ?>">
                        <td><?php echo htmlspecialchars($actual_production['Date_Time']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['WO_No']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['DisplayText']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['Section_Name']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['St_Name']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['currentPlan']); ?></td> <!-- ค่าที่คำนวณ -->
                        <td><?php echo htmlspecialchars($actual_production['Product_queue']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['Actual']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['NoGood']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['Losstime']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['FinishGood']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['Diff']); ?></td>
                    </tr>
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
