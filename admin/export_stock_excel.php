<?php
require_once '../db.php'; // Connect to the database
session_start();
 
// Retrieve filter values from GET parameters
$search = $_GET['search'] ?? '';  
$startDate = $_GET['start_date'] ?? '';  
$endDate = $_GET['end_date'] ?? '';  
$location = $_GET['location'] ?? '';  
 
// Start constructing the SQL query to fetch stock data
$sql = "SELECT
        s.Date_Time,
        s.WO_No,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø ', t.pipe_size, ' ', pe.PE_Name,
               IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS Product_name,
        s.Amount AS Stock_Amount,
        s.Location,
        CONCAT(c.Cus_Fname, ' ', c.Cus_Lname, ' - ', c.Project_Name) AS Customer_Name
    FROM
        stock s
    LEFT JOIN product pr ON s.Product_ID = pr.Product_ID
    LEFT JOIN product_detail pd ON pr.P_ID = pd.P_ID
    LEFT JOIN type t ON pr.T_ID = t.T_ID
    LEFT JOIN type_detail td ON t.TD_ID = td.TD_ID
    LEFT JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
    LEFT JOIN orders o ON s.WO_No = o.WO_No
    LEFT JOIN customer c ON o.Cus_ID = c.Cus_ID
    WHERE s.Amount > 0"; // Initial condition
 
// Search conditions
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
                   OR s.Location LIKE ?)";  
}
 
// Date filtering condition
if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND s.Date_Time BETWEEN ? AND ?";
}
 
// Location filtering condition
if (!empty($location)) {
    $sql .= " AND s.Location LIKE ?";
}
 
// Prepare the SQL statement
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Error preparing the SQL statement: ' . htmlspecialchars($conn->error));
}
 
// Set up the parameters for the SQL statement
$params = [];
$searchTermLike = '%' . $search . '%';
 
// If searching, prepare the parameters for LIKE
if (!empty($search)) {
    for ($i = 0; $i < 10; $i++) {
        $params[] = $searchTermLike; // For LIKE
    }
}
 
// If filtering by date
if (!empty($startDate) && !empty($endDate)) {
    $params[] = $startDate;
    $params[] = $endDate;
}
 
// If filtering by location
if (!empty($location)) {
    $params[] = '%' . $location . '%'; // Use LIKE for location
}
 
// Bind variables to the SQL statement
if (!empty($params)) {
    $types = str_repeat('s', count($params)); // Create a type string based on the number of parameters
    $stmt->bind_param($types, ...$params);
}
 
$stmt->execute();
$result = $stmt->get_result();
 
// Create Excel file
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="stock_report.xls"');
 
// Start writing the Excel XML file
echo '<?xml version="1.0"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                xmlns:o="urn:schemas-microsoft-com:office:office"
                xmlns:x="urn:schemas-microsoft-com:office:excel"
                xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
        <Worksheet ss:Name="StockReport">
            <Table>';
           
// Define column widths
echo '<Column ss:AutoFitWidth="1" ss:Width="100"/>'; // Date
echo '<Column ss:AutoFitWidth="1" ss:Width="80"/>';  // W/O
echo '<Column ss:AutoFitWidth="1" ss:Width="300"/>'; // Product name
echo '<Column ss:AutoFitWidth="1" ss:Width="80"/>';  // Amount
echo '<Column ss:AutoFitWidth="1" ss:Width="100"/>'; // Location
echo '<Column ss:AutoFitWidth="1" ss:Width="150"/>'; // Customer name
 
// Write the header row
echo '<Row>';
echo '<Cell><Data ss:Type="String">วันที่</Data></Cell>';
echo '<Cell><Data ss:Type="String">W/O</Data></Cell>';
echo '<Cell><Data ss:Type="String">ผลิตภัณฑ์</Data></Cell>';
echo '<Cell><Data ss:Type="String">จำนวน/ชิ้น</Data></Cell>';
echo '<Cell><Data ss:Type="String">สถานที่เก็บ</Data></Cell>';
echo '<Cell><Data ss:Type="String">ลูกค้า</Data></Cell>';
echo '</Row>';
 
$stocks = isset($_SESSION['stocks']) ? $_SESSION['stocks'] : [];
// Write data rows
if (!empty($stocks)) {
    foreach ($stocks as $stock) {
        echo '<Row>';
        echo '<Cell><Data ss:Type="String">' . date('d/m/Y', strtotime($stock['Date_Time'])) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($stock['WO_No']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($stock['Product_name']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="Number">' . htmlspecialchars($stock['Stock_Amount']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($stock['Location']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($stock['Customer_Name']) . '</Data></Cell>';
        echo '</Row>';
    }
} else {
    echo '<Row><Cell colspan="6"><Data ss:Type="String">ไม่มีข้อมูลที่จะแสดง</Data></Cell></Row>';
}
 
echo '    </Table>
        </Worksheet>
    </Workbook>';
?>
 