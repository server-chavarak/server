<?php
session_start();
require_once '../db.php';

// ดึงข้อมูลจากตาราง product โดยเชื่อมกับ Product_Detail และ type_detail
$query = "SELECT 
    p.Product_ID,
    pd.P_Name AS P_Name,  -- ใช้ชื่อผลิตภัณฑ์จากตาราง Product_Detail
    t.T_ID, 
    CONCAT('Ø', t.pipe_Size, 'mm') AS pipe_Size, 
    pe.PE_Name AS pipe_End,  -- ใช้ชื่อปลายท่อจากตาราง PipeEnd_Detail
    IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(t.degree, ' องศา'), '') AS degree,
    CONCAT(td.TD_Name, ' - Ø', t.Pipe_Size, 'mm. - ', pe.PE_Name, 
           IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS DisplayText
FROM 
    product p
INNER JOIN 
    product_detail pd ON p.P_ID = pd.P_ID  -- เชื่อมโยงกับ Product_Detail ด้วย P_ID
INNER JOIN 
    type t ON p.T_ID = t.T_ID
INNER JOIN 
    type_detail td ON t.TD_ID = td.TD_ID  -- เชื่อมโยงกับ type_detail ด้วย TD_ID
INNER JOIN 
    PipeEnd_Detail pe ON t.PE_ID = pe.PE_ID";  // เชื่อมตาราง PipeEnd_Detail เพื่อดึงข้อมูลปลายท่อ

// รันคำสั่ง SQL
$result = mysqli_query($conn, $query);

if (!$result) {
    die('Query Error: ' . mysqli_error($conn));
}

$product_details = [];
while ($row = mysqli_fetch_assoc($result)) {
    $product_details[] = $row;
}

$conn->close();
include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/product.css">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=K2D">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Maitree">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>ผลิตภัณฑ์</title>
</head>
<body>

<h1>ผลิตภัณฑ์</h1>
<div class="header">
    <a href="add_product.php" id="addButton"><i class="fa fa-plus"></i> เพิ่ม</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>รหัสผลิตภัณฑ์</th>
                <th>ชื่อผลิตภัณฑ์</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($product_details as $detail): ?>
            <tr id="product-<?php echo htmlspecialchars($detail['Product_ID']); ?>">
                <td><?php echo htmlspecialchars($detail['Product_ID']); ?></td>
                <td>
                    <?php echo htmlspecialchars($detail['P_Name']); ?> - 
                    <?php echo htmlspecialchars($detail['DisplayText']); ?>
                </td>

                <td class="action">
                    <a href="edit_product.php?Product_ID=<?php echo htmlspecialchars($detail['Product_ID']); ?>" class="edit-button">
                        <i class="fa fa-pen"></i>
                    </a>
                    <a href="#" onclick="confirmDeleteProduct('<?php echo htmlspecialchars($detail['Product_ID']); ?>')" class="delete-button">
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
        <p>คุณแน่ใจหรือไม่ว่าต้องการลบผลิตภัณฑ์นี้?</p>
        <div class="modal-footer">
            <button class="approve" id="confirmDelete">ตกลง</button>
            <button class="delete" id="cancelDelete">ยกเลิก</button>
        </div>
    </div>
</div>

<script>
let productToDelete = null;

function confirmDeleteProduct(Product_ID) {
    productToDelete = Product_ID;
    document.getElementById('deleteModal').style.display = 'flex';
}

document.getElementById('cancelDelete').onclick = function () {
    document.getElementById('deleteModal').style.display = 'none';
    productToDelete = null;
}

document.getElementById('confirmDelete').onclick = function () {
    if (productToDelete) {
        const formData = new FormData();
        formData.append('Product_ID', productToDelete);

        fetch('delete_product.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            console.log('Response data:', data);
            if (data.includes('success')) {
                const row = document.getElementById('product-' + productToDelete);
                if (row) row.remove();

                document.getElementById('deleteModal').style.display = 'none';
                productToDelete = null;

                Swal.fire({
                    icon: 'success',
                    title: 'ลบข้อมูลสำเร็จ',
                    showConfirmButton: false,
                    timer: 700
                });

            } else {
                console.error('Error deleting product:', data);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data
                });
            }
        })
        .catch(error => {
            console.error('Error deleting product:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'ไม่สามารถลบข้อมูลได้: ' + error
            });
        });
    }
}
</script>

</body>
</html>
