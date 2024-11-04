<?php
require '../db.php'; // Include your database connection
session_start();

// รับค่ากรองจากฟอร์ม (ถ้ามี)
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
$product = isset($_GET['product']) ? $_GET['product'] : ''; 
$status = isset($_GET['Status_ID']) ? $_GET['Status_ID'] : ''; 

// SQL query as used earlier
$sql = "SELECT 
        o.Date_Recevied, 
        CONCAT(c.Cus_Fname, ' ', c.Cus_Lname, ' - ', c.Project_Name) AS Product_name,
        o.WO_No,
        pd.P_Name,
        td.TD_Name,
        t.Pipe_Size,
        pe.PE_Name,
        IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(t.degree, ' องศา'), '') AS degree,
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
    WHERE (c.Cus_Fname LIKE ? OR o.WO_No LIKE ? OR o.Date_Recevied LIKE ? OR o.Sent_Date LIKE ?)";

$params = ['ssss', $search, $search, $search, $search];

if (!empty($status)) {
    $sql .= " AND o.Status_ID = ?";
    $params[0] .= 'i';
    $params[] = $status;
}

if (!empty($product)) {
    $sql .= " AND p.Product_ID = ?";
    $params[0] .= 'i';
    $params[] = $product;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param(...$params);
$stmt->execute();
$result = $stmt->get_result();

// สร้างไฟล์ Excel (XML)
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="order_export.xls"');

// เริ่มเขียน XML ที่จะใช้ใน Excel
echo '<?xml version="1.0"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                xmlns:o="urn:schemas-microsoft-com:office:office"
                xmlns:x="urn:schemas-microsoft-com:office:excel"
                xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
        <Worksheet ss:Name="Orders">
            <Table>';

// กำหนดความกว้างของคอลัมน์
echo '<Column ss:AutoFitWidth="1" ss:Width="120"/>'; // กำหนดความกว้างให้คอลัมน์ที่ 1
echo '<Column ss:AutoFitWidth="1" ss:Width="150"/>'; // กำหนดความกว้างให้คอลัมน์ที่ 2
echo '<Column ss:AutoFitWidth="1" ss:Width="80"/>';  // กำหนดความกว้างให้คอลัมน์ที่ 3
echo '<Column ss:AutoFitWidth="1" ss:Width="350"/>'; // กำหนดความกว้างให้คอลัมน์ที่ 4
echo '<Column ss:AutoFitWidth="1" ss:Width="80"/>';  // กำหนดความกว้างให้คอลัมน์ที่ 5
echo '<Column ss:AutoFitWidth="1" ss:Width="120"/>'; // กำหนดความกว้างให้คอลัมน์ที่ 6
echo '<Column ss:AutoFitWidth="1" ss:Width="120"/>'; // กำหนดความกว้างให้คอลัมน์ที่ 7

// แถวหัวตาราง
echo '<Row>';
echo '<Cell><Data ss:Type="String">วันที่รับเข้า</Data></Cell>';
echo '<Cell><Data ss:Type="String">ชื่อลูกค้า</Data></Cell>';
echo '<Cell><Data ss:Type="String">W/O</Data></Cell>';
echo '<Cell><Data ss:Type="String">ผลิตภัณฑ์</Data></Cell>';
echo '<Cell><Data ss:Type="String">จำนวน/ชิ้น</Data></Cell>';
echo '<Cell><Data ss:Type="String">วันที่จัดส่ง</Data></Cell>';
echo '<Cell><Data ss:Type="String">สถานะ</Data></Cell>';
echo '</Row>';

$orders = isset($_SESSION['orders']) ? $_SESSION['orders'] : [];
foreach ($orders as $row) {
    echo '<Row>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Date_Recevied']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Product_name']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['WO_No']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['P_Name'] . ' - ' . $row['TD_Name'] . ' Ø' . $row['Pipe_Size'] . 'mm. - ' . $row['PE_Name'] . ' ' . $row['degree']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="Number">' . htmlspecialchars($row['Amount']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Sent_Date']) . '</Data></Cell>';
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($row['Status_Name']) . '</Data></Cell>';
    echo '</Row>';
}

echo '    </Table>
        </Worksheet>
    </Workbook>';
?>
