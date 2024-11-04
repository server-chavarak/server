<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลที่ส่งมาจาก AJAX
    $data = json_decode(file_get_contents('php://input'), true);

    $wo_no = $data['WO_No'];
    $product_id = $data['Product_ID'];
    $date_time = $data['Date_Time']; // รับ Date_Time ที่ส่งมาจาก client

    if (!empty($wo_no) && !empty($product_id) && !empty($date_time)) {
        // ลบข้อมูลทั้งหมดที่มี WO_No, Product_ID และ Date_Time ตรงกัน
        $conn->begin_transaction();
        try {
            // แปลงวันที่ให้อยู่ในรูปแบบที่สามารถเปรียบเทียบกับฐานข้อมูลได้
            $formatted_date_time = date('Y-m-d H:i:s', strtotime($date_time));

            // ลบข้อมูลใน actual_production ตาม WO_No, Product_ID และ Date_Time
            $delete_actual_query = "DELETE FROM actual_production WHERE WO_No = ? AND Product_ID = ? AND Date_Time = ?";
            $stmt_actual = $conn->prepare($delete_actual_query);
            $stmt_actual->bind_param('iis', $wo_no, $product_id, $formatted_date_time);
            $stmt_actual->execute();

            // ลบข้อมูลใน production_plan ตาม WO_No, Product_ID และ Date_Time
            $delete_plan_query = "DELETE FROM production_plan WHERE WO_No = ? AND Product_ID = ? AND Date_Time = ?";
            $stmt_plan = $conn->prepare($delete_plan_query);
            $stmt_plan->bind_param('iis', $wo_no, $product_id, $formatted_date_time);
            $stmt_plan->execute();

            // หากการลบสำเร็จ, ทำการ commit การเปลี่ยนแปลง
            $conn->commit();

            // ส่งกลับสถานะสำเร็จ
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            // ในกรณีเกิดข้อผิดพลาด, ยกเลิกการทำงาน
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบข้อมูล']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    }
}
?>
