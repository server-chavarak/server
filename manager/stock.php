<?php
require_once '../db.php';
session_start();

// รับค่าการค้นหาจากฟอร์ม (ถ้ามี)
$search = isset($_POST['search']) ? $_POST['search'] : '';

// SQL สำหรับดึงข้อมูล stock รวมถึง `Status_ID`
$sql = "SELECT 
        s.Stock_ID,
        s.Date_Time,
        s.WO_No,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.pipe_size, ' ', pe.PE_Name, '  ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS Product_name,
        s.Amount AS Stock_Amount,
        s.Location,
        CONCAT(c.Cus_Fname, ' ', c.Cus_Lname, ' - ', c.Project_Name) AS Customer_Name,
        COALESCE(pp.Plan, 0) AS Plan,
        s.Status_ID,  
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
    LEFT JOIN production_plan pp ON s.WO_No = pp.WO_No AND s.Product_ID = pp.Product_ID
    LEFT JOIN status st ON s.Status_ID = st.Status_ID
    WHERE
        (c.Cus_Fname LIKE ? 
        OR c.Cus_Lname LIKE ? 
        OR c.Project_Name LIKE ?
        OR s.WO_No LIKE ? 
        OR pd.P_Name LIKE ? 
        OR td.TD_Name LIKE ? 
        OR t.pipe_size LIKE ? 
        OR pe.PE_Name LIKE ? 
        OR t.degree LIKE ? 
        OR s.Location LIKE ? 
        OR st.Status_Name LIKE ? 
        OR s.Date_Time LIKE ?  
        OR s.Amount = ?)  
    AND s.Amount > 0
    GROUP BY 
        DATE(s.Date_Time), s.WO_No, s.Product_ID  
    ORDER BY 
        s.Stock_ID ASC";


// เตรียมคำสั่ง SQL
$stmt = $conn->prepare($sql);

// ตรวจสอบว่าการเตรียมคำสั่ง SQL สำเร็จหรือไม่
if ($stmt === false) {
    die('Error preparing the SQL statement: ' . htmlspecialchars($conn->error));
}

// กำหนดค่าให้กับตัวแปรที่ใช้ในคำสั่ง SQL
$searchTermLike = '%' . $search . '%';

// ผูกตัวแปรกับคำสั่ง SQL
$stmt->bind_param(
    "ssssssssssssd", 
    $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike,
    $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike,
    $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $search
);

// เรียกใช้งานคำสั่ง SQL
if (!$stmt->execute()) {
    die('Error executing the SQL statement: ' . htmlspecialchars($stmt->error));
}

// ดึงข้อมูลผลลัพธ์
$result = $stmt->get_result();
if ($result === false) {
    die('Error getting result set: ' . htmlspecialchars($stmt->error));
}

// เก็บข้อมูลแต่ละแถวในอาเรย์ $stocks
$stocks = [];
while ($row = $result->fetch_assoc()) {
    $stocks[] = $row;
}

$stmt->close();
$conn->close();

// นำข้อมูลมาแสดงในหน้า HTML
include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/stock.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Stock</title>
</head>
<body>

<h1>Stock ที่มีเจ้าของ</h1>
<div id="searchForm">
    <form method="POST">
        <label for="search">ค้นหา:</label>
        <input type="text" id="search" name="search" placeholder="">
        <button type="submit" name="searchBtn">Search</button>
    </form>
</div>

<div class="header">
    <a href="add_stock.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>วันที่</th>
                <th>W/O</th>
                <th>ผลิตภัณฑ์</th>
                <th>จำนวน/ชิ้น</th>
                <th>สถานที่เก็บ</th>
                <th>ลูกค้า</th>
                <th>สถานะ</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
    <?php if (!empty($stocks)): ?>
        <?php foreach ($stocks as $stock): ?>
            <tr id="stock-<?php echo htmlspecialchars($stock['Stock_ID']); ?>">
                <td><?php echo date('d/m/Y ', strtotime($stock['Date_Time'])); ?></td>
                <td><?php echo htmlspecialchars($stock['WO_No']); ?></td>
                <td><?php echo htmlspecialchars($stock['Product_name']); ?></td>
                <td><?php echo htmlspecialchars($stock['Stock_Amount']); ?></td>
                <td><?php echo htmlspecialchars($stock['Location']); ?></td>
                <td><?php echo htmlspecialchars($stock['Customer_Name']); ?></td>
                <td>
                    <?php
                    // แสดงสถานะตาม `Status_ID`
                    $status = htmlspecialchars($stock['Status_Name']); 
                    $statusClass = '';

                    switch ($stock['Status_ID']) {
                        case 1:  // ระหว่างการผลิต
                            $statusClass = 'status-in-progress';
                            $status = 'ระหว่างการผลิต';
                            break;
                        case 3:  // ผลิตเสร็จแล้ว
                            $statusClass = 'status-completed';
                            $status = 'ผลิตเสร็จแล้ว';
                            break;
                        case 4:  // ส่งแล้ว
                            $statusClass = 'status-shipped';
                            $status = 'ส่งแล้ว';
                            break;
                        default:
                            $statusClass = 'status-unknown'; // Default class for unknown status
                            $status = 'ไม่ทราบสถานะ';
                    }
                    ?>
                    <div class="status-box <?php echo $statusClass; ?>">
                        <?php echo $status; ?>
                    </div>
                </td>
                                
                <td class="action">
                    <a href="edit_stock.php?Stock_ID=<?php echo htmlspecialchars($stock['Stock_ID']); ?>" class="edit-button">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="#" onclick="confirmDeleteStock('<?php echo htmlspecialchars($stock['Stock_ID']); ?>')" class="delete-button">
                        <i class="bx bx-trash"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="8">ไม่มีข้อมูลที่จะแสดง</td>
        </tr>
    <?php endif; ?>
</tbody>
    </table>
    </div>

<!-- Modal for Delete Confirmation -->
<div id="deleteModal" class="modal">
    <div class="modal-delete">
        <p>คุณแน่ใจหรือไม่ว่าต้องการลบ stock นี้?</p>
        <div class="modal-footer">
            <button class="approve" id="confirmDelete">ตกลง</button>
            <button class="delete" id="cancelDelete">ยกเลิก</button>
        </div>
    </div>
</div>

<script>
// ฟังก์ชันสำหรับจัดการการลบข้อมูล
let Stock_IDToDelete = null;

function confirmDeleteStock(Stock_ID) {
    Stock_IDToDelete = Stock_ID;
    document.getElementById('deleteModal').style.display = 'flex';
}

document.getElementById('cancelDelete').onclick = function() {
    document.getElementById('deleteModal').style.display = 'none';
    Stock_IDToDelete = null;
}

document.getElementById('confirmDelete').onclick = function() {
    if (Stock_IDToDelete) {
        fetch('delete_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ Stock_ID: Stock_IDToDelete })
        })
        .then(response => {
            if (response.ok) {
                document.getElementById('stock-' + Stock_IDToDelete).remove();
            } else {
                response.text().then(text => {
                    console.error('Error deleting stock:', text);
                });
            }
            document.getElementById('deleteModal').style.display = 'none';
            Stock_IDToDelete = null;
        })
        .catch(error => console.error('Error deleting stock:', error));
    }
}
</script>

</body>
</html>
