<?php
require_once '../db.php';
session_start();

// รับค่าจากฟอร์มการกรอง
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$section = isset($_GET['section']) ? $_GET['section'] : null;
$product = isset($_GET['product']) ? $_GET['product'] : null;

// ถ้ามีวันที่สิ้นสุด ให้เพิ่มเวลาเป็น 23:59:59
if ($date_to) {
    $date_to .= ' 23:59:59'; // เพิ่มเวลาเพื่อให้ครอบคลุมทั้งวันสุดท้าย
}

// สร้าง SQL query เบื้องต้น
$sql = "SELECT ap.Act_ID, ap.WO_No, 
         pd.P_Name,
        td.TD_Name,
        t.Pipe_Size,
        pe.PE_Name,
        IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(t.degree, ' องศา'), '') AS degree,
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

// เงื่อนไขการกรอง
$params = [];
$bind_types = '';

// Date filter
if ($date_from && $date_to) {
    $sql .= " AND ap.Date_Time BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $bind_types .= 'ss';
} elseif ($date_from) {
    $sql .= " AND ap.Date_Time >= ?";
    $params[] = $date_from;
    $bind_types .= 's';
} elseif ($date_to) {
    $sql .= " AND ap.Date_Time <= ?";
    $params[] = $date_to;
    $bind_types .= 's';
}

// Section filter
if ($section) {
    $sql .= " AND ap.S_ID = ?";
    $params[] = $section;
    $bind_types .= 'i';
}

// Product filter
if ($product) {
    $sql .= " AND ap.Product_ID = ?";
    $params[] = $product;
    $bind_types .= 'i';
}

$sql .= " ORDER BY ap.Act_ID";

// เตรียม statement
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

// Bind parameters if exist
if (!empty($params)) {
    $stmt->bind_param($bind_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// ตรวจสอบว่ามีข้อมูลที่กรองแล้วหรือไม่
if ($result->num_rows === 0) {
    die("No records found for the specified filters.");
}

// สร้างไฟล์ Excel (XML)
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="actual_production_export.xls"');

// เริ่มเขียน XML ที่จะใช้ใน Excel
echo '<?xml version="1.0"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                xmlns:o="urn:schemas-microsoft-com:office:office"
                xmlns:x="urn:schemas-microsoft-com:office:excel"
                xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
        <Worksheet ss:Name="ActualProduction">
            <Table>';

// กำหนดความกว้างของคอลัมน์
$columnWidths = [120, 80, 300, 100, 100, 80, 80, 80, 80, 100, 80, 80];
foreach ($columnWidths as $width) {
    echo '<Column ss:AutoFitWidth="1" ss:Width="' . $width . '"/>';
}

// แถวหัวตาราง
$headers = ['วัน/เวลา', 'W/O', 'ผลิตภัณฑ์', 'แผนก', 'ชื่อขั้นตอน', 'แผนผลิต', 'รอผลิต', 'ผลิตจริง', 'ของเสีย/กก.', 'เวลาที่สูญเสีย/นาที', 'ยอดรวม', 'ยอด+,-'];
echo '<Row>';
foreach ($headers as $header) {
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
}
echo '</Row>';

$actual_productions = isset($_SESSION['actual_productions']) ? $_SESSION['actual_productions'] : [];
// แถวข้อมูล
foreach ($actual_productions as $row) {
    echo '<Row>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Date_Time']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['WO_No']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['P_Name'] . ' - ' . $row['TD_Name'] . ' Ø' . $row['Pipe_Size'] . 'mm. - ' . $row['PE_Name'] . ' ' . $row['degree']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Section_Name']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['St_Name']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['currentPlan']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Product_queue']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Actual']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['NoGood']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Losstime']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['FinishGood']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Diff']) . '</Data></Cell>';
    echo '</Row>';
}

echo '    </Table>
        </Worksheet>
    </Workbook>';
?>
