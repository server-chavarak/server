<?php
session_start();
require_once '../db.php';

// รับข้อมูลคำสั่งซื้อเมื่อมีการส่ง `WO_No`
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['WO_No'])) {
    $woNo = $_GET['WO_No'];

    // ดึงข้อมูลคำสั่งซื้อ
    $orderSql = "SELECT * FROM orders WHERE WO_No = ?";
    $stmtOrder = $conn->prepare($orderSql);
    $stmtOrder->bind_param("i", $woNo);
    $stmtOrder->execute();
    $orderResult = $stmtOrder->get_result();

    if ($orderResult->num_rows > 0) {
        $orderData = $orderResult->fetch_assoc();
        $cusID = $orderData['Cus_ID'];
        $dateReceived = $orderData['Date_Recevied'];
        $sentDate = $orderData['Sent_Date'];

        // ดึงข้อมูลรายละเอียดคำสั่งซื้อ
        $productSql = "SELECT 
            od.Product_ID, 
            od.Amount, 
            pd.P_Name,  
            td.TD_Name,
            t.Pipe_Size, 
            pe.PE_Name,  
            t.degree 
        FROM order_details od 
        JOIN product p ON od.Product_ID = p.Product_ID 
        JOIN type t ON p.T_ID = t.T_ID 
        JOIN type_detail td ON t.TD_ID = td.TD_ID 
        JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID 
        JOIN product_detail pd ON p.P_ID = pd.P_ID 
        WHERE od.WO_No = ?";

        $stmtProduct = $conn->prepare($productSql);
        if (!$stmtProduct) {
            die('Error preparing product statement: ' . $conn->error);
        }
        $stmtProduct->bind_param("i", $woNo);
        $stmtProduct->execute();
        $productResult = $stmtProduct->get_result();
        $selectedProducts = [];
        while ($product = $productResult->fetch_assoc()) {
            $selectedProducts[] = $product;
        }
    }
}

// ดึงข้อมูลสถานะจากฐานข้อมูล
$statusSql = "SELECT Status_ID, Status_Name FROM status";
$statusResult = $conn->query($statusSql);
if (!$statusResult) {
    die('ไม่สามารถดึงข้อมูลสถานะ: ' . $conn->error);
}

