<?php
require_once '../db.php';

if (isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];

    // ดึงขั้นตอนการทำงานของผลิตภัณฑ์ที่มีแผนก Spiral เป็นลำดับแรก
    $query = "SELECT ws.St_ID, ws.St_Name, s.S_Name
              FROM work_step ws
              JOIN section s ON ws.S_ID = s.S_ID
              WHERE ws.Product_ID = ?
              AND (SELECT s2.S_Name FROM work_step ws2
                   JOIN section s2 ON ws2.S_ID = s2.S_ID
                   WHERE ws2.Product_ID = ? ORDER BY ws2.St_ID LIMIT 1) = 'Spiral'
              ORDER BY ws.St_ID ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $product_id, $product_id); // ใช้ product_id สองครั้งในเงื่อนไข
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stepCounter = 1; // ตัวนับสำหรับลำดับขั้นตอน
        while ($row = $result->fetch_assoc()) {
            echo '<div class="form-group">';
            // แสดงลำดับขั้นตอนตามด้วยชื่อแผนกและใส่ชื่อขั้นตอนในวงเล็บ
            echo '<label>' . $stepCounter . '. ' . htmlspecialchars($row['S_Name']) . ' (' . htmlspecialchars($row['St_Name']) . ')</label>';
            echo '<input type="number" name="plan[' . htmlspecialchars($row['St_ID']) . ']" placeholder="จำนวนแผนผลิต" required>';
            echo '</div>';
            $stepCounter++; // เพิ่มตัวนับลำดับขั้นตอน
        }
    } else {
        echo '<p>ไม่พบข้อมูลลำดับการทำงานสำหรับผลิตภัณฑ์นี้</p>';
    }
    $stmt->close();
}
?>
