<?php
require('../fpdf/fpdf.php');
require_once '../db.php';
session_start();

ob_start(); // Start output buffering at the beginning of your script

// รับค่าจากฟอร์มการกรอง
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null; 
$coil_lot = isset($_GET['coil_lot']) ? $_GET['coil_lot'] : null; 
$Sup_ID = isset($_GET['Sup_ID']) ? $_GET['Sup_ID'] : null; 

// ถ้ามีวันที่สิ้นสุด ให้เพิ่มเวลาเป็น 23:59:59
if ($date_to) {
    $date_to .= ' 23:59:59'; // เพิ่มเวลาเพื่อให้ครอบคลุมทั้งวันสุดท้าย
}
// สร้าง SQL query เบื้องต้น
$sql = "SELECT rm.*, s.Sup_Name, s.Sup_Address 
    FROM raw_material rm
    LEFT JOIN supplier s ON rm.Sup_ID = s.Sup_ID
    WHERE 1 = 1";// ค่าเริ่มต้นในการกรอง

// เงื่อนไขกรองข้อมูล
if (!empty($search)) {
    $sql .= " AND (rm.Raw_Name LIKE '%$search%' 
              OR rm.Coil_No LIKE '%$search%' 
              OR rm.Amount LIKE '%$search%' 
              OR rm.Price LIKE '%$search%' 
              OR rm.Date_Recevied LIKE '%$search%' 
              OR s.Sup_Name LIKE '%$search%' 
              OR s.Sup_Address LIKE '%$search%')";
}
if (!empty($Sup_ID)) {
    $Sup_ID = $conn->real_escape_string($Sup_ID);
    $sql .= " AND rm.Sup_ID = '$Sup_ID'";
}
if (!empty($date_from)) {
    $date_from = $conn->real_escape_string($date_from);
    $sql .= " AND rm.Date_Recevied >= '$date_from'";
}
if (!empty($coil_lot)) {
    $coil_lot = $conn->real_escape_string($coil_lot);
    $sql .= " AND rm.Coil_No = '$coil_lot'";
}

// ตรวจสอบผลลัพธ์ของการกรอง

$result = $conn->query($sql);
// $raw_materials = $result->fetch_all(MYSQLI_ASSOC);

// สร้าง PDF
$pdf = new FPDF('L', 'mm', 'A4'); // 'L' for Landscape
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
$pdf->AddPage();

// ตั้งค่าฟอนต์สำหรับหัวข้อใหญ่
$pdf->SetFont('THSarabunNew', '', 16);
$pdf->Cell(0, 15, iconv('UTF-8', 'CP874', 'รายงานวัตถุดิบ'), 0, 1, 'C');
$pdf->Ln(8); // เว้นบรรทัดหลังจากหัวข้อ

// กำหนดฟอนต์ขนาด 12 สำหรับส่วนอื่นๆ
$pdf->SetFont('THSarabunNew', '', 12);

// กำหนดขนาดคอลัมน์
$widths = [20, 40, 30, 25, 25, 50, 60];
$totalWidth = array_sum($widths); // คำนวณความกว้างรวมของตาราง
$startX = (297 - $totalWidth) / 2; // ตำแหน่ง X เพื่อให้ตารางอยู่ตรงกลาง (297 คือความกว้างของ A4 แนวนอน)
$header = ['รหัสวัตถุดิบ', 'ชื่อวัตถุดิบ', 'เลขล็อตคอยล์', 'จำนวน/ชิ้น', 'ราคา/บาท', 'วันที่รับวัตถุดิบ', 'ผู้ผลิตวัตถุดิบ'];

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

// ข้อมูลวัตถุดิบ
$pdf->SetFillColor(230, 240, 255); // สีพื้นหลังของแถวข้อมูล
$pdf->SetTextColor(0); // สีตัวอักษรของข้อมูล
$fill = false; // ใช้ในการสลับสีของแถว

$raw_material = $_SESSION['material'];
foreach ($raw_material as $row) {
    $pdf->SetX($startX); // กำหนดตำแหน่ง X ให้แถวข้อมูลอยู่ตรงกลาง
    $pdf->Cell($widths[0], 10, iconv('UTF-8', 'CP874', $row['Raw_ID']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[1], 10, iconv('UTF-8', 'CP874', $row['Raw_Name']), 1, 0, 'L', $fill);
    $pdf->Cell($widths[2], 10, iconv('UTF-8', 'CP874', $row['Coil_No']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[3], 10, iconv('UTF-8', 'CP874', $row['Amount']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[4], 10, iconv('UTF-8', 'CP874', $row['Price']), 1, 0, 'C', $fill);
    $pdf->Cell($widths[5], 10, iconv('UTF-8', 'CP874', date('d/m/Y H:i:s', strtotime($row['Date_Recevied']))), 1, 0, 'C', $fill);
    $pdf->Cell($widths[6], 10, iconv('UTF-8', 'CP874', $row['Sup_Name'] ), 1, 0, 'L', $fill);
    $pdf->Ln();
    $fill = !$fill; // สลับสีแถว
}

// ส่งออกไฟล์ PDF
ob_end_clean(); // Clean (erase) the output buffer and turn off output buffering
$pdf->Output(); // ส่งออกไฟล์ PDF
exit(); // หยุดการทำงานหลังจากส่งออก

?>