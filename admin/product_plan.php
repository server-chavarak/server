<?php
require_once '../db.php';

// ดึงข้อมูลแผนการผลิตจากฐานข้อมูล
$production_plan_query = "SELECT pp.PD_ID, pp.Date_Time, pp.WO_No, pd.P_Name, 
           CONCAT(td.TD_Name, ' Ø', t.Pipe_Size, ' ', pe.PE_Name, '  ',IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS product_details, 
           pp.Plan, s.S_Name, ws.St_Name, ws.St_ID, pp.Product_ID -- เพิ่ม pp.Product_ID เพื่อใช้ในการลบ
    FROM production_plan pp
    INNER JOIN product p ON pp.Product_ID = p.Product_ID
    INNER JOIN product_detail pd ON p.P_ID = pd.P_ID  -- เชื่อมกับ product_detail เพื่อดึง P_Name
    INNER JOIN type t ON p.T_ID = t.T_ID
    INNER JOIN type_detail td ON t.TD_ID = td.TD_ID  -- ใช้ td.TD_Name จากตาราง type_detail
    INNER JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID  -- เชื่อมกับ pipeend_detail เพื่อดึง PE_Name
    INNER JOIN section s ON pp.S_ID = s.S_ID
    INNER JOIN work_step ws ON pp.St_ID = ws.St_ID AND pp.Product_ID = ws.product_id
    ORDER BY pp.Date_Time DESC, ws.St_ID ASC;
";

$production_plan_result = mysqli_query($conn, $production_plan_query);

// ตรวจสอบข้อผิดพลาดของ SQL Query
if (!$production_plan_result) {
    die("เกิดข้อผิดพลาดในคำสั่ง SQL: " . mysqli_error($conn));
}

include 'admin_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/product_plan.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Production Plan</title>
</head>
<body>
<h1>แผนการผลิต</h1>
    <div class="header">
        <a href="add_product_plan.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>วัน/เวลา</th>
                    <th>W/O</th>
                    <th>ผลิตภัณฑ์</th>
                    <th>แผนผลิต</th>
                    <th>แผนก</th>
                    <th>ขั้นตอน</th>
                    <th class="action-column">Actions</th>
                </tr>
            </thead>
            <tbody id="productionPlanTable">
                <?php if (mysqli_num_rows($production_plan_result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($production_plan_result)): ?>
                        <tr class="production-plan-row" data-wo-no="<?php echo $row['WO_No']; ?>" data-product-id="<?php echo $row['Product_ID']; ?>" data-date-time="<?php echo $row['Date_Time']; ?>">
                            <td><?php echo date('d/m/Y H:i:s', strtotime($row['Date_Time'])); ?></td>
                            <td><?php echo htmlspecialchars($row['WO_No']); ?></td>
                            <td><?php echo htmlspecialchars($row['P_Name'] . ' - ' . $row['product_details']); ?></td>
                            <td><?php echo htmlspecialchars($row['Plan']); ?></td>
                            <td><?php echo htmlspecialchars($row['S_Name']); ?></td>
                            <td><?php echo htmlspecialchars($row['St_Name']); ?></td>
                            <td class="action">
                                <a href="edit_product_plan.php?pd_id=<?php echo $row['PD_ID']; ?>" class="edit-button">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <a href="#" onclick="confirmDeleteProduct('<?php echo $row['WO_No']; ?>', '<?php echo $row['Product_ID']; ?>', '<?php echo $row['Date_Time']; ?>')" class="delete-button">
                                    <i class="bx bx-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">ไม่มีข้อมูลแผนการผลิต</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal for Delete Confirmation -->
    <div id="deleteModal" class="modal">
        <div class="modal-delete">
            <p>คุณแน่ใจหรือไม่ว่าต้องการลบแผนผลิตนี้?</p>
            <div class="modal-footer">
                <button class="approve" id="confirmDelete">ตกลง</button>
                <button class="delete" id="cancelDelete">ยกเลิก</button>
            </div>
        </div>
    </div>

    <script>
        let WO_NoToDelete = null;
        let Product_IDToDelete = null;
        let DateTimeToDelete = null;

        function confirmDeleteProduct(WO_No, Product_ID, Date_Time) {
            WO_NoToDelete = WO_No;
            Product_IDToDelete = Product_ID;
            DateTimeToDelete = Date_Time; // เพิ่ม Date_Time ในการลบ
            document.getElementById('deleteModal').style.display = 'flex';
        }

        document.getElementById('cancelDelete').onclick = function() {
            document.getElementById('deleteModal').style.display = 'none';
            WO_NoToDelete = null;
            Product_IDToDelete = null;
            DateTimeToDelete = null;
        }

        document.getElementById('confirmDelete').onclick = function() {
            if (WO_NoToDelete && Product_IDToDelete && DateTimeToDelete) {
                fetch('delete_product_plan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ WO_No: WO_NoToDelete, Product_ID: Product_IDToDelete, Date_Time: DateTimeToDelete })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // ลบแถวที่มี WO_No, Product_ID, และ Date_Time ตรงกันจากตารางทั้งหมด
                        const rows = document.querySelectorAll(`.production-plan-row[data-wo-no='${WO_NoToDelete}'][data-product-id='${Product_IDToDelete}'][data-date-time='${DateTimeToDelete}']`);
                        rows.forEach(row => row.remove());  // ลบทุกแถวที่ตรงกัน
                    }
                    document.getElementById('deleteModal').style.display = 'none';
                    WO_NoToDelete = null;
                    Product_IDToDelete = null;
                    DateTimeToDelete = null;
                })
                .catch(error => console.error('Error:', error));
            }
        }
    </script>

</body>
</html>