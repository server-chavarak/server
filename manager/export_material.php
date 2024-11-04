<?php
require_once '../db.php';
session_start();

// Initialize search and filter variables
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$Sup_ID = isset($_GET['Sup_ID']) ? $_GET['Sup_ID'] : ''; // รับค่า Sup_ID จาก GET
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : ''; // รับค่า date_from จาก GET
$coil_lot = isset($_GET['coil_lot']) ? $_GET['coil_lot'] : ''; // รับค่า coil_lot จาก GET

// Base SQL query
$sql = "SELECT rm.*, s.Sup_Name, s.Sup_Address 
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

$material = $result->fetch_all(MYSQLI_ASSOC);
// บันทึกข้อมูลผลิตภัณฑ์จริงหลังกรองลงใน session
$_SESSION['material'] = $material;
include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/export_material.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
    <title>Stock วัตถุดิบ</title>
</head>
<body>

<h2>วัตถุดิบ</h2>

<!-- ฟอร์มสำหรับการกรอง -->
<form method="GET" action="" class="filter-container">
    <!-- ฟอร์มสำหรับการกรองข้อมูล -->
    <label for="date_from">วันที่รับวัตถุดิบ:</label>
    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">

    <label for="coil_lot">เลขล็อตคอยล์:</label>
    <select id="coil_lot" name="coil_lot">
        <option value="">-- เลือกเลขล็อตคอยล์ --</option>
        <?php
        // ดึงข้อมูลเลขล็อตคอยล์จากฐานข้อมูล
        $coil_lots = $conn->query("SELECT DISTINCT Coil_No FROM raw_material WHERE Coil_No IS NOT NULL");
        while ($coil_row = $coil_lots->fetch_assoc()) {
            $selected = ($coil_row['Coil_No'] == $_GET['coil_lot']) ? "selected" : "";
            echo '<option value="' . htmlspecialchars($coil_row['Coil_No']) . '" ' . $selected . '>' . htmlspecialchars($coil_row['Coil_No']) . '</option>';
        }
        ?>
    </select>
    
    <label for="Sup_ID">ผู้ผลิตวัตถุดิบ:</label>
<select id="Sup_ID" name="Sup_ID">
    <option value="">-- เลือกผู้ผลิตวัตถุดิบ --</option>
    <?php
    // ดึงข้อมูลผู้ผลิตวัตถุดิบที่มีการเพิ่มในสต็อก
    $suppliers = $conn->query("SELECT DISTINCT s.Sup_ID, s.Sup_Name, s.Sup_Address 
        FROM supplier s 
        JOIN raw_material rm ON s.Sup_ID = rm.Sup_ID
    ");

    while ($supplier_row = $suppliers->fetch_assoc()) {
        $selected = ($supplier_row['Sup_ID'] == $_GET['Sup_ID']) ? "selected" : "";
        echo '<option value="'.$supplier_row['Sup_ID'].'" '.$selected.'>'.$supplier_row['Sup_Name'].'</option>';
    }
    ?>
</select>


    <button type="submit">กรองข้อมูล</button>


    <button type="button" class="btn-reset" onclick="window.location.href='export_material.php'">รีเซ็ต</button> 
    
        <a href="export_material_excel.php" id="btn-export"><i class="fa-solid fa-file-excel"></i></a>
        <a href="export_material_pdf.php" id="btn-export"><i class='bx bxs-file-pdf'></i></a>
 
    
</form>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>รหัสวัตถุดิบ</th>
                <th>ชื่อวัตถุดิบ</th>
                <th>เลขล็อตคอยล์</th>
                <th>จำนวน/ชิ้น</th>
                <th>ราคา/บาท</th>
                <th>วันที่รับวัตถุดิบ</th>
                <th>ผู้ผลิตวัตถุดิบ</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($material)): ?>
            <?php foreach ($material as $row): ?>
                <tr id="raw_material-<?php echo htmlspecialchars($row['Raw_ID']); ?>">
                    <td><?php echo htmlspecialchars($row['Raw_ID']); ?></td>
                    <td><?php echo htmlspecialchars($row['Raw_Name']); ?></td>
                    <td><?php echo htmlspecialchars($row['Coil_No']); ?></td>
                    <td><?php echo htmlspecialchars($row['Amount']); ?></td>
                    <td><?php echo htmlspecialchars($row['Price']); ?></td>
                    <td><?php echo date('d/m/Y H:i:s', strtotime($row['Date_Recevied'])); ?></td>
                    <td><?php echo htmlspecialchars($row['Sup_Name']); ?></td>
                    <?php endforeach; 
                    
                    ?>
            <?php else: ?>
                <tr>
                    <td colspan="12" class="no-data">ไม่มีข้อมูลการผลิตจริง</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
