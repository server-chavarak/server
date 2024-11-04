<?php

session_start();
require_once '../db.php';

// ดึงข้อมูลทั้งหมดจากตาราง type
$sql = "
    SELECT 
        t.T_ID, 
        td.TD_Name, 
        t.Pipe_Size, 
        pe.PE_Name, 
        t.degree 
    FROM type t
    JOIN Type_Detail td ON t.TD_ID = td.TD_ID
    JOIN PipeEnd_Detail pe ON t.PE_ID = pe.PE_ID
";

$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error); // แสดงข้อผิดพลาดหาก query ผิดพลาด
}

$typeList = array(); // ประกาศตัวแปรเพื่อเก็บข้อมูลประเภทท่อ

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $typeList[] = $row;
    }
} 
$conn->close();


include 'admin_index.html';
?>

<div class="content">

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
    <h1>ประเภทและขนาดท่อ</h1>
        <div class="header">
            <a href="add_type.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>รหัสประเภทท่อ</th>
                        <th>ชื่อประเภทท่อ</th>
                        <th>ขนาดท่อ</th>
                        <th>ลักษณะปลายท่อ</th>
                        <th>องศา</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($typeList as $type): ?>
                        <tr id="type-<?php echo htmlspecialchars($type['T_ID']); ?>">
                            <td><?php echo htmlspecialchars($type['T_ID']); ?></td>
                            <td><?php echo htmlspecialchars($type['TD_Name']); ?></td> 
                            <td><?php echo ' Ø' . htmlspecialchars($type['Pipe_Size']) . ' mm.'; ?></td>
                            <td><?php echo htmlspecialchars($type['PE_Name']); ?></td> 
                            <td>
                                <?php 
                                if (!empty($type['degree'])) {
                                    echo htmlspecialchars($type['degree']) . ' องศา';
                                } else {
                                    echo ''; 
                                }
                                ?>
                            </td>
                            <td class="action">
                                <a href="edit_type.php?T_ID=<?php echo htmlspecialchars($type['T_ID']); ?>" class="edit-button">
                                    <i class="fa fa-pen"></i>
                                </a>
                                <a href="#" onclick="confirmDeletetype('<?php echo htmlspecialchars($type['T_ID']); ?>')" class="delete-button">
                                    <i class="bx bx-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal for Delete Confirmation -->
        <div id="deleteModal" class="modal">
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

function confirmDeletetype(T_ID) {
    typeToDelete = T_ID;
    document.getElementById('deleteModal').style.display = 'flex'; // แสดง modal
}

document.getElementById('cancelDelete').onclick = function () {
    document.getElementById('deleteModal').style.display = 'none'; // ซ่อน modal
    typeToDelete = null;
}

document.getElementById('confirmDelete').onclick = function () {
    if (typeToDelete) {
        fetch('delete_type.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                T_ID: typeToDelete 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const row = document.getElementById('type-' + typeToDelete);
                if (row) row.remove();
                document.getElementById('deleteModal').style.display = 'none';
                typeToDelete = null;
                Swal.fire({
                    icon: 'success',
                    title: 'ลบข้อมูลสำเร็จ',
                    showConfirmButton: false,
                    timer: 1500
                });
            } else {
                console.error('Error deleting type:', data.message);
                Swal.fire({
                    title: 'เกิดข้อผิดพลาด!',
                    text: data.message,
                    icon: 'error',
                });
            }
        })
        .catch(error => {
            console.error('Error deleting type:', error);
            Swal.fire({
                title: 'เกิดข้อผิดพลาด!',
                text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                icon: 'error',
            });
        });
    }
}


</script>
</body>
</html>
