<?php
require_once '../db.php';
session_start();

// ตรวจสอบว่ามีการค้นหาหรือไม่
$searchTerm = isset($_POST['search']) ? $_POST['search'] : '';

// สร้างคำสั่ง SQL สำหรับค้นหาข้อมูล
$sql = "SELECT * FROM customer WHERE 
    Cus_ID LIKE ? OR 
    Cus_Fname LIKE ? OR 
    Cus_Lname LIKE ? OR 
    Cus_Address LIKE ? OR 
    Email LIKE ? OR 
    Tell LIKE ? OR 
    Project_Name LIKE ?";

// เตรียมคำสั่ง SQL
$stmt = $conn->prepare($sql);

// ตรวจสอบว่าการเตรียมคำสั่ง SQL สำเร็จหรือไม่
if ($stmt === false) {
    die('Error preparing the SQL statement: ' . htmlspecialchars($conn->error));
}

// กำหนดค่าให้กับตัวแปรที่ใช้ในคำสั่ง SQL
$searchTermLike = '%' . $searchTerm . '%';

// ผูกตัวแปรกับคำสั่ง SQL
$stmt->bind_param("sssssss", $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike);

// เรียกใช้งานคำสั่ง SQL
$stmt->execute();

// ดึงข้อมูลผลลัพธ์
$result = $stmt->get_result();
$customers = $result->fetch_all(MYSQLI_ASSOC);

// ปิดการเชื่อมต่อ
$stmt->close();
include 'admin_index.html';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/customer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
    <title>Users</title>
</head>
<body>

<h1>ข้อมูลลูกค้า</h1>
<div id="searchForm">
        <form method="POST">
            <label for="search">ค้นหา:</label>
            <input type="text" id="search" name="search" placeholder="">
            <button type="submit" name="searchBtn">Search</button>
        </form>
    </div>
    
    <div class="header">
        <a href="add_customer.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>รหัสลูกค้า</th>
                    <th>ชื่อ</th>
                    <th>นามสกุล</th>
                    <th>ชื่อโครงการ</th>
                    <th>ที่อยู่</th>
                    <th>เบอร์โทร</th>
                    <th>อีเมล</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr id="customer-<?php echo htmlspecialchars($customer['Cus_ID']); ?>">
                        <td><?php echo htmlspecialchars($customer['Cus_ID']); ?></td>
                        <td><?php echo htmlspecialchars($customer['Cus_Fname']); ?></td>
                        <td><?php echo htmlspecialchars($customer['Cus_Lname']); ?></td>
                        <td><?php echo htmlspecialchars($customer['Project_Name']); ?></td>
                        <td><?php echo htmlspecialchars($customer['Cus_Address']); ?></td>
                        <td><?php echo htmlspecialchars($customer['Tell']); ?></td>
                        <td><?php echo htmlspecialchars($customer['Email']); ?></td>
                        <td class="action">
                            <a href="edit_customer.php?Cus_ID=<?php echo htmlspecialchars($customer['Cus_ID']); ?>" class="edit-button">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <a href="#" onclick="confirmDeleteCustomer('<?php echo htmlspecialchars($customer['Cus_ID']); ?>')" class="delete-button">
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
                <button class="approve" id="confirmDelete">ตกลง</button>
                <button class="delete" id="cancelDelete">ยกเลิก</button>
            </div>
        </div>
    </div>

    <script>
        // Function to confirm deletion of a customer
        let Cus_IDToDelete = null;

        function confirmDeleteCustomer(Cus_ID) {
            Cus_IDToDelete = Cus_ID;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        document.getElementById('cancelDelete').onclick = function() {
            document.getElementById('deleteModal').style.display = 'none';
            Cus_IDToDelete = null;
        }

        document.getElementById('confirmDelete').onclick = function() {
            if (Cus_IDToDelete) {
                fetch('delete_customer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ Cus_ID: Cus_IDToDelete })
                })
                .then(response => {
                    if (response.ok) {
                        document.getElementById('customer-' + Cus_IDToDelete).remove();
                    } else {
                        response.text().then(text => {
                            console.error('Error deleting customer:', text);
                        });
                    }
                    document.getElementById('deleteModal').style.display = 'none';
                    Cus_IDToDelete = null;
                })
                .catch(error => console.error('Error deleting customer:', error));
            }
        }
    </script>
</body>
</html>


