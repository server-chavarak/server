<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $act_id = $data['Act_ID'];

    if ($act_id) {
        // Step 1: Get the product_id, S_ID and WO_No from the actual_production based on Act_ID
        $sql = "SELECT ap.WO_No, ws.product_id, ws.S_ID 
                FROM actual_production ap
                JOIN work_step ws ON ap.St_ID = ws.St_ID
                WHERE ap.Act_ID = ?";
                
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $act_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row) {
                $product_id = $row['product_id'];
                $S_ID = $row['S_ID'];
                $wo_no = $row['WO_No'];

                // Step 2: Check if WO_No exists or not and prepare the appropriate delete query
                if (!is_null($wo_no)) {
                    // If WO_No exists, delete all rows with the same WO_No and Product_ID across all sections
                    $delete_sql = "DELETE ap
                                   FROM actual_production ap
                                   JOIN work_step ws ON ap.St_ID = ws.St_ID
                                   WHERE ws.product_id = ? AND ap.WO_No = ?";
                    if ($delete_stmt = $conn->prepare($delete_sql)) {
                        $delete_stmt->bind_param("is", $product_id, $wo_no);
                    }
                } else {
                    // If WO_No does not exist, delete all rows with the same Product_ID (across all sections)
                    $delete_sql = "DELETE ap
                                   FROM actual_production ap
                                   JOIN work_step ws ON ap.St_ID = ws.St_ID
                                   WHERE ws.product_id = ? AND ap.WO_No IS NULL";
                    if ($delete_stmt = $conn->prepare($delete_sql)) {
                        $delete_stmt->bind_param("i", $product_id);
                    }
                }

                // Execute the delete query
                if (isset($delete_stmt) && $delete_stmt->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to delete the records.']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Record not found.']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to prepare select statement.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid Act_ID.']);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
