<?php
require_once '../db.php';

if (isset($_POST['wo_no']) && isset($_POST['product_id'])) {
    $wo_no = $_POST['wo_no'];
    $product_id = $_POST['product_id'];

    // ดึงข้อมูลลำดับขั้นตอน (S_Name, St_Name) สำหรับผลิตภัณฑ์ตาม WO_No และ Product_ID
    $query = "SELECT ws.St_ID, ws.St_Name, s.S_Name
              FROM work_step ws
              INNER JOIN section s ON ws.S_ID = s.S_ID
              WHERE ws.Product_ID = ? 
              ORDER BY ws.S_ID";  // เรียงตามแผนก (S_ID)

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $step_number = 1; // ตัวนับลำดับการทำงาน
        while ($row = $result->fetch_assoc()) {
            echo '
            <div class="form-group">
                <label for="plan_' . htmlspecialchars($row['St_ID']) . '">' . $step_number . '. ' . htmlspecialchars($row['S_Name']) . ' (' . htmlspecialchars($row['St_Name']) . '):</label>
                <input type="number" id="plan_' . htmlspecialchars($row['St_ID']) . '" name="plan[' . htmlspecialchars($row['St_ID']) . ']" required pattern="^[1-9][0-9]*$" min="1" title="กรุณาใส่จำนวนเต็มบวกเท่านั้น">
            </div>
            ';
            $step_number++;
        }
    } else {
        echo '<p>ไม่มีข้อมูลขั้นตอนสำหรับผลิตภัณฑ์นี้</p>';
    }

    $stmt->close();
}
?>
