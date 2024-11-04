<?php
require_once '../db.php';

if (isset($_POST['wo_no'])) {
    $wo_no = $_POST['wo_no'];

    // ดึงข้อมูล Product_ID และรายละเอียดผลิตภัณฑ์จาก order_details ตาม WO_No
    $query = "SELECT p.Product_ID, 
                     CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.Pipe_Size, ' ', pe.PE_Name, ' * ', t.degree) AS display_name
              FROM order_details od
              INNER JOIN product p ON od.Product_ID = p.Product_ID
              INNER JOIN product_detail pd ON p.P_ID = pd.P_ID  -- เชื่อมกับ product_detail เพื่อดึง P_Name
              INNER JOIN type t ON p.T_ID = t.T_ID
              INNER JOIN type_detail td ON t.TD_ID = td.TD_ID  -- เชื่อมกับ type_detail เพื่อดึง TD_Name
              INNER JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID  -- เชื่อมกับ pipeend_detail เพื่อดึง PE_Name
              WHERE od.WO_No = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $wo_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo '<option value="">เลือกผลิตภัณฑ์</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($row['Product_ID']) . '">' . htmlspecialchars($row['display_name']) . '</option>';
        }
    } else {
        echo '<option value="">ไม่มีผลิตภัณฑ์ที่เกี่ยวข้อง</option>';
    }

    $stmt->close();
}

?>
