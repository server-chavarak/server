<?php
require('../fpdf/fpdf.php');
require_once '../db.php';
session_start();

// เริ่มการบล็อกการส่งข้อมูลไปยังเบราว์เซอร์
ob_start();

// ปิดการแคชของเบราว์เซอร์
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="1.pdf"');
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
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
        WHERE 1=1"; // ค่าเริ่มต้นในการกรอง

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
    $bind_types .= "ss"; 
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
    $bind_types .= "i"; 
    $bind_values[] = $section;
}
if ($product) {
    $bind_types .= "i"; 
    $bind_values[] = $product;
}

// ตรวจสอบว่ามีค่าที่จะ bind หรือไม่
if (!empty($bind_values)) {
    $stmt->bind_param($bind_types, ...$bind_values); 
}

$stmt->execute();
$result = $stmt->get_result();
// $actual_productions = $result->fetch_all(MYSQLI_ASSOC);


// สร้าง PDF
$pdf = new FPDF('L', 'mm', 'A4'); // 'L' for Landscape
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
$pdf->AddPage();

// ตั้งค่าฟอนต์สำหรับหัวข้อใหญ่
$pdf->SetFont('THSarabunNew', '', 16);
$pdf->Cell(0, 15, iconv('UTF-8', 'CP874', 'รายงานยอดการผลิตจริง'), 0, 1, 'C');
$pdf->Ln(8); // เว้นบรรทัดหลังจากหัวข้อ

// กำหนดฟอนต์ขนาด 12 สำหรับส่วนอื่นๆ
$pdf->SetFont('THSarabunNew', '', 12);

// กำหนดขนาดคอลัมน์
$widths = [30, 15, 85, 20, 25, 15, 15, 15, 15, 15, 15, 15];
$totalWidth = array_sum($widths); // คำนวณความกว้างรวมของตาราง
$startX = (297 - $totalWidth) / 2; // ตำแหน่ง X เพื่อให้ตารางอยู่ตรงกลาง (297 คือความกว้างของ A4 แนวนอน)
$header = ['วัน/เวลา', 'W/O', 'ผลิตภัณฑ์', 'แผนก', 'ขั้นตอน', 'แผนผลิต', 'รอผลิต', 'ผลิตจริง', 'ของเสีย', 'เวลาสูญเสีย', 'ยอดรวม', 'ยอด+,-'];

// ตั้งค่าฟอนต์เป็นตัวหนาสำหรับหัวตาราง
$pdf->SetFillColor(25, 25, 112); // สีพื้นหลังส่วนหัว
$pdf->SetTextColor(255); // สีตัวอักษรในหัวตาราง
$pdf->SetLineWidth(.3); // ความหนาของเส้นขอบ
$pdf->SetDrawColor(192, 192, 192); // สีเส้นขอบ

$pdf->SetX($startX); // กำหนดตำแหน่ง X ให้ตารางอยู่ตรงกลาง
foreach ($header as $i => $col) {
    $pdf->Cell($widths[$i], 10, iconv('UTF-8', 'CP874', $col), 1, 0, 'C', true);
}
$pdf->Ln();

// ข้อมูลการผลิตจริง
$pdf->SetFillColor(230, 240, 255); // สีพื้นหลังของแถวข้อมูล
$pdf->SetTextColor(0); // สีตัวอักษรของข้อมูล
$fill = false; // ใช้ในการสลับสีของแถว

$actual_productions = isset($_SESSION['actual_productions']) ? $_SESSION['actual_productions'] : [];
foreach ($actual_productions as $row) {

    $pdf->SetX($startX); // กำหนดตำแหน่ง X ให้แถวข้อมูลอยู่ตรงกลาง

    $pdf->Cell($widths[0], 10, iconv('UTF-8', 'CP874', $row['Date_Time']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[1], 10, iconv('UTF-8', 'CP874', $row['WO_No']), 1, 0, 'C', $fill);
    
    // ใช้ MultiCell แทนในคอลัมน์ที่ข้อมูลอาจยาวเกินไป เช่น 'ผลิตภัณฑ์'
    $x = $pdf->GetX(); // บันทึกตำแหน่ง X ปัจจุบัน
    $y = $pdf->GetY(); // บันทึกตำแหน่ง Y ปัจจุบัน

    $pdf->Cell($widths[2], 10, iconv('UTF-8', 'CP874', $row['DisplayText']), 1,0, 'L', $fill);
    
    // ปรับตำแหน่ง X กลับมาหลังจากการใช้ MultiCell เพื่อไม่ให้ข้อมูลในคอลัมน์ถัดไปซ้อนกัน

    $pdf->SetXY($x + $widths[2], $y);
    $pdf->Cell($widths[3], 10, iconv('UTF-8', 'CP874', $row['Section_Name']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[4], 10, iconv('UTF-8', 'CP874', $row['St_Name']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[5], 10, iconv('UTF-8', 'CP874', $row['currentPlan']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[6], 10, iconv('UTF-8', 'CP874', $row['Product_queue']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[7], 10, iconv('UTF-8', 'CP874', $row['Actual']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[8], 10, iconv('UTF-8', 'CP874', $row['NoGood']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[9], 10, iconv('UTF-8', 'CP874', $row['Losstime']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[10], 10, iconv('UTF-8', 'CP874', $row['FinishGood']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[11], 10, iconv('UTF-8', 'CP874', $row['Diff']), 1, 0, 'C', $fill);
    $pdf->Ln();
    $fill = !$fill; // สลับสีแถว
}

// ล้างบัฟเฟอร์และส่งออก PDF
ob_end_clean(); // ล้างบัฟเฟอร์ก่อนสร้าง PDF
$pdf->Output('D', 'report.pdf'); // ส่งออกไฟล์ PDF
exit(); // หยุดการทำงานหลังจากส่งออก
?>