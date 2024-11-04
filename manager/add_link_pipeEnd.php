<?php
require_once '../db.php';
session_start(); // เริ่มต้น session เพื่อเก็บข้อความแจ้งเตือน

// ตรวจสอบการเรียกด้วยวิธี POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pipeEndName = isset($_POST['pipeEndName']) ? $_POST['pipeEndName'] : '';
    $pipeEndID = isset($_POST['pipeEndID']) ? $_POST['pipeEndID'] : '';

    if (!empty($pipeEndName)) {
        // ตรวจสอบข้อมูลซ้ำก่อนบันทึก
        $checkQuery = "SELECT * FROM add_pipeend WHERE Name_pipeEnd = ? AND ID != ?";
        $stmtCheck = $conn->prepare($checkQuery);
        $stmtCheck->bind_param("si", $pipeEndName, $pipeEndID);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            // พบข้อมูลซ้ำ
            $_SESSION['message'] = "ข้อมูลซ้ำ ลักษณะปลายท่อนี้มีอยู่แล้วในระบบ";
        } else {
            // ไม่มีข้อมูลซ้ำ ให้ดำเนินการบันทึกหรือแก้ไขข้อมูล
            if (!empty($pipeEndID)) {
                // กรณีเป็นการแก้ไข
                $stmt = $conn->prepare("UPDATE add_pipeend SET Name_pipeEnd = ? WHERE ID = ?");
                $stmt->bind_param("si", $pipeEndName, $pipeEndID);
            } else {
                // กรณีเป็นการเพิ่มใหม่
                $stmt = $conn->prepare("INSERT INTO add_pipeend (Name_pipeEnd) VALUES (?)");
                $stmt->bind_param("s", $pipeEndName);
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

include 'manager_index.html';

// ดึงข้อมูลลักษณะปลายท่อจากฐานข้อมูล
$query = "SELECT ID, Name_pipeEnd FROM add_pipeend";
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
    <a href="../manager/add_type.php" class="back-link">ย้อนกลับ</a>
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
                <label for="pipeEndName">ลักษณะปลายท่อ</label>
                <input type="text" id="pipeEndName" name="pipeEndName" required>
                <input type="hidden" id="pipeEndID" name="pipeEndID">
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
                    <tr id="end-<?php echo htmlspecialchars($pipeEnd['ID']); ?>">
                        <td><?php echo $pipeEnd['ID']; ?></td>
                        <td><?php echo htmlspecialchars($pipeEnd['Name_pipeEnd']); ?></td>
                        <td class="action">
                            <!-- ลิงก์แก้ไข -->
                            <a href="edit_pipeEnd.php?ID=<?php echo htmlspecialchars($pipeEnd['ID']); ?>" onclick="editEnd('<?php echo htmlspecialchars($pipeEnd['ID']); ?>', '<?php echo htmlspecialchars($pipeEnd['Name_pipeEnd']); ?>')" class="edit-button">
                                <i class="fa fa-pen"></i>
                            </a>


                            <!-- ลบลักษณะปลายท่อ -->
                            <a href="#" onclick="confirmDeleteEnd('<?php echo htmlspecialchars($pipeEnd['ID']); ?>')" class="delete-button">
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
let endToDelete = null; // Variable to store the ID of the pipe end to delete

// Function to confirm deletion
function confirmDeleteEnd(endID) {
    endToDelete = endID; // Set the ID of the pipe end to delete
    document.getElementById('deleteModal').style.display = 'flex'; // Show the delete confirmation modal
}

// Cancel deletion action
document.getElementById('cancelDelete').onclick = function () {
    document.getElementById('deleteModal').style.display = 'none'; // Hide the delete confirmation modal
    endToDelete = null; // Reset the ID to delete
};

// Confirm deletion action
document.getElementById('confirmDelete').onclick = function () {
    if (endToDelete) {
        fetch('delete_pipeEnd.php', {
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
            if (data.includes('successfully')) {
                const row = document.getElementById('end-' + endToDelete);
                if (row) row.remove();
                document.getElementById('deleteModal').style.display = 'none';
                endToDelete = null;
                Swal.fire({
                    icon: 'success',
                    title: 'ลบข้อมูลสำเร็จ',
                    showConfirmButton: false,
                    timer: 1500
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


        // ฟังก์ชันแก้ไขลักษณะปลายท่อ
        function editEnd(endID, endName) {
            document.getElementById('pipeEndID').value = endID;
            document.getElementById('pipeEndName').value = endName;
        }
    </script>
</body>
</html>
