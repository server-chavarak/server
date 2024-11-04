<?php
session_start(); // ต้องอยู่ที่บรรทัดแรกของไฟล์
require_once '../db.php';


$data = json_decode(file_get_contents('php://input'), true);

// ตรวจสอบว่ามีข้อมูลส่งมาหรือไม่
if (!isset($data['finishGood']) || !isset($data['previousFinishGood']) || !isset($data['actId'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

$finishGood = $data['finishGood'];
$previousFinishGood = $data['previousFinishGood'];
$actId = $data['actId'];

// ตรวจสอบว่า actId มีค่าหรือไม่
if (empty($actId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid Act_ID']);
    exit();
}

// ดึงข้อมูลจาก actual_production ปัจจุบัน
$sql = "SELECT Product_ID, St_ID, WO_No, Date_Time FROM actual_production WHERE Act_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $actId);
$stmt->execute();
$result = $stmt->get_result();
$actual_production = $result->fetch_assoc();

// ตรวจสอบว่าข้อมูลของ Act_ID มีอยู่หรือไม่
if (!$actual_production) {
    echo json_encode(['success' => false, 'message' => 'Invalid Act_ID']);
    exit();
}

// คำนวณ Diff
$diff = $finishGood - $previousFinishGood; // คำนวณความแตกต่างของ FinishGood

// หาลำดับถัดไปของ work_step
$next_step_query = "SELECT ws.St_ID, ap.Product_queue, ap.Date_Time 
                    FROM work_step ws 
                    JOIN actual_production ap ON ws.St_ID = ap.St_ID AND ws.Product_ID = ap.Product_ID
                    WHERE ws.Product_ID = ? 
                    AND ws.St_ID > ? 
                    AND (ap.WO_No = ? OR ap.WO_No IS NULL) 
                    AND ap.Date_Time = ?  
                    ORDER BY ws.St_ID ASC 
                    LIMIT 1";
$next_step_stmt = $conn->prepare($next_step_query);
$next_step_stmt->bind_param('iiss', $actual_production['Product_ID'], $actual_production['St_ID'], $actual_production['WO_No'], $actual_production['Date_Time']);
$next_step_stmt->execute();
$next_step_result = $next_step_stmt->get_result();
$next_step_row = $next_step_result->fetch_assoc();

if ($next_step_row) {
    $next_st_id = $next_step_row['St_ID'];
    $next_product_queue = $next_step_row['Product_queue'];

    // เพิ่มหรือลบค่า diff ไปยัง Product_queue ของแผนกถัดไป
    $new_product_queue = $next_product_queue + $diff;

    // ตรวจสอบว่า WO_No เป็น NULL หรือไม่
    if (is_null($actual_production['WO_No'])) {
        // กรณี WO_No เป็น NULL
        $update_queue_query = "UPDATE actual_production 
                               SET Product_queue = ? 
                               WHERE St_ID = ? 
                               AND Product_ID = ?
                               AND WO_No IS NULL
                               AND Date_Time = ?";
        $update_queue_stmt = $conn->prepare($update_queue_query);
        $update_queue_stmt->bind_param('iiss', $new_product_queue, $next_st_id, $actual_production['Product_ID'], $actual_production['Date_Time']);
    } else {
        // กรณีที่ WO_No ไม่เป็น NULL
        $update_queue_query = "UPDATE actual_production 
                               SET Product_queue = ? 
                               WHERE St_ID = ? 
                               AND Product_ID = ?
                               AND WO_No = ?
                               AND Date_Time = ?";
        $update_queue_stmt = $conn->prepare($update_queue_query);
        $update_queue_stmt->bind_param('iiiss', $new_product_queue, $next_st_id, $actual_production['Product_ID'], $actual_production['WO_No'], $actual_production['Date_Time']);
    }

    // ดำเนินการอัปเดต Product_queue
    $update_queue_stmt->execute();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No next step found']);
}

?>