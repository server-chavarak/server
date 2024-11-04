<?php
session_start();
require_once '../db.php';

// ตรวจสอบการเรียกด้วยวิธี POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = isset($_POST['product_name']) ? $_POST['product_name'] : '';

    if (!empty($productName)) {
        // ตรวจสอบข้อมูลซ้ำก่อนบันทึก
        $checkQuery = "SELECT * FROM add_link_product WHERE product_name = ?";
        $stmtCheck = $conn->prepare($checkQuery);
        $stmtCheck->bind_param("s", $productName);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            // พบข้อมูลซ้ำ
            $_SESSION['message'] = "ข้อมูลซ้ำ: ชื่อผลิตภัณฑ์นี้มีอยู่แล้วในระบบ";
        } else {
            // ไม่มีข้อมูลซ้ำ ดำเนินการบันทึกข้อมูลใหม่
            $stmt = $conn->prepare("INSERT INTO add_link_product (product_name) VALUES (?)");
            
            if ($stmt) {
                $stmt->bind_param("s", $productName);
                
                if ($stmt->execute()) {
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
        $_SESSION['message'] = "กรุณากรอกชื่อผลิตภัณฑ์";
    }

    // หลังจากบันทึกข้อมูลเสร็จสิ้นให้ทำการ redirect ไปที่หน้าเดิม
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit(); // หยุดการทำงานของสคริปต์ที่เหลือ
}

include 'admin_index.html';

// ดึงข้อมูลชื่อผลิตภัณฑ์จากฐานข้อมูล
$productList = [];  // กำหนดค่าเริ่มต้นให้กับ $productList เป็น array ว่าง
$query = "SELECT ID, product_name FROM add_link_product";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $productList[] = $row;  // เพิ่มข้อมูลใน array $productList
    }
}

$conn->close(); // ปิดการเชื่อมต่อฐานข้อมูล
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/product.css">
    <link rel="stylesheet" href="https://sunpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=K2D">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Maitree">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Product Management</title>
</head>
<body>
    <a href="../admin/add_product.php" class="back-link">ย้อนกลับ</a>
    <h1>เพิ่มชื่อผลิตภัณฑ์</h1>

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
    <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="content">
        <!-- ฟอร์มเพิ่มข้อมูล -->
        <div id="addProductForm" class="add-product-form">
            <form id="productForm" method="post" action="">
                <label for="product_name">ชื่อผลิตภัณฑ์</label>
                <input type="text" id="product_name" name="product_name" required>
                <button type="submit" id="saveProductButton">บันทึก</button>
            </form>
        </div>

        <!-- ตารางแสดงข้อมูล -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>ชื่อผลิตภัณฑ์</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php foreach ($productList as $product): ?>
                    <tr id="product-<?php echo htmlspecialchars($product['ID']); ?>">
                        <td><?php echo $product['ID']; ?></td>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td class="action">
                            <a href="edit_linkproduct.php?ID=<?php echo htmlspecialchars($product['ID']); ?>" class="edit-button">
                                <i class="fa fa-pen"></i>
                            </a>
                            <a href="#" onclick="confirmDeleteProduct('<?php echo htmlspecialchars($product['ID']); ?>')" class="delete-button">
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
            <p>คุณแน่ใจหรือไม่ว่าต้องการลบผลิตภัณฑ์นี้?</p>
            <div class="modal-footer">
                <button class="approve" id="confirmDelete">ตกลง</button>
                <button class="delete" id="cancelDelete">ยกเลิก</button>
            </div>
        </div>
    </div>

    <script>
        let productToDelete = null;

        function confirmDeleteProduct(productID) {
            productToDelete = productID;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        document.getElementById('confirmDelete').onclick = function () {
            if (productToDelete) {
                fetch('delete_linkproduct.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({ ID: productToDelete })
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('successfully')) {
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
        }

        document.getElementById('cancelDelete').onclick = function () {
            document.getElementById('deleteModal').style.display = 'none';
        }
    </script>
</body>
</html>
