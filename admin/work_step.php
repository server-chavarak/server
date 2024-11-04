<?php
session_start();
require_once '../db.php';

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

// ดึงข้อมูลชื่อผลิตภัณฑ์ทั้งหมดที่มีอยู่ใน work_step
$productQuery = "SELECT DISTINCT p.Product_ID, CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.pipe_size, ' ', pe.PE_Name, ' ', t.degree) AS product_name
                 FROM product p
                 JOIN product_detail pd ON p.P_ID = pd.P_ID
                 JOIN type t ON p.T_ID = t.T_ID
                 JOIN type_detail td ON t.TD_ID = td.TD_ID
                 JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
                 JOIN work_step ws ON ws.product_id = p.Product_ID";
$productResult = $conn->query($productQuery);
if (!$productResult) {
    die('ไม่สามารถดึงข้อมูลผลิตภัณฑ์: ' . $conn->error);
}

// ตรวจสอบว่ามีการกรองหรือไม่
$filterProduct = isset($_POST['productFilter']) ? $_POST['productFilter'] : '';

// ดึงข้อมูลลำดับการทำงาน
$query = "SELECT
            ws.st_id,
            p.P_ID,
            CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.pipe_size, ' ', pe.PE_Name, ' ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS product_id,
            s.S_Name AS s_id,
            ws.st_name,
            ws.cycletime
        FROM
            work_step ws
        JOIN
            product p ON ws.product_id = p.Product_ID
        JOIN
            product_detail pd ON p.P_ID = pd.P_ID
        JOIN
            type t ON p.T_ID = t.T_ID
        JOIN
            type_detail td ON t.TD_ID = td.TD_ID
        JOIN
            pipeend_detail pe ON t.PE_ID = pe.PE_ID
        JOIN
            section s ON ws.s_id = s.s_id";

if ($filterProduct !== '') {
    $query .= " WHERE p.Product_ID = ?";
}

$query .= " ORDER BY p.P_ID, ws.st_id";
$stmt = $conn->prepare($query);

if ($filterProduct !== '') {
    $stmt->bind_param("i", $filterProduct);
}

$stmt->execute();
$result = $stmt->get_result();
$workStep = [];
while ($row = $result->fetch_assoc()) {
    $workStep[] = $row;
}

include 'admin_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Maitree">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=K2D">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/work_step.css">
    
    <!-- เพิ่ม select2 CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css" rel="stylesheet" />
    <title>ลำดับการทำงาน</title>
