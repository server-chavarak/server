<?php
require_once '../db.php';
session_start();

$searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
$sql = "SELECT * FROM supplier WHERE 
    Sup_ID LIKE ? OR 
    Sup_Name LIKE ? OR 
    Sup_Address LIKE ? OR 
    Email LIKE ? OR 
    Tell LIKE ? ";
$stmt = $conn->prepare($sql);
$searchTermLike = '%' . $searchTerm . '%';
$stmt->bind_param("sssss", $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike, $searchTermLike);
$stmt->execute();
$result = $stmt->get_result();
$suppliers = $result->fetch_all(MYSQLI_ASSOC);

include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/supplier.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
    <title>ข้อมูลผู้ผลิตวัตถุดิบ</title>
</head>
<body>
<a href="../manager/raw_material.php" class="back">ย้อนกลับ</a>
<h1>ข้อมูลผู้ผลิตวัตถุดิบ</h1>
<div id="searchForm">
    <form method="POST">
        <label for="search">ค้นหา:</label>
        <input type="text" id="search" name="search" placeholder="">
        <button type="submit" name="searchBtn">Search</button>
    </form>
</div>

<div class="header">
    <a href="add_supplier.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>รหัสผู้ผลิต</th>
                <th>ชื่อผู้ผลิต</th>
                <th>ที่อยู่</th>
                <th>เบอร์โทร</th>
                <th>อีเมล</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($suppliers)): ?>
                <?php foreach ($suppliers as $supplier): ?>
                    <tr id="supplier-<?php echo htmlspecialchars($supplier['Sup_ID']); ?>">
                        <td><?php echo htmlspecialchars($supplier['Sup_ID']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['Sup_Name']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['Sup_Address']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['Tell']); ?></td>
                        <td><?php echo htmlspecialchars($supplier['Email']); ?></td>
                        <td class="action">
                            <a href="edit_supplier.php?Sup_ID=<?php echo htmlspecialchars($supplier['Sup_ID']); ?>" class="edit-button">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <a href="#" onclick="confirmDeleteSupplier('<?php echo htmlspecialchars($supplier['Sup_ID']); ?>')" class="delete-button">
                                <i class="bx bx-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">ไม่พบข้อมูลผู้ผลิตวัตถุดิบ</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal สำหรับการยืนยันการลบ -->
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
    // ฟังก์ชันสำหรับการยืนยันการลบผู้ผลิต
    let Sup_IDToDelete = null;

    function confirmDeleteSupplier(Sup_ID) {
        Sup_IDToDelete = Sup_ID;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    document.getElementById('cancelDelete').onclick = function() {
        document.getElementById('deleteModal').style.display = 'none';
        Sup_IDToDelete = null;
    }

    document.getElementById('confirmDelete').onclick = function() {
        if (Sup_IDToDelete) {
            fetch('delete_supplier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ Sup_ID: Sup_IDToDelete })
            })
            .then(response => {
                if (response.ok) {
                    document.getElementById('supplier-' + Sup_IDToDelete).remove();
                } else {
                    response.text().then(text => {
                        console.error('Error deleting supplier:', text);
                    });
                }
                document.getElementById('deleteModal').style.display = 'none';
                Sup_IDToDelete = null;
            })
            .catch(error => console.error('Error deleting supplier:', error));
        }
    }
</script>
</body>
</html>
