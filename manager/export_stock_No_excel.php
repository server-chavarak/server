<?php
require_once '../db.php'; // Connect to the database
session_start();
 
// Retrieve filter values from GET parameters
$startDate = $_GET['start_date'] ?? '';  // Start date
$endDate = $_GET['end_date'] ?? '';      // End date
$location = $_GET['location'] ?? '';      // Storage location
 
// SQL query to fetch data from stock_no_order with optional filters
$sql = "
    SELECT
        sno.StockNo_ID,
        sno.Date_Time,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø ', t.pipe_size, ' ', pe.PE_Name,
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
    WHERE
        1=1";
 
// Add conditions based on filters
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
 
// Prepare SQL statement
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Error preparing the SQL statement: ' . htmlspecialchars($conn->error));
}
 
// Bind parameters
$params = [];
$paramTypes = '';
 
if (!empty($startDate)) {
    $params[] = $startDate;
    $paramTypes .= 's'; // Date
}
if (!empty($endDate)) {
    $params[] = $endDate;
    $paramTypes .= 's'; // Date
}
if (!empty($location)) {
    $params[] = $location;
    $paramTypes .= 's'; // Location
}
 
// Bind variables to the SQL statement
if (!empty($paramTypes)) {
    $stmt->bind_param($paramTypes, ...$params);
}
 
// Execute the query
$stmt->execute();
$result = $stmt->get_result();
 
// Set the content type and filename for Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="stock_no_order_export.xls"');
 
// Start writing the Excel XML file
echo '<?xml version="1.0"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                xmlns:o="urn:schemas-microsoft-com:office:office"
                xmlns:x="urn:schemas-microsoft-com:office:excel"
                xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
        <Worksheet ss:Name="StockNoOrder">
            <Table>';
 
// Define column widths
echo '<Column ss:AutoFitWidth="1" ss:Width="100"/>'; // Date
echo '<Column ss:AutoFitWidth="1" ss:Width="150"/>'; // Product name
echo '<Column ss:AutoFitWidth="1" ss:Width="80"/>';  // Amount
echo '<Column ss:AutoFitWidth="1" ss:Width="100"/>'; // Location
 
// Write the header row
echo '<Row>';
echo '<Cell><Data ss:Type="String">วันที่</Data></Cell>';
echo '<Cell><Data ss:Type="String">ผลิตภัณฑ์</Data></Cell>';
echo '<Cell><Data ss:Type="String">จำนวน/ชิ้น</Data></Cell>';
echo '<Cell><Data ss:Type="String">สถานที่เก็บ</Data></Cell>';
echo '</Row>';
 
$stock_no_orders = $_SESSION['stock_no_orders'];
foreach ($stock_no_orders as $row) {
    echo '<Row>';
    echo '<Cell><Data ss:Type="String">' . date('d/m/Y', strtotime($row['Date_Time'])) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Product_name']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="Number">' . htmlspecialchars($row['stock_no_order_Amount']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Location']) . '</Data></Cell>';
    echo '</Row>';
}
 
echo '    </Table>
        </Worksheet>
    </Workbook>';
 
// Close the database connection
$stmt->close();
$conn->close();
?>