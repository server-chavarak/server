<?php
require_once '../db.php';
session_start();

// กำหนดค่า $searchTerm ให้เป็นค่าว่างถ้าไม่ได้ถูกกำหนดค่า
$searchTerm = isset($_POST['search']) ? $_POST['search'] : '';

// ใช้ SQL Query เพื่อดึงข้อมูลคำสั่งซื้อจากตารางที่เกี่ยวข้อง
$sql = "SELECT 
        o.Date_Recevied, 
        CONCAT(c.Cus_Fname, ' ', c.Cus_Lname, ' - ', c.Project_Name) AS Customer_Name,
        o.WO_No,
        GROUP_CONCAT(CONCAT(
            pd.P_Name, ' - ',
            td.TD_Name, ' ',
            ' Ø', t.Pipe_Size, 'mm. ', 
            pe.PE_Name, '  ',
            IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')
        ) ORDER BY p.Product_ID SEPARATOR '<br>') AS Product_Details,
        GROUP_CONCAT(od.Amount ORDER BY p.Product_ID SEPARATOR '<br>') AS Amounts,
        o.Sent_Date,
        o.note,  /* เพิ่มฟิลด์ note */
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
    WHERE 
        c.Cus_Fname LIKE ? 
        OR c.Cus_Lname LIKE ? 
        OR c.Project_Name LIKE ? 
        OR o.WO_No LIKE ? 
        OR pd.P_Name LIKE ?
        OR s.Status_Name LIKE ? 
        OR o.Date_Recevied LIKE ? 
        OR o.Sent_Date LIKE ?
    GROUP BY o.Date_Recevied, c.Cus_Fname, c.Cus_Lname, c.Project_Name, o.WO_No, o.Sent_Date, s.Status_Name
";


// เตรียมและผูกพารามิเตอร์การค้นหาก่อนดำเนินการ Query
$stmt = $conn->prepare($sql);
$searchTermLike = '%' . $searchTerm . '%'; // กำหนดค่า searchTerm
$stmt->bind_param("ssssssss", $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike);
$stmt->execute();
$result = $stmt->get_result();

$orders = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// ปิด statement และการเชื่อมต่อฐานข้อมูล
$stmt->close();
$conn->close();

