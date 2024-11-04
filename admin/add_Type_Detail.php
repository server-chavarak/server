<?php

require_once '../db.php';
session_start(); // เริ่มต้น session เพื่อเก็บข้อความแจ้งเตือน

// ตรวจสอบการเรียกด้วยวิธี POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pipeTypeName = isset($_POST['pipeTypeName']) ? $_POST['pipeTypeName'] : '';

    if (!empty($pipeTypeName)) {
        // ตรวจสอบข้อมูลซ้ำก่อนบันทึก
        $checkQuery = "SELECT * FROM Type_Detail WHERE TD_Name = ?";
        $stmtCheck = $conn->prepare($checkQuery);
        $stmtCheck->bind_param("s", $pipeTypeName);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            // พบข้อมูลซ้ำ
            $_SESSION['message'] = "ข้อมูลซ้ำ ประเภทท่อนี้มีอยู่แล้วในระบบ";
        } else {
            // ไม่มีข้อมูลซ้ำ ดำเนินการบันทึกข้อมูลใหม่
            $stmt = $conn->prepare("INSERT INTO Type_Detail (TD_Name) VALUES (?)");
            
            if ($stmt) {
                $stmt->bind_param("s", $pipeTypeName);
                
                if ($stmt->execute()) {
                    $newID = $stmt->insert_id; 
                    $_SESSION['message'] = "บันทึกข้อมูลสำเร็จ"; // เก็บข้อความแจ้งเตือนใน session
                } else {
                    $_SESSION['message'] = "เกิดข้อผิดพลาด: " . $stmt->error;
                }

                $stmt->close();
            } else {
                $_SESSION['message'] = "ไม่สามารถเตรียมคำสั่งได้: " . $conn->error;
            }
        }

        $stmtCheck->close();
    } else {
        $_SESSION['message'] = "กรุณากรอกชื่อประเภทท่อ";
    }

    // หลังจากบันทึกข้อมูลเสร็จสิ้นให้ทำการ redirect ไปที่หน้าเดิม
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit(); // หยุดการทำงานของสคริปต์ที่เหลือ
}

include 'admin_index.html';

// ดึงข้อมูลประเภทท่อจากฐานข้อมูล
$typeList = [];
$query = "SELECT TD_ID, TD_Name FROM Type_Detail";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $typeList[] = $row;
    }
}

$conn->close(); // ปิดการเชื่อมต่อฐานข้อมูล
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/type.css">
    <link rel="stylesheet" href="https://sunpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=K2D">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Maitree">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Type Management</title>
</head>
<body>
    <a href="../admin/add_type.php" class="back-link">ย้อนกลับ</a>
    <h1>เพิ่มชื่อประเภทท่อ</h1>

    <!-- แสดงข้อความแจ้งเตือนเมื่อมีการบันทึกข้อมูลสำเร็จ -->
    <?php if (isset($_SESSION['message'])): ?>
    <script>
        Swal.fire({
            icon: '<?php echo strpos($_SESSION['message'], "สำเร็จ") !== false ? "success" : "error"; ?>',
            title: '<?php echo $_SESSION['message']; ?>',
            showConfirmButton: false,
            timer: 1500
        });
    </script>
    <?php unset($_SESSION['message']); // ลบข้อความแจ้งเตือนออกจาก session หลังจากแสดงผล ?>
    <?php endif; ?>

    <div class="content">
        <!-- ฟอร์มเพิ่มข้อมูล -->
        <div id="addTypeForm" class="add-type-form">
            <form id="typeForm" method="post" action="">
                <label for="pipeTypeName">ชื่อประเภทท่อ</label>
                <input type="text" id="pipeTypeName" name="pipeTypeName" required>
                <button type="submit" id="saveTypeButton">บันทึก</button>
            </form>
        </div>

        <!-- ตารางแสดงข้อมูล -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>ชื่อประเภทท่อ</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="typeTableBody">
                    <?php foreach ($typeList as $type): ?>
                    <tr id="type-<?php echo htmlspecialchars($type['TD_ID']); ?>">
                        <td><?php echo $type['TD_ID']; ?></td>
                        <td><?php echo htmlspecialchars($type['TD_Name']); ?></td>
                        <td class="action">
                            <a href="edit_type_detail.php?TD_ID=<?php echo htmlspecialchars($type['TD_ID']); ?>" class="edit-button">
                                <i class="fa fa-pen"></i>
                            </a>
                            <a href="#" onclick="confirmDeletetype('<?php echo htmlspecialchars($type['TD_ID']); ?>')" class="delete-button">
                                <i class="bx bx-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for Delete Confirmation -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-delete">
            <p>คุณแน่ใจหรือไม่ว่าต้องการลบประเภทท่อนี้?</p>
            <div class="modal-footer">
                <button class="approve" id="confirmDelete">ตกลง</button>
                <button class="delete" id="cancelDelete">ยกเลิก</button>
            </div>
        </div>
    </div>

    <script>
        let typeToDelete = null;

        function confirmDeletetype(typeID) {
            typeToDelete = typeID;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        document.getElementById('confirmDelete').onclick = function () {
    if (typeToDelete) {
        fetch('delete_Type_Detail.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ TD_ID: typeToDelete }) // ส่ง TD_ID แทน ID
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes('successfully')) {
                const row = document.getElementById('type-' + typeToDelete);
                if (row) row.remove(); // ลบแถวจากหน้าเว็บ
                document.getElementById('deleteModal').style.display = 'none';
                typeToDelete = null;
                Swal.fire({
                    icon: 'success',
                    title: 'ลบข้อมูลสำเร็จ',
                    showConfirmButton: false,
                    timer: 700
                });
            } else {
                console.error('Error deleting type:', data);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถลบข้อมูลได้: ' + data
                });
            }
        })
        .catch(error => {
            console.error('Error deleting type:', error);
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถลบข้อมูลได้: ' + error
            });
        });
    }
}

        document.getElementById('cancelDelete').onclick = function () {
            document.getElementById('deleteModal').style.display = 'none';
        }
    </script>
</body>
</html>
