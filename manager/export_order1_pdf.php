<?php
require('../fpdf/fpdf.php');
require_once '../db.php';
session_start();

// เริ่มการบล็อกการส่งข้อมูลไปยังเบราว์เซอร์
ob_start();

// ปิดการแคชของเบราว์เซอร์
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="report.pdf"');
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// รับค่ากรองจากฟอร์ม (ถ้ามี)
$product = isset($_GET['product']) ? $_GET['product'] : '';  // ตรวจสอบการกรองผลิตภัณฑ์
$status = isset($_GET['Status_ID']) ? $_GET['Status_ID'] : '';  // ตรวจสอบการกรองสถานะ

// สร้าง SQL Query สำหรับกรองข้อมูล
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
    WHERE 1=1"; // ค่าเริ่มต้นในการกรอง

// เงื่อนไขกรองสถานะ
$params = [];
if (!empty($status)) {
    $sql .= " AND o.Status_ID = ?";  // เพิ่มเงื่อนไขกรองสถานะ
    $params[] = ['type' => 'i', 'value' => $status];
}

// เงื่อนไขกรองผลิตภัณฑ์
if ($product) {
    $sql .= " AND p.Product_ID = ?";  // เพิ่มเงื่อนไขกรองผลิตภัณฑ์
    $params[] = ['type' => 'i', 'value' => $product];
}

$sql .= " ORDER BY o.Date_Recevied, o.WO_No, p.Product_ID";

// เตรียม statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Error in SQL preparation: ' . $conn->error);
}

// bind_param dynamically
if (!empty($params)) {
    $types = '';
    $values = [];
    foreach ($params as $param) {
        $types .= $param['type'];
        $values[] = $param['value'];
    }
    $stmt->bind_param($types, ...$values);
}

// ดำเนินการ query
if (!$stmt->execute()) {
    die('Error in SQL execution: ' . $stmt->error);
}

$stmt->execute();
$result = $stmt->get_result();
#$orders = $result->fetch_all(MYSQLI_ASSOC);

// สร้าง PDF
$pdf = new FPDF('L', 'mm', 'A4'); // 'L' for Landscape
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
$pdf->AddPage();

// ตั้งค่าฟอนต์สำหรับหัวข้อใหญ่
$pdf->SetFont('THSarabunNew', '', 16);
$pdf->Cell(0, 15, iconv('UTF-8', 'CP874', 'รายงานคำสั่งซื้อ'), 0, 1, 'C');
$pdf->Ln(8); // เว้นบรรทัดหลังจากหัวข้อ

// กำหนดฟอนต์ขนาด 12 สำหรับส่วนอื่นๆ
$pdf->SetFont('THSarabunNew', '', 12);

// กำหนดขนาดคอลัมน์
$widths = [28, 80, 15, 100, 15, 28, 23];
$totalWidth = array_sum($widths); 
$startX = (297 - $totalWidth) / 2;

$header = ['วันที่รับเข้า', 'ชื่อลูกค้า', 'W/O', 'ผลิตภัณฑ์', 'จำนวน/ชิ้น', 'วันที่จัดส่ง', 'สถานะ'];

// ตั้งค่าฟอนต์เป็นตัวหนาสำหรับหัวตาราง
$pdf->SetFillColor(25, 25, 112); 
$pdf->SetTextColor(255); 
$pdf->SetLineWidth(.3);
$pdf->SetDrawColor(192, 192, 192);

$pdf->SetX($startX);

foreach ($header as $i => $col) {
    $pdf->Cell($widths[$i], 10, iconv('UTF-8', 'CP874', $col), 1, 0, 'C', true);
}
$pdf->Ln();

// ข้อมูลคำสั่งซื้อ
$pdf->SetFillColor(230, 240, 255);
$pdf->SetTextColor(0);
$fill = false;
$orders = isset($_SESSION['orders']) ? $_SESSION['orders'] : [];
foreach ($orders as $row) {
    $pdf->SetX($startX); 
    $pdf->Cell($widths[0], 10, iconv('UTF-8', 'CP874', $row['Date_Recevied']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[1], 10, iconv('UTF-8', 'CP874', $row['Product_name']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[2], 10, iconv('UTF-8', 'CP874', $row['WO_No']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[3], 10, iconv('UTF-8', 'CP874', $row['DisplayText']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[4], 10, iconv('UTF-8', 'CP874', $row['Amount']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[5], 10, iconv('UTF-8', 'CP874', $row['Sent_Date']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[6], 10, iconv('UTF-8', 'CP874', $row['Status_Name']), 1, 0, 'C', $fill);
    $pdf->Ln();
    $fill = !$fill;
}

ob_end_clean(); // ล้างบัฟเฟอร์ก่อนสร้าง PDF
$pdf->Output('D', 'report.pdf'); // ส่งออกไฟล์ PDF
exit(); 
?>
