<?php
require_once '../db.php';
session_start();

$S_ID = 5; 

$search = ''; // ตัวแปรสำหรับเก็บค่าค้นหา

// ตรวจสอบว่ามีการค้นหาหรือไม่
if (isset($_POST['search']) && !empty(trim($_POST['search']))) {
    $search = "%" . trim($_POST['search']) . "%";
} else {
    $search = "%%"; // ค่าเริ่มต้นจะค้นหาทุกอย่าง
}

// ปรับ SQL query เพื่อรวมการค้นหาใน W/O, ผลิตภัณฑ์, และ วัน/เวลา
$sql = "SELECT ap.Act_ID, ap.WO_No, 
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' ', ' Ø', t.Pipe_Size, ' ', pe.PE_Name, '  ', 
        IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS Product_name,
        s.S_Name AS Section_Name, 
        ap.St_ID, 
        ap.PD_ID, 
        ap.Product_queue, ap.Date_Time, ap.Actual, ap.NoGood, ap.FinishGood, ap.Losstime, ap.Diff, 
        ws.St_Name, ws.cycletime, c.Working_Hours, ap.currentPlan, 
        o.note  
        FROM actual_production ap
        JOIN product pr ON pr.Product_ID = ap.Product_ID
        JOIN product_detail pd ON pr.P_ID = pd.P_ID
        JOIN type t ON pr.T_ID = t.T_ID
        JOIN type_detail td ON t.TD_ID = td.TD_ID
        JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
        JOIN section s ON ap.S_ID = s.S_ID
        JOIN work_step ws ON ap.St_ID = ws.St_ID
        JOIN calculate c ON ws.Product_ID = c.Product_ID
        LEFT JOIN orders o ON o.WO_No = ap.WO_No  -- เปลี่ยนเป็น LEFT JOIN เพื่อให้ยังแสดงแม้ว่า WO_No เป็น NULL
        WHERE ap.S_ID = ?
        AND (ap.WO_No LIKE ? OR CONCAT(pd.P_Name, ' - ', td.TD_Name) LIKE ? OR ap.Date_Time LIKE ?)
        ORDER BY ap.Date_Time DESC " ;






if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("isss", $S_ID, $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    $actual_productions = $result->fetch_all(MYSQLI_ASSOC);
} else {
    echo "Error preparing statement: " . $conn->error;
}

include 'pu_index.html';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/actual.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
    <title>PU</title>
</head>
<body>

<h2>PU</h2>
<h1>Actual Production</h1>

<div id="searchForm">
    <form method="POST">
        <label for="search">ค้นหา:</label>
        <input type="text" id="search" name="search" placeholder="">
        <button type="submit" name="searchBtn">Search</button>
    </form>
</div>
    


<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>วัน/เวลา</th>
                <th>W/O</th>
                <th>ผลิตภัณฑ์</th>
                <th>แผนก</th>
                <th>ชื่อขั้นตอน</th>
                <th>แผนผลิต</th>
                <th>รอผลิต</th>
                <th>ผลิตจริง</th>
                <th>ของเสีย/กก.</th>
                <th>เวลาที่สูญเสีย/นาที</th>
                <th>ยอดรวม</th>
                <th>ยอด+,-</th>
                <th>หมายเหตุ</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($actual_productions)): ?>
                <?php foreach ($actual_productions as $actual_production): ?>
                    <?php
                    // ตรวจสอบว่าค่า Product_queue หรือ FinishGood มีค่าหรือไม่เพื่อเปิดหรือปิดการแก้ไข
                    $is_disabled = ($actual_production['Product_queue'] == 0 && $actual_production['FinishGood'] == 0) ? 'row-disabled' : '';
                    ?>
                    <tr id="actual_production-<?php echo htmlspecialchars($actual_production['Act_ID']); ?>" class="<?php echo $is_disabled; ?>">
                        <td><?php echo date('d/m/Y H:i:s', strtotime($actual_production['Date_Time'])); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['WO_No']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['Product_name']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['Section_Name']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['St_Name']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['currentPlan']); ?></td> <!-- ค่าที่คำนวณ -->
                        <td><?php echo htmlspecialchars($actual_production['Product_queue']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['Actual']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['NoGood']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['Losstime']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['FinishGood']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['Diff']); ?></td>
                        <td><?php echo htmlspecialchars($actual_production['note']); ?></td>
                        <td class="action">
                            <?php if ($actual_production['Product_queue'] > 0 || $actual_production['FinishGood'] > 0): ?>
                                <a href="edit_pu.php?Act_ID=<?php echo htmlspecialchars($actual_production['Act_ID']); ?>" class="edit-button">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <a href="#" onclick="confirmDelete('<?php echo htmlspecialchars($actual_production['Act_ID']); ?>')" class="delete-button">
                                    <i class="bx bx-trash"></i>
                                </a>
                            <?php else: ?>
                                <!-- แสดงแถวแบบสีเทาแต่ปิดการแก้ไข -->
                                <span class="disabled-text">ไม่สามารถแก้ไขได้</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="14" class="no-data">ไม่มีข้อมูลการผลิตจริงสำหรับแผนกนี้</td>
                </tr>
            <?php endif; ?>
        </tbody>


    </table>
</div>


</body>
</html>
