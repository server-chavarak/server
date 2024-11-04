<?php
require_once '../db.php';
session_start();

$errorMessage = '';
$successMessage = '';
$dateReceived = '';
$woNo = '';
$cusId = '';
$note = '';  // กำหนดค่าเริ่มต้นให้กับ $note
$selectedProducts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cusId = $_POST['Cus_ID'];
    $dateReceived = $_POST['Date_Received'];
    $woNo = $_POST['WO_No'];
    $note = $_POST['note'];  // รับค่าหมายเหตุจากฟอร์ม
    $statusID = 0; // กำหนดค่า Status_ID สำหรับสถานะ 'รอการผลิต'
    $selectedProducts = json_decode($_POST['selectedProducts'], true);

    // ตรวจสอบว่า WO_No มีการซ้ำกันหรือไม่
    $woNoCheckSql = "SELECT COUNT(*) AS count FROM orders WHERE WO_No = ?";
    $stmtCheck = $conn->prepare($woNoCheckSql);
    $stmtCheck->bind_param("i", $woNo);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    $rowCheck = $resultCheck->fetch_assoc();

    if ($rowCheck['count'] > 0) {
        $errorMessage = "W/O นี้มีการใช้แล้ว กรุณาเปลี่ยน";
    } else {
        // เริ่มต้นการทำธุรกรรม
        $conn->begin_transaction();

        try {
            // เตรียมคำสั่ง SQL เพื่อเพิ่มคำสั่งซื้อ พร้อมบันทึกหมายเหตุลงฐานข้อมูล
            $sql = "INSERT INTO orders (Cus_ID, Date_Recevied, Status_ID, WO_No, note) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                throw new Exception("ข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error);
            }

            $stmt->bind_param("issis", $cusId, $dateReceived, $statusID, $woNo, $note);
            
            if (!$stmt->execute()) {
                throw new Exception("เกิดข้อผิดพลาดในการเพิ่มคำสั่งซื้อ: " . $stmt->error);
            }

            // การเพิ่มรายละเอียดคำสั่งซื้อเหมือนเดิม...
            $sqlDetail = "INSERT INTO order_details (WO_No, Product_ID, Amount) VALUES (?, ?, ?)";
            $stmtDetail = $conn->prepare($sqlDetail);

            if ($stmtDetail === false) {
                throw new Exception("ข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error);
            }

            foreach ($selectedProducts as $product) {
                $productId = $product[0];
                $amount = $product[1];
                $stmtDetail->bind_param("iid", $woNo, $productId, $amount);

                if (!$stmtDetail->execute()) {
                    throw new Exception("เกิดข้อผิดพลาดในการเพิ่มรายละเอียดคำสั่งซื้อ: " . $stmtDetail->error);
                }
            }

            // ยืนยันการทำธุรกรรม
            $conn->commit();
            $successMessage = "เพิ่มคำสั่งซื้อสินค้าสำเร็จ";
        } catch (Exception $e) {
            // ยกเลิกการทำธุรกรรมในกรณีเกิดข้อผิดพลาด
            $conn->rollback();
            $errorMessage = "Error: " . $e->getMessage();
        }
    }
}


// Fetch customer data
$customerSql = "SELECT Cus_ID, Cus_Fname, Cus_Lname, Project_Name FROM customer";
$customerResult = $conn->query($customerSql);
if (!$customerResult) {
    die('Error fetching customer data: ' . $conn->error);
}

// Fetch product data
$sql = "SELECT 
        p.Product_ID, 
        pd.P_Name, -- ดึงชื่อผลิตภัณฑ์จาก product_detail
        td.TD_Name, -- ใช้ TD_Name แทน T_Name
        t.Pipe_Size, 
        pe.PE_Name, -- ดึงชื่อ Pipe End จาก pipeend_detail
        t.degree 
    FROM product p 
    JOIN type t ON p.T_ID = t.T_ID 
    JOIN type_detail td ON t.TD_ID = td.TD_ID -- JOIN เพื่อดึง TD_Name จาก type_detail
    JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID -- JOIN เพื่อดึง PE_Name จาก pipeend_detail
    JOIN product_detail pd ON p.P_ID = pd.P_ID -- JOIN เพื่อดึง P_Name จาก product_detail
