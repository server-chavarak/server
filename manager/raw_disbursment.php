<?php
require_once '../db.php';
session_start();

// Initialize search term
$searchTerm = '';
if (isset($_POST['searchBtn'])) {
    $searchTerm = $_POST['search'];
}

// Fetch data for display
$sql = "SELECT raw_disbursment.*, section.S_Name, raw_material.Raw_Name, raw_material.Coil_No, orders.WO_No
        FROM raw_disbursment
        JOIN section ON raw_disbursment.S_ID = section.S_ID
        JOIN raw_material ON raw_disbursment.Raw_ID = raw_material.Raw_ID
        JOIN orders ON raw_disbursment.WO_No = orders.WO_No
        WHERE raw_disbursment.Date_Time LIKE ? 
           OR raw_material.Raw_Name LIKE ? 
           OR orders.WO_No LIKE ? 
           OR raw_disbursment.Amount LIKE ? 
           OR section.S_Name LIKE ?";

// Prepare and execute statement
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing SQL query: " . $conn->error);
}

$searchWildcard = '%' . $searchTerm . '%';
$stmt->bind_param('sssss', $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard);

if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}

$result = $stmt->get_result();

if (!$result) {
    echo "Query failed: " . $conn->error;
    exit();
}

include 'manager_index.html';
?>


<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/disbursment.css">
    <title>Raw Disbursment</title>
</head>
<body>

<h1>เบิกวัตถุดิบ</h1>
    <div id="searchForm">
        <form method="POST">
            <label for="search">ค้นหา:</label>
            <input type="text" id="search" name="search" placeholder="">
            <button type="submit" name="searchBtn">Search</button>
        </form>
    </div>
    
    <div class="header">
        <a href="add_disbursment.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
    </div>

<div class="table-container">
<table>
    <thead>
        <tr>
            <th>วันที่</th>
            <th>ชื่อวัตถุดิบ</th>
            <th>W/O</th>
            <th>จำนวน/ชิ้น</th>
            <th>แผนก</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr id="raw_disbursment-<?php echo htmlspecialchars($row['RD_ID']); ?>">
                <td><?php echo date('d/m/Y H:i:s', strtotime($row['Date_Time'])); ?></td>
                <td><?php echo htmlspecialchars($row['Raw_Name']) . ' - ' . htmlspecialchars($row['Coil_No']); ?></td>
                <td><?php echo htmlspecialchars($row['WO_No']); ?></td>
                <td><?php echo htmlspecialchars($row['Amount']); ?></td>
                <td><?php echo htmlspecialchars($row['S_Name']); ?></td>
                <td class="action">
                    <a href="edit_disbursment.php?RD_ID=<?php echo htmlspecialchars($row['RD_ID']); ?>" class="edit-button">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="#" onclick="confirmDeleteOrder('<?php echo htmlspecialchars($row['RD_ID']); ?>')" class="delete-button">
                        <i class="bx bx-trash"></i>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="6">ไม่มีข้อมูลการเบิกวัตถุดิบที่จะแสดง</td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Modal for Delete Confirmation -->
<div id="deleteModal" class="modal">
    <div class="modal-delete">
        <p>คุณแน่ใจหรือไม่ว่าต้องการลบคำสั่งซื้อนี้?</p>
        <div class="modal-footer">
            <button class="approve" id="confirmDelete">ตกลง</button>
            <button class="delete" id="cancelDelete">ยกเลิก</button>
        </div>
    </div>
</div>

<script>
    let RD_IDToDelete = null;

    function confirmDeleteOrder(RD_ID) {
        RD_IDToDelete = RD_ID;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    document.getElementById('cancelDelete').onclick = function() {
        document.getElementById('deleteModal').style.display = 'none';
        RD_IDToDelete = null;
    }

    document.getElementById('confirmDelete').onclick = function() {
        if (RD_IDToDelete) {
            fetch('delete_disbursment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ RD_ID: RD_IDToDelete })
            })
            .then(response => {
                if (response.ok) {
                    document.getElementById('raw_disbursment-' + RD_IDToDelete).remove();
                } else {
                    response.text().then(text => {
                        console.error('Error deleting disbursment:', text);
                    });
                }
                document.getElementById('deleteModal').style.display = 'none';
                RD_IDToDelete = null;
            })
            .catch(error => console.error('Error deleting disbursment:', error));
        }
    }
</script>

</body>
</html>
