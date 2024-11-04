<?php
require('../fpdf/fpdf.php');
require_once '../db.php';
session_start();
 
// เริ่มการบล็อกการส่งข้อมูลไปยังเบราว์เซอร์
ob_start();
 
// ปิดการแคชของเบราว์เซอร์
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="inventory_report.pdf"');
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
 
// รับค่าจากฟอร์มการกรอง
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;  // วันที่เริ่มต้น
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;          // วันที่สิ้นสุด
$location = isset($_GET['location']) ? $_GET['location'] : null;         // สถานที่เก็บ
 
// สร้าง SQL query เบื้องต้น
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
        product_detail pd ON pr.P_ID = pd.P_ID
    INNER JOIN
        type t ON pr.T_ID = t.T_ID
    INNER JOIN
        type_detail td ON t.TD_ID = td.TD_ID
    INNER JOIN
        pipeend_detail pe ON t.PE_ID = pe.PE_ID
    WHERE 1=1"; // ค่าเริ่มต้นในการกรอง
 
// เงื่อนไขกรองวันที่และสถานที่เก็บ
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
 
// เตรียม statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('SQL Prepare Error: ' . $conn->error);
}
 
// การ bind ค่าตัวแปรในการกรอง
$bind_types = "";
$bind_values = [];
 
if (!empty($startDate)) {
    $bind_types .= "s";
    $bind_values[] = $startDate;
}
if (!empty($endDate)) {
    $bind_types .= "s";
    $bind_values[] = $endDate;
}
if (!empty($location)) {
    $bind_types .= "s";
    $bind_values[] = $location;
}
 
// ตรวจสอบว่ามีค่าที่จะ bind หรือไม่
if (!empty($bind_values)) {
    $stmt->bind_param($bind_types, ...$bind_values);
}
 
$stmt->execute();
$result = $stmt->get_result();
 
$stock_no_orders = [];
while ($row = $result->fetch_assoc()) {
    $stock_no_orders[] = $row; // เพิ่มข้อมูลแต่ละแถวเข้าในอาเรย์ $stock_no_orders
}
 
// สร้าง PDF
$pdf = new FPDF('L', 'mm', 'A4'); // 'L' for Landscape
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
$pdf->AddPage();
 
// ตั้งค่าฟอนต์สำหรับหัวข้อใหญ่
$pdf->SetFont('THSarabunNew', '', 16);
$pdf->Cell(0, 15, iconv('UTF-8', 'CP874', 'รายงานสินค้าคงคลังที่ไม่มีเจ้าของ'), 0, 1, 'C');
$pdf->Ln(8); // เว้นบรรทัดหลังจากหัวข้อ
 
// กำหนดฟอนต์ขนาด 12 สำหรับส่วนอื่นๆ
$pdf->SetFont('THSarabunNew', '', 12);
 
// กำหนดขนาดคอลัมน์
$widths = [30, 80, 30, 50]; // ความกว้างของคอลัมน์
$totalWidth = array_sum($widths);
$startX = (297 - $totalWidth) / 2; // ตำแหน่ง X เพื่อให้ตารางอยู่ตรงกลาง
$header = ['วันที่', 'ผลิตภัณฑ์', 'จำนวน/ชิ้น', 'สถานที่เก็บ'];
 
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
$stock_no_orders = $_SESSION["stock_no_orders"];
foreach ($stock_no_orders as $row) {
    $pdf->SetX($startX); // กำหนดตำแหน่ง X ให้แถวข้อมูลอยู่ตรงกลาง
 
    $pdf->Cell($widths[0], 10, iconv('UTF-8', 'CP874', date('d/m/Y', strtotime($row['Date_Time']))), 1, 0, 'C', $fill);
    $pdf->Cell($widths[1], 10, iconv('UTF-8', 'CP874', $row['Product_name']), 1, 0, 'L', $fill);
    $pdf->Cell($widths[2], 10, iconv('UTF-8', 'CP874', $row['stock_no_order_Amount']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[3], 10, iconv('UTF-8', 'CP874', $row['Location']), 1, 0, 'C', $fill);
    $pdf->Ln();
    $fill = !$fill; // สลับสีแถว
}
 
// ล้างบัฟเฟอร์และส่งออก PDF
ob_end_clean(); // ล้างบัฟเฟอร์ก่อนสร้าง PDF
$pdf->Output('D', 'inventory_report.pdf'); // ส่งออกไฟล์ PDF
exit(); // หยุดการทำงานหลังจากส่งออก
?>