";

// ตรวจสอบว่าการดึงข้อมูลสำเร็จหรือไม่
$result = $conn->query($sql);
if (!$result) {
    die('Error fetching product data: ' . $conn->error);
}

$products = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

include 'manager_index.html';
?>





<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../css/order.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<title>Add Order</title>
</head>
<body>
<a href="../manager/order.php" class="back-link">ย้อนกลับ</a>


<form action="add_order.php" method="POST" id="addcustomer">
    <h1>เพิ่มคำสั่งซื้อ</h1>

    <div class="form-group">
        <label for="Date_Received">วันที่รับเข้า:</label>
        <input type="datetime-local" id="Date_Received" name="Date_Received" required value="<?php echo htmlspecialchars($dateReceived); ?>">
    </div>

    <div class="form-group">
        <label for="Cus_ID">ชื่อลูกค้า:</label>
        <select id="Cus_ID" name="Cus_ID" required>
            <option value="">--เลือกชื่อลูกค้า--</option>
            <?php while ($row = $customerResult->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['Cus_ID']); ?>" <?php echo $row['Cus_ID'] == $cusId ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($row['Cus_Fname'] . ' ' . $row['Cus_Lname'].' - '.$row['Project_Name']  ); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>


    <div class="form-group">
        <label for="WO_No">W/O:</label>
        <input type="text" id="WO_No" name="WO_No" required value="<?php echo htmlspecialchars($woNo); ?>">
    </div>

    <div class="form-group">
    <label for="Product_ID">ผลิตภัณฑ์:</label>
    <select id="Product_ID" name="Product_ID">
        <option value="">--เลือกผลิตภัณฑ์--</option>
        <?php foreach ($products as $product): ?>
            <option value="<?php echo htmlspecialchars($product['Product_ID']); ?>">
                <?php 
                echo htmlspecialchars(
                    $product['P_Name'] . ' - ' . 
                    $product['TD_Name'] . ' - ' . 
                    ' Ø ' . $product['Pipe_Size'] . 'mm. - ' . 
                    $product['PE_Name'] . 
                    (!empty($product['degree']) ? ' - ' . $product['degree'] . ' องศา' : '')
                ); 
                ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>




    <div class="form-group">
        <label for="selectedProducts">ผลิตภัณฑ์ที่เลือก:</label>
        <ul id="selectedProducts" class="selected-product-list"></ul>
    </div>

   



    <div class="form-group">
        <label for="note">หมายเหตุ:</label>
        <textarea id="note" name="note" rows="4" cols="50"><?php echo htmlspecialchars($note); ?></textarea>
    </div>


    
<!-- เพิ่มที่แสดงข้อความข้อผิดพลาดที่นี่ -->
<?php if ($errorMessage): ?>
    <div id="error-message" class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<?php if ($successMessage): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                text: '<?php echo htmlspecialchars($successMessage); ?>'
            }).then(() => {
                window.location.href = 'order.php'; // Redirect to order list page
            });
        });
    </script>
<?php endif; ?>


    <div class="footer">
        <button type="submit" class="approve">เพิ่มสินค้า</button>
        <button type="reset" class="delete">ยกเลิก</button>
    </div>
