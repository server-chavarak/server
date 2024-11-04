<?php
require('../fpdf/fpdf.php');
require_once '../db.php';
session_start();
 
ob_start(); // Start output buffering to handle headers correctly.
 
// Handle HTTP headers to prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
 
// Retrieve search parameters
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$location = $_GET['location'] ?? '';
 
// SQL query construction with basic WHERE clause
$sql = "SELECT
        s.Date_Time,
        s.WO_No,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' ', t.pipe_size, ' ', pe.PE_Name,
               IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS Product_name,
        s.Amount AS Stock_Amount,
        s.Location,
        CONCAT(c.Cus_Fname, ' ', c.Cus_Lname, ' - ', c.Project_Name) AS Customer_Name,
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
    LEFT JOIN status st ON s.Status_ID = st.Status_ID
    WHERE s.Amount > 0";
 
// Dynamic filtering based on input
if (!empty($search)) {
    $search = "%$search%";
    $sql .= " AND (c.Cus_Fname LIKE ? OR c.Cus_Lname LIKE ? OR c.Project_Name LIKE ? OR s.WO_No LIKE ? OR pd.P_Name LIKE ? OR td.TD_Name LIKE ? OR t.pipe_size LIKE ? OR pe.PE_Name LIKE ? OR t.degree LIKE ? OR s.Location LIKE ? OR st.Status_Name LIKE ? OR s.Date_Time LIKE ? OR s.Amount = ?)";
}
 
if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND s.Date_Time BETWEEN ? AND ?";
}
 
if (!empty($location)) {
    $location = "%$location%";
    $sql .= " AND s.Location LIKE ?";
}
 
// Prepare and bind
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "SQL Prepare Error: " . $conn->error;
    exit;
}
 
$params = []; // To hold dynamic binding parameters
$types = '';  // To hold the type string for binding
 
if (!empty($search)) {
    $types .= str_repeat('s', 13); // 13 search fields
    $params = array_fill(0, 13, $search);
}
 
if (!empty($startDate) && !empty($endDate)) {
    $types .= 'ss';
    $params[] = $startDate;
    $params[] = $endDate;
}
 
if (!empty($location)) {
    $types .= 's';
    $params[] = $location;
}
 
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
 
 
// PDF creation
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
$pdf->AddPage();
$pdf->SetFont('THSarabunNew', '', 16);
$pdf->Cell(0, 15, iconv('UTF-8', 'CP874', 'รายงานสินค้าคงคลังที่มีเจ้าของ'), 0, 1, 'C');
$pdf->Ln(8);
 
$pdf->SetFont('THSarabunNew', '', 12);
$widths = [30, 30, 100, 30, 40, 60];
$totalWidth = array_sum($widths);
$startX = (297 - $totalWidth) / 2;
$headers = ['วันที่', 'W/O', 'ผลิตภัณฑ์', 'จำนวน/ชิ้น', 'สถานที่เก็บ', 'ลูกค้า'];
 
$pdf->SetFillColor(25, 25, 112);
$pdf->SetTextColor(255);
$pdf->SetLineWidth(.3);
$pdf->SetDrawColor(192, 192, 192);
$pdf->SetX($startX);
foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 10, iconv('UTF-8', 'CP874', $header), 1, 0, 'C', true);
}
$pdf->Ln();
 
$pdf->SetFillColor(230, 240, 255);
$pdf->SetTextColor(0);
$fill = false;
$stocks = isset($_SESSION['stocks']) ? $_SESSION['stocks'] : [];
foreach ($stocks as $row) {
    $pdf->SetX($startX);
    foreach ([0, 1, 2, 3, 4, 5] as $index) {
        $field = ['Date_Time', 'WO_No', 'Product_name', 'Stock_Amount', 'Location', 'Customer_Name'];
        $value = ($field[$index] === 'Date_Time') ? date('d/m/Y', strtotime($row[$field[$index]])) : $row[$field[$index]];
        $pdf->Cell($widths[$index], 10, iconv('UTF-8', 'CP874', $value), 1, 0, 'C', $fill);
    }
    $pdf->Ln();
    $fill = !$fill;
}
 
ob_end_clean(); // Clean the output buffer and turn off output buffering
$pdf->Output(); // Send the PDF to the browser for download
exit();
?>