// เมื่อรับข้อมูลและตรวจสอบแล้วว่ามีการเลือกสถานะ
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['WO_No']) && isset($_POST['New_WO_No']) && isset($_POST['Status_ID'])) {
        $woNo = $_POST['WO_No'];
        $newWoNo = $_POST['New_WO_No']; 
        $cusID = $_POST['Cus_ID'];
        $dateReceived = $_POST['Date_Received'];
        $sentDate = $_POST['Sent_Date'];
        $note = $_POST['note'];  // รับค่า note จากฟอร์ม
        $products = json_decode($_POST['products'], true);
        $new_status = $_POST['Status_ID'];  // รับค่า Status_ID ที่เลือกจากฟอร์ม

        // ตรวจสอบว่า New_WO_No มีซ้ำหรือไม่
        $checkNewWoSql = "SELECT COUNT(*) as count FROM orders WHERE WO_No = ? AND WO_No != ?";
        $stmtCheckNewWo = $conn->prepare($checkNewWoSql);
        $stmtCheckNewWo->bind_param("ii", $newWoNo, $woNo);
        $stmtCheckNewWo->execute();
        $resultNewWo = $stmtCheckNewWo->get_result();
        $rowNewWo = $resultNewWo->fetch_assoc();

        if ($rowNewWo['count'] > 0) {
            echo json_encode(['status' => 'error', 'message' => 'W/O นี้มีการใช้งานแล้ว กรุณาเปลี่ยน W/O']);
            exit;
        } else {
            $conn->begin_transaction();
            try {
                // อัปเดตคำสั่งซื้อในตาราง orders รวมถึงช่อง note ด้วย
                $updateOrderSql = "UPDATE orders SET WO_No = ?, Cus_ID = ?, Date_Recevied = ?, Sent_Date = ?, Status_ID = ?, note = ? WHERE WO_No = ?";
                $stmtUpdateOrder = $conn->prepare($updateOrderSql);
                $stmtUpdateOrder->bind_param("isssisi", $newWoNo, $cusID, $dateReceived, $sentDate, $new_status, $note, $woNo);

                if (!$stmtUpdateOrder->execute()) {
                    throw new Exception("Error updating orders: " . $stmtUpdateOrder->error);
                }

                // ถ้า WO_No เปลี่ยนแปลง อัปเดตค่า WO_No ใน order_details
                if ($woNo !== $newWoNo) {
                    $updateDetailsSql = "UPDATE order_details SET WO_No = ? WHERE WO_No = ?";
                    $stmtUpdateDetails = $conn->prepare($updateDetailsSql);
                    $stmtUpdateDetails->bind_param("ii", $newWoNo, $woNo);
                    if (!$stmtUpdateDetails->execute()) {
                        throw new Exception("Error updating order details: " . $stmtUpdateDetails->error);
                    }
                }

                // ลบรายการสินค้าเดิมใน order_details
                $deleteOrderDetailsSql = "DELETE FROM order_details WHERE WO_No = ?";
                $stmtDeleteDetails = $conn->prepare($deleteOrderDetailsSql);
                $stmtDeleteDetails->bind_param("i", $newWoNo);
                if (!$stmtDeleteDetails->execute()) {
                    throw new Exception("Error deleting order details: " . $stmtDeleteDetails->error);
                }

                // เพิ่มรายการสินค้าใหม่
                $insertOrderDetailsSql = "INSERT INTO order_details (WO_No, Product_ID, Amount) VALUES (?, ?, ?)";
                $stmtInsertDetails = $conn->prepare($insertOrderDetailsSql);
                foreach ($products as $product) {
                    $stmtInsertDetails->bind_param("iii", $newWoNo, $product['id'], $product['amount']);
                    if (!$stmtInsertDetails->execute()) {
                        throw new Exception("Error inserting order details: " . $stmtInsertDetails->error);
                    }
                }

                // ตรวจสอบสถานะถ้าเป็น 'ส่งแล้ว'
                if ($new_status == 4) { // 4 = ส่งแล้ว
                    // อัปเดตวันที่จัดส่งเป็นวันที่ปัจจุบัน
                    $updateSentDateSql = "UPDATE orders SET Sent_Date = NOW() WHERE WO_No = ?";
                    $stmtUpdateSentDate = $conn->prepare($updateSentDateSql);
                    $stmtUpdateSentDate->bind_param("i", $woNo);
                    $stmtUpdateSentDate->execute();
                
                    // อัปเดตสถานะในตาราง stock สำหรับผลิตภัณฑ์ที่เกี่ยวข้อง
                    $updateStockSql = "UPDATE stock SET Status_ID = 4 WHERE WO_No = ? AND Product_ID = ?";
                    $stmtUpdateStock = $conn->prepare($updateStockSql);
                    foreach ($products as $product) {
                        $stmtUpdateStock->bind_param("si", $woNo, $product['id']);
                        $stmtUpdateStock->execute();
                    }
                }

                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'แก้ไขคำสั่งซื้อเรียบร้อยแล้ว']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
            }
            exit;
        }
    }
}


// Fetch customer data
$customerSql = "SELECT Cus_ID, Cus_Fname, Cus_Lname, Project_Name FROM customer";
$customerResult = $conn->query($customerSql);
if (!$customerResult) {
    die('ไม่สามารถดึงข้อมูลลูกค้า: ' . $conn->error);
}

