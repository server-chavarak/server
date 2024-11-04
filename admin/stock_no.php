<?php
require_once '../db.php'; // เชื่อมต่อฐานข้อมูล
session_start();

// รับค่าการค้นหาจากฟอร์ม
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

// ดึงข้อมูลจากตาราง stock_no_order พร้อมการกรองหากมีการค้นหา
if ($search !== '') {
    $sql = "SELECT 
            sno.StockNo_ID,
            sno.Date_Time,
            CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.pipe_size, ' ', pe.PE_Name, '  ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS Product_name,
            sno.Amount AS stock_no_order_Amount,
            sno.Location
        FROM 
            stock_no_order sno
        INNER JOIN 
            product pr ON sno.Product_ID = pr.Product_ID
        INNER JOIN 
            product_detail pd ON pr.P_ID = pd.P_ID -- เชื่อม product_detail เพื่อดึง P_Name
        INNER JOIN 
            type t ON pr.T_ID = t.T_ID
        INNER JOIN 
            type_detail td ON t.TD_ID = td.TD_ID -- เชื่อม type_detail เพื่อดึง TD_Name
        INNER JOIN 
            pipeend_detail pe ON t.PE_ID = pe.PE_ID -- เชื่อม pipeend_detail เพื่อดึง PE_Name
        WHERE 
            pd.P_Name LIKE ?
            OR td.TD_Name LIKE ?
            OR t.pipe_size LIKE ?
            OR pe.PE_Name LIKE ? -- แทนที่ t.pipe_end ด้วย pe.PE_Name
            OR t.degree LIKE ?
            OR sno.Amount LIKE ?
            OR sno.Location LIKE ?
            OR sno.Date_Time LIKE ?
        ORDER BY 
            sno.StockNo_ID ASC;
    ";


    // เตรียมคำสั่ง SQL
    $stmt = $conn->prepare($sql);

    // ตรวจสอบว่าการเตรียมคำสั่ง SQL สำเร็จหรือไม่
    if ($stmt === false) {
        die('Error preparing the SQL statement: ' . htmlspecialchars($conn->error));
    }

    // กำหนดค่าให้กับตัวแปรที่ใช้ในคำสั่ง SQL
    $searchTermLike = '%' . $search . '%';

    // ผูกตัวแปรกับคำสั่ง SQL
    $stmt->bind_param("ssss", $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike);

    // ดำเนินการคำสั่ง SQL
    $stmt->execute();
    $result = $stmt->get_result();

} else {
    // กรณีที่ไม่มีการค้นหา ให้ดึงข้อมูลทั้งหมด
    $sql = "SELECT 
        sno.StockNo_ID,
        sno.Date_Time,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.pipe_size, ' ', pe.PE_Name, ' * ', t.degree) AS Product_name,
        sno.Amount AS stock_no_order_Amount,
        sno.Location
    FROM 
        stock_no_order sno
    INNER JOIN 
        product pr ON sno.Product_ID = pr.Product_ID
    INNER JOIN 
        product_detail pd ON pr.P_ID = pd.P_ID -- เชื่อมกับ product_detail เพื่อดึง P_Name
    INNER JOIN 
        type t ON pr.T_ID = t.T_ID
    INNER JOIN 
        type_detail td ON t.TD_ID = td.TD_ID -- เชื่อมกับ type_detail เพื่อดึง TD_Name
    INNER JOIN 
        pipeend_detail pe ON t.PE_ID = pe.PE_ID -- เชื่อมกับ pipeend_detail เพื่อดึง PE_Name
    ORDER BY 
        sno.StockNo_ID DESC;
";


    $stmt = $conn->prepare($sql);

    // ตรวจสอบว่าการเตรียมคำสั่ง SQL สำเร็จหรือไม่
    if ($stmt === false) {
        die('Error preparing the SQL statement: ' . htmlspecialchars($conn->error));
    }

    // ดำเนินการคำสั่ง SQL
    $stmt->execute();
    $result = $stmt->get_result();
}

$stock_no_orders = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $stock_no_orders[] = $row; // เพิ่มข้อมูลแต่ละแถวเข้าในอาเรย์ $stock_no_orders
    }
} else {
    echo "Error retrieving records: " . mysqli_error($conn); // แสดงข้อผิดพลาดหากมีการดึงข้อมูลล้มเหลว
}

// ปิดการเชื่อมต่อฐานข้อมูล
$stmt->close();
$conn->close();

include 'admin_index.html'; // แสดงหน้าเว็บ admin_index.html ที่เกี่ยวข้อง
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/stock_no.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Stock ที่ไม่มีเจ้าของ</title>
</head>
<body>

<h1>Stock ที่ไม่มีเจ้าของ</h1>
<div id="searchForm">
        <form method="POST">
            <label for="search">ค้นหา:</label>
            <input type="text" id="search" name="search" placeholder="">
            <button type="submit" name="searchBtn">Search</button>
        </form>
    </div>

<div class="header">
    <a href="add_stock_no.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>วันที่</th>
                <th>ผลิตภัณฑ์</th>
                <th>จำนวน/ชิ้น</th>
                <th>สถานที่เก็บ</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
    <?php if (!empty($stock_no_orders)): ?>
        <?php foreach ($stock_no_orders as $stock_no_order): ?>
            <tr id="stock_no_order-<?php echo htmlspecialchars($stock_no_order['StockNo_ID']); ?>">
                <td><?php echo date('d/m/Y ', strtotime($stock_no_order['Date_Time'])); ?></td>
                <td><?php echo htmlspecialchars($stock_no_order['Product_name']); ?></td>
                <td><?php echo htmlspecialchars($stock_no_order['stock_no_order_Amount']); ?></td>
                <td><?php echo htmlspecialchars($stock_no_order['Location']); ?></td>
                <td class="action">
                    <a href="edit_stock_no.php?StockNo_ID=<?php echo htmlspecialchars($stock_no_order['StockNo_ID']); ?>" class="edit-button">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="#" onclick="confirmDeletestock_no_order('<?php echo htmlspecialchars($stock_no_order['StockNo_ID']); ?>')" class="delete-button">
                        <i class="bx bx-trash"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="5">ไม่มีข้อมูลที่จะแสดง</td>
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
let stock_no_order_IDToDelete = null;

function confirmDeletestock_no_order(StockNo_ID) {
    stock_no_order_IDToDelete = StockNo_ID;
    document.getElementById('deleteModal').style.display = 'flex';
}

document.getElementById('cancelDelete').onclick = function() {
    document.getElementById('deleteModal').style.display = 'none';
    stock_no_order_IDToDelete = null;
}

document.getElementById('confirmDelete').onclick = function() {
    if (stock_no_order_IDToDelete) {
        fetch('delete_stock_no.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ StockNo_ID: stock_no_order_IDToDelete })
        })
        .then(response => {
            if (response.ok) {
                document.getElementById('stock_no_order-' + stock_no_order_IDToDelete).remove();
            } else {
                response.text().then(text => {
                    console.error('Error deleting stock_no_order:', text);
                });
            }
            document.getElementById('deleteModal').style.display = 'none';
            stock_no_order_IDToDelete = null;
        })
        .catch(error => console.error('Error deleting stock_no_order:', error));
    }
}


</script>

</body>
</html>