include 'manager_index.html';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/order.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>คำสั่งสินค้า</title>
</head>
<body>
    <h1>คำสั่งซื้อสินค้า</h1>
    <div id="searchForm">
        <form method="POST">
            <label for="search">ค้นหา:</label>
            <input type="text" id="search" name="search" placeholder="">
            <button type="submit" name="searchBtn">Search</button>
        </form>
    </div>
    
    <div class="header">
        <a href="add_order.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>วันที่รับเข้า</th>
                    <th>ชื่อลูกค้า</th>
                    <th>W/O</th>
                    <th>ผลิตภัณฑ์</th>
                    <th>จำนวน/ชิ้น</th>
                    <th>วันที่จัดส่ง</th>
                    <th>สถานะ</th>
                    <th>หมายเหตุ</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <tr id="order-<?php echo htmlspecialchars($order['WO_No']); ?>">
                            <td><?php echo date('d/m/Y H:i:s', strtotime($order['Date_Recevied'])); ?></td>
                            <td><?php echo htmlspecialchars($order['Customer_Name']); ?></td>
                            <td><?php echo htmlspecialchars($order['WO_No']); ?></td>
                            <td>
                                <div class="product-info">
                                    <?php
                                    // แยก Product_Details และ Amounts
                                    $details = explode('<br>', $order['Product_Details']);
                                    $amounts = explode('<br>', $order['Amounts']);
                                    
                                    // กำหนดจำนวนแถวสูงสุด
                                    $itemsCount = max(count($details), count($amounts));
                                    ?>
                                    
                                    <?php for ($i = 0; $i < $itemsCount; $i++): ?>
                                        <div class="product-summary">
                                            <div class="product-detail">
                                                <?php echo isset($details[$i]) ? htmlspecialchars($details[$i]) : ''; ?>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </td>

                            <td>
                                <div class="product-info">
                                    <?php for ($i = 0; $itemsCount > $i; $i++): ?>
                                        <div class="product-summary">
                                            <div class="product-amount">
                                                <?php echo isset($amounts[$i]) ? htmlspecialchars($amounts[$i]) : ''; ?>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </td>

                            <!-- ตรวจสอบว่ามีค่า Sent_Date หรือไม่ -->
                            <td><?php echo ($order['Sent_Date'] && $order['Sent_Date'] != '0000-00-00 00:00:00') ? date('d/m/Y H:i:s', strtotime($order['Sent_Date'])) : ''; ?></td>


                            
                            <td>
                                <?php
                                $status = htmlspecialchars($order['Status_Name']); // แสดง Status_Name แทน Status_ID
                                $statusClass = '';

                                switch ($status) {
                                    case 'รอการผลิต':
                                        $statusClass = 'status-waiting';
                                        break;
                                    case 'ระหว่างการผลิต':
                                        $statusClass = 'status-in-progress';
                                        break;
                                    case 'ผลิตเสร็จแล้ว':
                                        $statusClass = 'status-completed';
                                        break;
                                    case 'ส่งแล้ว':
                                        $statusClass = 'status-shipped';
                                        break;
                                    default:
                                        $statusClass = ''; // กำหนดคลาสว่างถ้าสถานะไม่ตรงกับที่ระบุ
                                }
                                ?>
                                <div class="status-box <?php echo $statusClass; ?>">
                                    <?php echo $status; ?>
                                </div>
                            </td>


                            <td><?php echo htmlspecialchars($order['note']); ?></td> 


                            <td class="action">
                                <a href="edit_order.php?WO_No=<?php echo htmlspecialchars($order['WO_No']); ?>" class="edit-button">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <a href="#" onclick="confirmDeleteOrder('<?php echo htmlspecialchars($order['WO_No']); ?>')" class="delete-button">
                                    <i class="bx bx-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">ไม่มีข้อมูลคำสั่งซื้อที่จะแสดง</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal for Delete Confirmation -->
    <div id="deleteModal" class="modal">
        <div class="modal-delete">
            <p>คุณแน่ใจหรือไม่ว่าต้องการลบคำสั่งซื้อนี้?</p>
            <div class="modal-footer">
                <button class="approve" id="confirmDelete">ตกลง</button>
                <button class="delete" id="cancelDelete">ยกเลิก</button>
            </div>
        </div>
    </div>

    <script>
        let WO_NoToDelete = null;

        function confirmDeleteOrder(WO_No) {
            WO_NoToDelete = WO_No;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        document.getElementById('cancelDelete').onclick = function() {
            document.getElementById('deleteModal').style.display = 'none';
            WO_NoToDelete = null;
        }

        document.getElementById('confirmDelete').onclick = function() {
            if (WO_NoToDelete) {
                fetch('delete_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ WO_No: WO_NoToDelete })
                })
                .then(response => {
                    if (response.ok) {
                        document.getElementById('order-' +WO_NoToDelete).remove();
                    } else {
                        response.text().then(text => {
                            console.error('Error deleting order:', text);
                        });
                    }
                    document.getElementById('deleteModal').style.display = 'none';
                    WO_NoToDelete = null;
                })
                .catch(error => console.error('Error deleting order:', error));
            }
        }

        function toggleMoreInfo(link, WO_No) {
            const moreInfoDiv = document.getElementById(`more-info-${WO_No}`);
            const moreAmountsDiv = document.getElementById(`more-amounts-${WO_No}`);
            if (moreInfoDiv.style.display === 'none' || moreInfoDiv.style.display === '') {
                moreInfoDiv.style.display = 'block';
                moreAmountsDiv.style.display = 'block';
                link.innerHTML = '<i class="fa-solid fa-sort-up"></i>';
            } else {
                moreInfoDiv.style.display = 'none';
                moreAmountsDiv.style.display = 'none';
                link.innerHTML = '<i class="fa-solid fa-sort-down"></i>';
            }
        }



        
      
    </script>
</body>
</html>





