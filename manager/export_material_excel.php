<?php
require_once '../db.php';
session_start();

// Initialize search and filter variables
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$Sup_ID = isset($_GET['Sup_ID']) ? $_GET['Sup_ID'] : ''; // รับค่า Sup_ID จาก GET
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : ''; // รับค่า date_from จาก GET
$coil_lot = isset($_GET['coil_lot']) ? $_GET['coil_lot'] : ''; // รับค่า coil_lot จาก GET

// Base SQL query
$sql = "
    SELECT rm.*, s.Sup_Name, s.Sup_Address 
    FROM raw_material rm
    LEFT JOIN supplier s ON rm.Sup_ID = s.Sup_ID
    WHERE 1 = 1
";

// Append search condition if search input is provided
if ($search) {
    $search = $conn->real_escape_string($search); // Sanitize search input
    $sql .= " AND (rm.Raw_Name LIKE '%$search%' 
              OR rm.Coil_No LIKE '%$search%' 
              OR rm.Amount LIKE '%$search%' 
              OR rm.Price LIKE '%$search%' 
              OR rm.Date_Recevied LIKE '%$search%' 
              OR s.Sup_Name LIKE '%$search%' 
              OR s.Sup_Address LIKE '%$search%')";
}

// Append supplier filter if selected
if ($Sup_ID) {
    $Sup_ID = $conn->real_escape_string($Sup_ID); // Sanitize supplier input
    $sql .= " AND rm.Sup_ID = '$Sup_ID'";
}

// Append date filter if selected
if ($date_from) {
    $date_from = $conn->real_escape_string($date_from); // Sanitize date input
    $sql .= " AND rm.Date_Recevied >= '$date_from'";
}

// Append coil lot filter if provided
if (!empty($coil_lot)) {
    $coil_lot = $conn->real_escape_string($coil_lot); // Sanitize coil lot input
    $sql .= " AND rm.Coil_No = '$coil_lot'"; // กรองเลขล็อตคอลย์ที่เลือกจาก Dropdown
}

$result = $conn->query($sql);

if ($result === false) {
    die("Error executing query: " . $conn->error);
}

// สร้างไฟล์ Excel (XML)
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="raw_material_export.xls"');

// เริ่มเขียน XML ที่จะใช้ใน Excel
echo '<?xml version="1.0"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                xmlns:o="urn:schemas-microsoft-com:office:office"
                xmlns:x="urn:schemas-microsoft-com:office:excel"
                xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
        <Worksheet ss:Name="RawMaterials">
            <Table>';

// กำหนดความกว้างของคอลัมน์
echo '<Column ss:AutoFitWidth="1" ss:Width="100"/>'; // คอลัมน์ที่ 1
echo '<Column ss:AutoFitWidth="1" ss:Width="150"/>'; // คอลัมน์ที่ 2
echo '<Column ss:AutoFitWidth="1" ss:Width="100"/>';  // คอลัมน์ที่ 3
echo '<Column ss:AutoFitWidth="1" ss:Width="100"/>'; // คอลัมน์ที่ 4
echo '<Column ss:AutoFitWidth="1" ss:Width="100"/>'; // คอลัมน์ที่ 5
echo '<Column ss:AutoFitWidth="1" ss:Width="150"/>'; // คอลัมน์ที่ 6

// แถวหัวตาราง
echo '<Row>';
echo '<Cell><Data ss:Type="String">รหัสวัตถุดิบ</Data></Cell>';
echo '<Cell><Data ss:Type="String">ชื่อวัตถุดิบ</Data></Cell>';
echo '<Cell><Data ss:Type="String">เลขล็อตคอยล์</Data></Cell>';
echo '<Cell><Data ss:Type="String">จำนวน/ชิ้น</Data></Cell>';
echo '<Cell><Data ss:Type="String">ราคา/บาท</Data></Cell>';
echo '<Cell><Data ss:Type="String">วันที่รับวัตถุดิบ</Data></Cell>';
echo '<Cell><Data ss:Type="String">ผู้ผลิตวัตถุดิบ</Data></Cell>';
echo '</Row>';

$raw_material = $_SESSION['material'];
foreach ($raw_material as $row) {
    echo '<Row>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Raw_ID']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Raw_Name']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Coil_No']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="Number">' . htmlspecialchars($row['Amount']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="Number">' . htmlspecialchars($row['Price']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . date('d/m/Y H:i:s', strtotime($row['Date_Recevied'])) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Sup_Name']) . " - " . htmlspecialchars($row['Sup_Address']) . '</Data></Cell>';
    echo '</Row>';
}

echo '    </Table>
        </Worksheet>
    </Workbook>';
?>