</form>


    <script>
  document.addEventListener('DOMContentLoaded', function () {
    const selectElement = $('#Product_ID'); // ใช้ jQuery สำหรับ Select2
    const selectedProductsContainer = document.getElementById('selectedProducts');
    const selectedProductMap = new Map();

    // เริ่มต้น Select2
    selectElement.select2({
        placeholder: '--เลือกผลิตภัณฑ์--',
        allowClear: true
    });

   

    // เมื่อมีการเปลี่ยนแปลงใน dropdown
    selectElement.on('change', function () {
        const selectedProductID = $(this).val();
        if (selectedProductID) {
            const productName = $(this).find('option:selected').text();
            addProduct(selectedProductID, productName);
        }
        // ปิด dropdown หลังจากเลือกตัวเลือก
        $(this).select2('close');
    });

    function addProduct(productID, productName) {
        if (!selectedProductMap.has(productID)) {
            selectedProductMap.set(productID, { name: productName, amount: 1 });
            updateSelectedProducts();
        } else {
            Swal.fire({
                icon: 'warning',
                text: 'สินค้านี้ถูกเลือกแล้ว'
            });
        }
    }

    function updateSelectedProducts() {
        selectedProductsContainer.innerHTML = '';

        selectedProductMap.forEach((productDetails, productID) => {
            const listItem = document.createElement('li');
            listItem.classList.add('selected-product-item');

            const productNameText = document.createElement('span');
            productNameText.textContent = productDetails.name;

            const productAmountContainer = document.createElement('span');
            productAmountContainer.classList.add('product-amount-container');

            const productAmountInput = document.createElement('input');
            productAmountInput.classList.add('product-amount');
            productAmountInput.type = 'number';
            productAmountInput.min = '1';
            productAmountInput.value = productDetails.amount;
            productAmountInput.onchange = (event) => updateProductAmount(productID, event.target.value);

            const productAmountText = document.createElement('span');
            productAmountText.classList.add('product-amount-text');
            productAmountText.textContent = ' ชิ้น';

            productAmountContainer.appendChild(productAmountInput);
            productAmountContainer.appendChild(productAmountText);

            const removeBtn = document.createElement('span');
            removeBtn.classList.add('remove-btn');
            removeBtn.textContent = 'ลบ';
            removeBtn.onclick = () => removeProduct(productID);

            listItem.appendChild(productNameText);
            listItem.appendChild(productAmountContainer);
            listItem.appendChild(removeBtn);
            selectedProductsContainer.appendChild(listItem);
        });
    }

    function removeProduct(productID) {
        selectedProductMap.delete(productID);
        updateSelectedProducts();
    }

    function updateProductAmount(productID, amount) {
        if (selectedProductMap.has(productID)) {
            selectedProductMap.set(productID, {
                name: selectedProductMap.get(productID).name,
                amount: parseInt(amount, 10)
            });
        }
    }

    function resetSelectedProducts() {
        selectedProductMap.clear();
        selectedProductsContainer.innerHTML = '';
    }

    // Add event listener for the reset button
    const resetButton = document.querySelector('button[type="reset"]');
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            resetSelectedProducts();
        });
    }

    document.getElementById('addcustomer').addEventListener('submit', function (event) {
        const selectedProducts = Array.from(selectedProductMap).map(([productID, productDetails]) => [productID, productDetails.amount]);

        if (selectedProducts.length === 0) {
            Swal.fire({
                icon: 'warning',
                text: 'กรุณาเลือกผลิตภัณฑ์อย่างน้อยหนึ่งชิ้น'
            });
            event.preventDefault();
        } else {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'selectedProducts';
            hiddenInput.value = JSON.stringify(selectedProducts);
            event.target.appendChild(hiddenInput);
        }
    });

    // Clear error message on form input
    document.querySelectorAll('#addcustomer input, #addcustomer select').forEach(element => {
        element.addEventListener('input', function () {
            const errorMessage = document.getElementById('error-message');
            if (errorMessage) {
                errorMessage.remove();
            }
        });
    });

    document.getElementById('addcustomer').addEventListener('reset', function () {
        const errorMessage = document.getElementById('error-message');
        if (errorMessage) {
            errorMessage.remove();
        }
    });
});



</script>

</body>
</html>




