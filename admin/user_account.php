<?php
session_start();
require_once '../db.php';

// ดึงข้อมูลผู้ใช้มาแสดงทั้งหมด
$sql = "SELECT 
            users.Firstname, 
            users.Lastname, 
            users.Tell, 
            users.Email, 
            users.Username, 
            users.Approve,
            role.R_ID, 
            role.R_Name,
            CASE
                WHEN role.R_ID IN (0, 1) THEN '-' -- Admin หรือ ผู้จัดการฝ่าย
                ELSE section.S_Name
            END as S_ID
        FROM users 
        LEFT JOIN section ON users.S_ID = section.S_ID 
        LEFT JOIN role ON users.R_ID = role.R_ID"; 

$result = $conn->query($sql);

if ($result === false) {
    die("Error fetching users: " . $conn->error); 
}

$users = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$conn->close();
include 'admin_index.html';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
    <title>Users</title>
</head>
<body>
    
        <div class="header">
        <a href="../admin/add_user.php" id="addButton" ><i class="fa fa-plus"></i> เพิ่ม</a>
        
            <h1>บัญชีผู้ใช้งาน</h1>
        </div>
        <div class="table-container">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>ชื่อ</th>
                    <th>นามสกุล</th>
                    <th>เบอร์โทร</th>
                    <th>อีเมล</th>
                    <th>ตำแหน่งงาน</th>
                    <th>แผนก</th>
                    <th>สถานะ</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="users">
                <?php $index = 1; ?>
                <?php foreach ($users as $user) : ?>
                    <tr id="user-<?php echo htmlspecialchars($user['Username']); ?>" data-approve="<?php echo htmlspecialchars($user['Approve']); ?>">
                        <td><?php echo $index++; ?></td>
                        <td><?php echo htmlspecialchars($user['Firstname']); ?></td>
                        <td><?php echo htmlspecialchars($user['Lastname']); ?></td>
                        <td><?php echo htmlspecialchars($user['Tell']); ?></td>
                        <td><?php echo htmlspecialchars($user['Email']); ?></td>
                        <td><?php echo htmlspecialchars($user['R_Name']); ?></td> <!-- แสดง R_Name -->
                        <td>
                            <?php 
                            // ตรวจสอบ R_ID และแสดงผลตามเงื่อนไข
                            if ($user['R_ID'] == 0 || $user['R_ID'] == 1) {
                                echo '-'; // ถ้าเป็น Admin หรือ ผู้จัดการฝ่าย ให้แสดง '-'
                            } else {
                                echo htmlspecialchars($user['S_ID']); // ถ้าเป็นหัวหน้าแผนก ให้แสดงชื่อแผนก
                            }
                            ?>
                        </td>

                        <td class="status">
                            <select class="status-dropdown <?php echo $user['Approve'] ? 'approved' : 'not-approved'; ?>" onchange="updateStatus('<?php echo $user['Username']; ?>', this.value, this)">
                                <option value="1" <?php echo $user['Approve'] ? 'selected' : ''; ?>>อนุมัติ</option>
                                <option value="0" <?php echo !$user['Approve'] ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                            </select>
                        </td>
                        <td class="action">
                        <a href="../admin/edit_user.php?username=<?php echo htmlspecialchars($user['Username']); ?>" class="edit-button">
                                <i class="fa-solid fa-pen"></i>
                            </a>

                            <a href="#" onclick="confirmDeleteUser('<?php echo htmlspecialchars($user['Username']); ?>')" class="delete-button">
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
                <p>คุณแน่ใจหรือไม่ว่าต้องการลบบัญชีผู้ใช้นี้?</p>
                <div class="modal-footer">
                <button class=" approve" id="confirmDelete">ตกลง</button>
                <button class=" delete" id="cancelDelete">ยกเลิก</button>
                </div>
            </div>
        </div>

<script>
    // ฟังก์ชันสำหรับการยืนยันการลบ
    let usernameToDelete = null;
    
    function confirmDeleteUser(username) {
        usernameToDelete = username;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    document.getElementById('cancelDelete').onclick = function() {
        document.getElementById('deleteModal').style.display = 'none';
        usernameToDelete = null;
    }

    document.getElementById('confirmDelete').onclick = function() {
        if (usernameToDelete) {
            fetch('delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username: usernameToDelete })
            })
            .then(response => {
                if (response.ok) {
                    document.getElementById('user-' + usernameToDelete).remove();
                } else {
                    console.error('Error deleting user:', response.statusText);
                }
                document.getElementById('deleteModal').style.display = 'none';
                usernameToDelete = null;
            })
            .catch(error => console.error('Error deleting user:', error));
        }
    }

   // ฟังก์ชันสำหรับการอัปเดตสถานะ
   function updateStatus(username, approve, selectElement) {
    const actionText = approve === '1' ? 'อนุมัติ' : 'ไม่อนุมัติ';

    Swal.fire({
        text: `คุณแน่ใจหรือไม่ว่าจะ${actionText}ผู้ใช้นี้?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2d9a47',
        cancelButtonColor: '#d33',
        confirmButtonText: 'ใช่',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('username', username);
            formData.append('approve', approve);

            fetch('approve.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // เปลี่ยนสถานะของ dropdown และ class CSS
                    if (approve === '1') {
                        selectElement.classList.remove('not-approved');
                        selectElement.classList.add('approved');
                    } else {
                        selectElement.classList.remove('approved');
                        selectElement.classList.add('not-approved');
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'สถานะผู้ใช้ได้รับการอัปเดตแล้ว',
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.message
                    });
                }
            })
            
        } else {
            // ยกเลิกการเปลี่ยนสถานะ
            selectElement.value = approve === '1' ? '0' : '1';
        }
    });
}

</script>
</body>
</html>
