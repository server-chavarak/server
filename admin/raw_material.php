<?php
require_once '../db.php';
session_start();

// Initialize search variable
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

// Base SQL query
$sql = "
    SELECT rm.*, s.Sup_Name, s.Sup_Address 
    FROM raw_material rm
    LEFT JOIN supplier s ON rm.Sup_ID = s.Sup_ID
";

// Append search condition if search input is provided
if ($search) {
    $search = $conn->real_escape_string($search); // Sanitize search input
    $sql .= " WHERE rm.Raw_Name LIKE '%$search%' 
              OR rm.Coil_No LIKE '%$search%' 
              OR rm.Amount LIKE '%$search%' 
              OR rm.Price LIKE '%$search%' 
              OR rm.Date_Recevied LIKE '%$search%' 
              OR s.Sup_Name LIKE '%$search%' 
              OR s.Sup_Address LIKE '%$search%'";
}

$result = $conn->query($sql);

if ($result === false) {
    die("Error executing query: " . $conn->error);
}

include 'admin_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/material.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script> <!-- SweetAlert2 -->
    <title>Stock วัตถุดิบ</title>
</head>
<body>

<h1>Stock วัตถุดิบ</h1>
<div id="searchForm">
    <form method="POST">
        <label for="search">ค้นหา:</label>
        <input type="text" id="search" name="search" placeholder="">
        <button type="submit" name="searchBtn">Search</button>
    </form>
</div>

<div class="header">
    <a href="add_material.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
    <a href="supplier.php" class="supplier">ข้อมูลผู้ผลิตวัตถุดิบ</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>รหัสวัตถุดิบ</th>
                <th>ชื่อวัตถุดิบ</th>
                <th>เลขล็อตคอยล์</th>
                <th>จำนวน/ชิ้น</th>
                <th>ราคา/ชิ้น</th>
                <th>วันที่รับวัตถุดิบ</th>
                <th>ผู้ผลิตวัตถุดิบ</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr id="raw_material-<?php echo htmlspecialchars($row['Raw_ID']); ?>">
                    <td><?php echo htmlspecialchars($row['Raw_ID']); ?></td>
                    <td><?php echo htmlspecialchars($row['Raw_Name']); ?></td>
                    <td><?php echo htmlspecialchars($row['Coil_No']); ?></td>
                    <td><?php echo htmlspecialchars($row['Amount']); ?></td>
                    <td><?php echo htmlspecialchars($row['Price']); ?></td>
                    <td><?php echo date('d/m/Y H:i:s', strtotime($row['Date_Recevied'])); ?></td>
                    <td><?php echo htmlspecialchars($row['Sup_Name']) ?></td>
                    <td class="action">
                        <a href="edit_material.php?Raw_ID=<?php echo htmlspecialchars($row['Raw_ID']); ?>" class="edit-button">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <a href="#" onclick="confirmDeleteMaterial('<?php echo htmlspecialchars($row['Raw_ID']); ?>')" class="delete-button">
                            <i class="bx bx-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="8">ไม่มีข้อมูลวัตถุดิบที่จะแสดง</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal for Delete Confirmation -->
<div id="deleteModal" class="modal">
    <div class="modal-delete">
        <p>คุณแน่ใจหรือไม่ว่าต้องการลบวัตถุดิบนี้?</p>
        <div class="modal-footer">
            <button class="approve" id="confirmDelete">ตกลง</button>
            <button class="delete" id="cancelDelete">ยกเลิก</button>
        </div>
    </div>
</div>

<script>
let Raw_IDToDelete = null;

function confirmDeleteMaterial(Raw_ID) {
    Raw_IDToDelete = Raw_ID;
    document.getElementById('deleteModal').style.display = 'flex';
}

document.getElementById('cancelDelete').onclick = function() {
    document.getElementById('deleteModal').style.display = 'none';
    Raw_IDToDelete = null;
}

document.getElementById('confirmDelete').onclick = function() {
    if (Raw_IDToDelete) {
        fetch('delete_material.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ Raw_ID: Raw_IDToDelete })
        })
        .then(response => {
            if (response.ok) {
                document.getElementById('raw_material-' + Raw_IDToDelete).remove();
            } else {
                response.text().then(text => {
                    console.error('Error deleting raw_material:', text);
                });
            }
            document.getElementById('deleteModal').style.display = 'none';
            Raw_IDToDelete = null;
        })
        .catch(error => console.error('Error deleting raw_material:', error));
    }
}
</script>

</body>
</html>