</head>
<body>
    <div class="header">
        ลำดับการทำงาน
    </div>

    <!-- Dropdown สำหรับกรองผลิตภัณฑ์ -->
    <div class="filter-container">
        <form method="POST" action="">
            <label for="productFilter">ค้นหา:</label>
            <select id="productFilter" name="productFilter" class="select2" onchange="this.form.submit()">
                <option value="">-- เลือกผลิตภัณฑ์ --</option>
                <?php while ($productRow = $productResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($productRow['Product_ID']); ?>" <?php echo $filterProduct == $productRow['Product_ID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($productRow['product_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <!-- ปุ่มเคลียร์การค้นหา -->
            <button type="button" id="clearFilter">ยกเลิก</button>
        </form>
    </div>

    <div class="button-container">
        <a href="add_work_step.php" onclick="loadContent('add_work_step.php'); return false;" id="addButton"><i class="fa fa-plus"></i>เพิ่มข้อมูล</a>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ผลิตภัณฑ์</th>
                    <th>ลำดับขั้นตอนการทำงาน</th>
                    <th>ชื่อขั้นตอน</th>
                    <th>แผนก</th>
                    <th>เวลาผลิต (นาที)</th>
                    <th class="action-column">Action</th>
                </tr>
            </thead>
            <tbody id="workStepTable">
            <?php
                    $stepCounter = 1;
                    $previousProductDetail = ''; // เก็บรายละเอียดของผลิตภัณฑ์ก่อนหน้า

                    foreach ($workStep as $step) {
                        // สร้างรายละเอียดของผลิตภัณฑ์ปัจจุบัน
                        $currentProductDetail = $step['product_id'];

                        // ตรวจสอบว่ารายละเอียดของผลิตภัณฑ์ปัจจุบันต่างจากผลิตภัณฑ์ก่อนหน้าหรือไม่
                        if ($currentProductDetail !== $previousProductDetail) {
                            $stepCounter = 1; // รีเซ็ตตัวนับเมื่อผลิตภัณฑ์เปลี่ยน
                            $previousProductDetail = $currentProductDetail; // อัปเดตรายละเอียดผลิตภัณฑ์ปัจจุบัน
                        }
                        ?>
                        <tr id="step-<?php echo htmlspecialchars($step['st_id']); ?>">
                            <td class="product-name" data-product-id="<?php echo htmlspecialchars($step['P_ID']); ?>">
                                <?php echo htmlspecialchars($step['product_id']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($stepCounter); ?></td> <!-- แสดงลำดับ -->
                            <td><?php echo htmlspecialchars($step['st_name']); ?></td> <!-- แสดงชื่อขั้นตอน -->
                            <td><?php echo htmlspecialchars($step['s_id']); ?></td> <!-- แสดงแผนก -->
                            <td><?php echo htmlspecialchars($step['cycletime']); ?></td> <!-- แสดงเวลาผลิต -->
                            <td class="action-column">
                                <div class="action-links">
                                    <a href="../admin/edit_work_step.php?st_id=<?php echo htmlspecialchars($step['st_id']); ?>&stepCounter=<?php echo htmlspecialchars($stepCounter); ?>" class="approve-button">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="javascript:void(0);" onclick="confirmDeleteStep('<?php echo htmlspecialchars($step['st_id']); ?>')" class="delete-button">
                                        <i class="bx bx-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php
                        $stepCounter++; // เพิ่มลำดับในขั้นตอนถัดไป
                    }
                    ?>

            </tbody>
        </table>
    </div>

    <!-- Modal for Delete Confirmation -->
    <div id="deleteModal" class="modal">
        <div class="modal-delete">
            <p>คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลของขั้นตอนนี้?</p>
            <div class="modal-footer">
                <button class="approve" id="confirmDelete">ตกลง</button>
                <button class="delete" id="cancelDelete">ยกเลิก</button>
            </div>
        </div>
    </div>

    <!-- Include jQuery and select2 JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>

    <script>
        // Initialize select2 on the productFilter dropdown
        $(document).ready(function() {
            $('#productFilter').select2({
                placeholder: "-- เลือกผลิตภัณฑ์ --",
                allowClear: true,
                width: 'resolve' // adjust dropdown width dynamically
            });

            // ปุ่มเคลียร์การค้นหา
            $('#clearFilter').click(function() {
                // เคลียร์ค่าใน dropdown และ submit ฟอร์ม
                $('#productFilter').val('').trigger('change');
            });
        });

        let stepToDelete = null;

        function confirmDeleteStep(st_id) {
            stepToDelete = st_id;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        document.getElementById('cancelDelete').onclick = function() {
            document.getElementById('deleteModal').style.display = 'none';
            stepToDelete = null;
        }

        document.getElementById('confirmDelete').onclick = function() {
            if (stepToDelete) {
                fetch('delete_work_step.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ st_id: stepToDelete })
                })
                .then(response => {
                    if (response.ok) {
                        document.getElementById('step-' + stepToDelete).remove();
                    } else {
                        alert('เกิดข้อผิดพลาดในการลบข้อมูล');
                    }
                    document.getElementById('deleteModal').style.display = 'none';
                    stepToDelete = null;
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('deleteModal').style.display = 'none';
                    stepToDelete = null;
                });
            }
        }
    </script>
</body>
</html>
