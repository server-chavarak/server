<?php
session_start();
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบว่ามีการส่งค่า deleteID มาหรือไม่ (ใช้สำหรับการลบ)
    if (isset($_POST['deleteID'])) {
        $ID = intval($_POST['deleteID']);

        // ตรวจสอบว่า ID ที่ส่งมาถูกต้องหรือไม่
        if ($ID > 0) {
            // เตรียมคำสั่ง SQL สำหรับการลบข้อมูล
            $stmt = $conn->prepare("DELETE FROM Product_Detail WHERE P_ID = ?");
            $stmt->bind_param("i", $ID);

            if ($stmt->execute()) {
                echo "Product deleted successfully";
            } else {
                http_response_code(500);
                echo "Error deleting product: " . $stmt->error;
            }

            $stmt->close();
        } else {
            http_response_code(400);
            echo "Invalid ID";
        }
    }
    // ตรวจสอบว่าเป็นการบันทึกข้อมูลใหม่หรือไม่
    elseif (isset($_POST['P_Name'])) {
        $productName = $_POST['P_Name'];
        $productID = isset($_POST['P_ID']) ? $_POST['P_ID'] : '';

        if (!empty($productName)) {
            // ตรวจสอบข้อมูลซ้ำในกรณีเพิ่มใหม่หรือแก้ไข
            $checkQuery = "SELECT * FROM Product_Detail WHERE P_Name = ? AND P_ID != ?";
            $stmtCheck = $conn->prepare($checkQuery);
            $stmtCheck->bind_param("si", $productName, $productID);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();

            if ($resultCheck->num_rows > 0) {
                $_SESSION['message'] = "ข้อมูลซ้ำ: ชื่อผลิตภัณฑ์นี้มีอยู่แล้วในระบบ";
            } else {
                // ดำเนินการบันทึก (เพิ่มหรือแก้ไข)
                if (!empty($productID)) {
                    // กรณีแก้ไข
                    $stmt = $conn->prepare("UPDATE Product_Detail SET P_Name = ? WHERE P_ID = ?");
                    $stmt->bind_param("si", $productName, $productID);
                } else {
                    // เพิ่มใหม่
                    $stmt = $conn->prepare("INSERT INTO Product_Detail (P_Name) VALUES (?)");
                    $stmt->bind_param("s", $productName);
                }

                if ($stmt && $stmt->execute()) {
                    $_SESSION['message'] = "บันทึกข้อมูลสำเร็จ";
                } else {
                    $_SESSION['message'] = "เกิดข้อผิดพลาด: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmtCheck->close();
        } else {
            $_SESSION['message'] = "กรุณากรอกชื่อผลิตภัณฑ์";
        }

        // หลังจากบันทึกข้อมูลเสร็จสิ้นให้ทำการ redirect ไปที่หน้าเดิม
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ดึงข้อมูลชื่อผลิตภัณฑ์จากฐานข้อมูล
$productList = [];
$query = "SELECT P_ID, P_Name FROM Product_Detail";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $productList[] = $row;
    }
}

$conn->close();
include 'manager_index.html';
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/product.css">
    <link rel="stylesheet" href="https://sunpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Product Management</title>
</head>
<body>
    <a href="../manager/add_product.php" class="back-link">ย้อนกลับ</a>
    <h1>เพิ่มชื่อชนิด</h1>

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
                <label for="P_Name">ชื่อชนิด</label>
                <input type="hidden" id="P_ID" name="P_ID"> <!-- ฟิลด์ซ่อนสำหรับแก้ไข -->
                <input type="text" id="P_Name" name="P_Name" required>
                <button type="submit" id="saveProductButton">บันทึก</button>
            </form>
        </div>

        <!-- ตารางแสดงข้อมูล -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>ชื่อชนิด</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php foreach ($productList as $product): ?>
                    <tr id="product-<?php echo htmlspecialchars($product['P_ID']); ?>">
                        <td><?php echo $product['P_ID']; ?></td>
                        <td><?php echo htmlspecialchars($product['P_Name']); ?></td>
                        <td class="action">
                            


                            <a href="edit_Product_detail.php?P_ID=<?php echo htmlspecialchars($product['P_ID']); ?>" onclick="editEnd('<?php echo htmlspecialchars($product['P_ID']); ?>', '<?php echo htmlspecialchars($product['P_Name']); ?>')" class="edit-button">
                                <i class="fa fa-pen"></i>
                            </a>
                            <a href="#" onclick="confirmDeleteProduct('<?php echo htmlspecialchars($product['P_ID']); ?>')" class="delete-button">
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
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({ deleteID: productToDelete })
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('Product deleted successfully')) {
                    // ลบแถวออกจากตารางเมื่อข้อมูลถูกลบสำเร็จในฐานข้อมูล
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

    function editProduct(id, name) {
        document.getElementById('P_ID').value = id;
        document.getElementById('P_Name').value = name;
    }
</script>

</body>
</html>