// Fetch products
$sql = "SELECT 
        p.Product_ID, 
        pd.P_Name,
        td.TD_Name,
        t.Pipe_Size, 
        pe.PE_Name,
        t.degree 
    FROM product p 
    JOIN type t ON p.T_ID = t.T_ID 
    JOIN type_detail td ON t.TD_ID = td.TD_ID 
    JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID 
    JOIN product_detail pd ON p.P_ID = pd.P_ID";

$result = $conn->query($sql);
if (!$result) {
    die('ไม่สามารถดึงข้อมูลสินค้า: ' . $conn->error);
}
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

include 'admin_index.html';

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10/dist/sweetalert2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>

<title>Edit Order</title>
</head>
<body>
<a href="../admin/order.php" class="back-link">ย้อนกลับ</a>

<form action="edit_order.php" method="POST" id="addcustomer">
    <h1>แก้ไขคำสั่งซื้อ</h1>

    <div class="form-group">
        <label for="Date_Received">วันที่รับเข้า:</label>
        <input type="datetime-local" id="Date_Received" name="Date_Received" required value="<?php echo htmlspecialchars($dateReceived); ?>">
    </div>

    <div class="form-group">
        <label for="Cus_ID">ชื่อลูกค้า:</label>
        <select id="Cus_ID" name="Cus_ID" required>
            <option value="">--เลือกชื่อลูกค้า--</option>
            <?php while ($row = $customerResult->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['Cus_ID']); ?>" <?php echo ($row['Cus_ID'] == $cusID) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($row['Cus_Fname'] . ' ' . $row['Cus_Lname'] . '-' .$row['Project_Name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="WO_No">W/O:</label>
        <input type="hidden" id="WO_No" name="WO_No" value="<?php echo htmlspecialchars($woNo); ?>">
        <input type="text" id="New_WO_No" name="New_WO_No" required value="<?php echo htmlspecialchars($woNo); ?>">
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
        <ul id="selectedProducts" class="selected-product-list">
            <?php foreach ($selectedProducts as $product): ?>
                <li data-product-id="<?php echo htmlspecialchars($product['Product_ID']); ?>">
                <?php 
                echo htmlspecialchars(
                    $product['P_Name'] . ' - ' . 
                    $product['TD_Name'] . ' - ' . 
                    ' Ø ' . $product['Pipe_Size'] . 'mm. - ' . 
                    $product['PE_Name'] . 
                    (!empty($product['degree']) ? ' - ' . $product['degree'] . ' องศา' : '')
                ); 
                ?>
                    <input type="number" value="<?php echo htmlspecialchars($product['Amount']); ?>" min="1" step="1">
                    <button type="button" class="remove-product">ลบ</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="form-group">
        <label for="Sent_Date">วันที่จัดส่ง:</label>
        <input type="datetime-local" id="Sent_Date" name="Sent_Date" value="<?php echo ($sentDate && $sentDate != '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($sentDate)) : ''; ?>">
    </div>


    <div class="form-group">
        <label for="Status_ID">สถานะ:</label>
        <select id="Status_ID" name="Status_ID" required>
            <option value="">--เลือกสถานะ--</option>
            <?php while ($status = $statusResult->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($status['Status_ID']); ?>" <?php echo ($status['Status_ID'] == $orderData['Status_ID']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($status['Status_Name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>


    <div class="form-group">
        <label for="note">หมายเหตุ:</label>
        <textarea id="note" name="note" rows="3"><?php echo htmlspecialchars($orderData['note']); ?></textarea>
    </div>



    <!-- เพิ่ม Element สำหรับแสดงข้อความแจ้งเตือน -->
    <div id="error-message"></div>

    <div class="footer">
        <button type="submit" class="approve">แก้ไขคำสั่งซื้อ</button>
        <button type="reset" class="delete">ยกเลิก</button>
    </div>
</form>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectedProductsElement = document.getElementById('selectedProducts');
    const productSelectElement = $('#Product_ID'); // ใช้ jQuery สำหรับ Select2

    // เริ่มต้น Select2
    productSelectElement.select2({
        placeholder: '--เลือกผลิตภัณฑ์--',
        allowClear: true
    });

    $(document).ready(function() {
        $('#Cus_ID').select2({
            placeholder: "--เลือกชื่อลูกค้า--",
            allowClear: true
        });
    });

    // ฟังก์ชันการเลือกผลิตภัณฑ์
    productSelectElement.on('change', function () {
        const selectedProductID = $(this).val();
        const selectedProductText = $(this).find('option:selected').text();

        if (selectedProductID) {
            const existingProduct = selectedProductsElement.querySelector(`li[data-product-id="${selectedProductID}"]`);
            if (!existingProduct) {
                const productListItem = document.createElement('li');
                productListItem.setAttribute('data-product-id', selectedProductID);
                productListItem.innerHTML = `${selectedProductText} <input type="number" value="1" min="1" step="1"> <a type="button" class="remove-product">ลบ</a>`;
                selectedProductsElement.appendChild(productListItem);
            } else {
                Swal.fire('', 'ผลิตภัณฑ์นี้ได้ถูกเลือกแล้ว', 'warning');
            }

            // รีเซ็ต Dropdown
            productSelectElement.val(null).trigger('change');
        }
    });

    // ฟังก์ชันการลบผลิตภัณฑ์ที่เลือก
    selectedProductsElement.addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-product')) {
            event.target.parentElement.remove();
        }
    });

   // ฟังก์ชันการส่งฟอร์ม
