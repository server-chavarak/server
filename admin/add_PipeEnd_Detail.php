<?php
require_once '../db.php';
session_start(); // เริ่มต้น session เพื่อเก็บข้อความแจ้งเตือน

// ตรวจสอบการเรียกด้วยวิธี POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $PE_Name = isset($_POST['PE_Name']) ? $_POST['PE_Name'] : '';
    $PE_ID = isset($_POST['PE_ID']) ? $_POST['PE_ID'] : '';

    if (!empty($PE_Name)) {
        // ตรวจสอบข้อมูลซ้ำก่อนบันทึก
        $checkQuery = "SELECT * FROM pipeend_detail WHERE PE_Name = ? AND PE_ID != ?";
        $stmtCheck = $conn->prepare($checkQuery);
        $stmtCheck->bind_param("si", $PE_Name, $PE_ID);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            // พบข้อมูลซ้ำ
            $_SESSION['message'] = "ข้อมูลซ้ำ ลักษณะปลายท่อนี้มีอยู่แล้วในระบบ";
        } else {
            // ไม่มีข้อมูลซ้ำ ให้ดำเนินการบันทึกหรือแก้ไขข้อมูล
            if (!empty($PE_ID)) {
                // กรณีเป็นการแก้ไข
                $stmt = $conn->prepare("UPDATE pipeend_detail SET PE_Name = ? WHERE PE_ID = ?");
                $stmt->bind_param("si", $PE_Name, $PE_ID);
            } else {
                // กรณีเป็นการเพิ่มใหม่
                $stmt = $conn->prepare("INSERT INTO pipeend_detail (PE_Name) VALUES (?)");
                $stmt->bind_param("s", $PE_Name);
            }

            if ($stmt->execute()) {
                $_SESSION['message'] = "บันทึกข้อมูลสำเร็จ"; // เก็บข้อความแจ้งเตือนใน session
            } else {
                $_SESSION['message'] = "เกิดข้อผิดพลาด: " . $stmt->error;
            }

            $stmt->close();
        }

        $stmtCheck->close();
    } else {
        $_SESSION['message'] = "กรุณากรอกชื่อลักษณะปลายท่อ";
    }

    // หลังจากบันทึกข้อมูลเสร็จสิ้นให้ทำการ redirect ไปที่หน้าเดิม
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit(); // หยุดการทำงานของสคริปต์ที่เหลือ
}

include 'admin_index.html';

// ดึงข้อมูลลักษณะปลายท่อจากฐานข้อมูล
$query = "SELECT PE_ID, PE_Name FROM pipeend_detail";
$result = $conn->query($query);

$pipeEndList = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pipeEndList[] = $row;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Pipe End Management</title>
</head>
<body>
    <a href="../admin/add_type.php" class="back-link">ย้อนกลับ</a>
    <h1>เพิ่มลักษณะปลายท่อ</h1>

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
        <!-- ฟอร์มเพิ่มหรือแก้ไขข้อมูล -->
        <div id="addEndForm" class="add-end-form">
            <form id="endForm" method="post" action="">
                <label for="PE_Name">ลักษณะปลายท่อ</label>
                <input type="text" id="PE_Name" name="PE_Name" required>
                <input type="hidden" id="PE_ID" name="PE_ID">
                <button type="submit" id="saveEndButton">บันทึก</button>
            </form>
        </div>

        <!-- ตารางแสดงข้อมูล -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>ลักษณะปลายท่อ</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="endTableBody">
                    <?php foreach ($pipeEndList as $pipeEnd): ?>
                    <tr id="end-<?php echo htmlspecialchars($pipeEnd['PE_ID']); ?>">
                        <td><?php echo $pipeEnd['PE_ID']; ?></td>
                        <td><?php echo htmlspecialchars($pipeEnd['PE_Name']); ?></td>
                        <td class="action">
                            <!-- ลิงก์แก้ไข -->
                            <a href="edit_pipeEnd_Detail.php?PE_ID=<?php echo htmlspecialchars($pipeEnd['PE_ID']); ?>" onclick="editEnd('<?php echo htmlspecialchars($pipeEnd['PE_ID']); ?>', '<?php echo htmlspecialchars($pipeEnd['PE_Name']); ?>')" class="edit-button">
                                <i class="fa fa-pen"></i>
                            </a>


                            <!-- ลบลักษณะปลายท่อ -->
                            <a href="#" onclick="confirmDeleteEnd('<?php echo htmlspecialchars($pipeEnd['PE_ID']); ?>')" class="delete-button">
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
            <p>คุณแน่ใจหรือไม่ว่าต้องการลบลักษณะปลายท่อนี้?</p>
            <div class="modal-footer">
                <button class="approve" id="confirmDelete">ตกลง</button>
                <button class="delete" id="cancelDelete">ยกเลิก</button>
            </div>
        </div>
    </div>

    <script>
let endToDelete = null; // ตัวแปรเก็บ ID ของลักษณะปลายท่อที่จะลบ

// ฟังก์ชันสำหรับยืนยันการลบ
function confirmDeleteEnd(endID) {
    endToDelete = endID; // กำหนดค่า ID ของลักษณะปลายท่อที่จะลบ
    document.getElementById('deleteModal').style.display = 'flex'; // แสดง modal ยืนยันการลบ
}

// ยกเลิกการลบ
document.getElementById('cancelDelete').onclick = function () {
    document.getElementById('deleteModal').style.display = 'none'; // ปิด modal
    endToDelete = null; // รีเซ็ตค่า ID ที่จะลบ
};

// ยืนยันการลบ
document.getElementById('confirmDelete').onclick = function () {
    if (endToDelete) {
        fetch('delete_pipeEnd_Detail.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ ID: endToDelete })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.text();
        })
        .then(data => {
            if (data.includes('สำเร็จ')) {
                const row = document.getElementById('end-' + endToDelete);
                if (row) row.remove(); // ลบแถวออกจากตาราง
                document.getElementById('deleteModal').style.display = 'none'; // ปิด modal
                endToDelete = null; // รีเซ็ตค่า ID ที่ลบแล้ว
                Swal.fire({
                    icon: 'success',
                    title: 'ลบข้อมูลสำเร็จ',
                    showConfirmButton: false,
                    timer: 700
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถลบข้อมูลได้: ' + data
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถลบข้อมูลได้: ' + error
            });
        });
    }
};


    </script>
</body>
</html>