document.getElementById('addcustomer').addEventListener('submit', function (event) {
    event.preventDefault();

    const selectedProducts = [];
    selectedProductsElement.querySelectorAll('li').forEach(function (productListItem) {
        const productId = productListItem.getAttribute('data-product-id');
        const amount = productListItem.querySelector('input').value;
        selectedProducts.push({ id: productId, amount: parseInt(amount) });
    });

    if (selectedProducts.length === 0) {
        Swal.fire('', 'กรุณาเลือกผลิตภัณฑ์อย่างน้อยหนึ่งชิ้น', 'warning');
        return;
    }

    const formData = new FormData(this);
    formData.append('products', JSON.stringify(selectedProducts));

    fetch('edit_order.php', {
    method: 'POST',
    body: formData
})
.then(response => response.text())  // Change this temporarily to text() to view raw output
.then(data => {
    console.log('Raw response:', data);  // Check what is actually being returned
    try {
        const jsonData = JSON.parse(data);
        const errorMessageElement = document.getElementById('error-message');
        if (jsonData.status === 'error') {
            errorMessageElement.innerHTML = `<p style="color: red;">${jsonData.message}</p>`;
        } else if (jsonData.status === 'success') {
            Swal.fire('สำเร็จ', jsonData.message, 'success').then(() => {
                window.location.href = '../admin/order.php';
            });
        }
    } catch (error) {
        console.error('Error parsing JSON:', error, data);
        Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดในการประมวลผลข้อมูล', 'error');
    }
})
.catch(error => {
    console.error('Fetch error:', error);
    Swal.fire('ผิดพลาด', error.message, 'error');
});


});


// ฟังก์ชันการลบ error message เมื่อเริ่มพิมพ์ข้อมูลใหม่
document.querySelectorAll('input, select').forEach(field => {
    field.addEventListener('input', function() {
        const errorMessageElement = document.getElementById('error-message');
        if (errorMessageElement) {
            errorMessageElement.innerHTML = ''; // ลบข้อความแจ้งเตือนออก
        }
    });
    
    // สำหรับฟิลด์ที่เป็น select ให้ใช้ event 'change'
    field.addEventListener('change', function() {
        const errorMessageElement = document.getElementById('error-message');
        if (errorMessageElement) {
            errorMessageElement.innerHTML = ''; // ลบข้อความแจ้งเตือนออก
        }
    });
});

});
</script>
</body>
</html>
