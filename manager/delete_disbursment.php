<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['RD_ID'])) {
        $RD_ID = $data['RD_ID'];

        // Start transaction
        $conn->begin_transaction();

        try {
            // Fetch the disbursment details
            $fetchSql = "SELECT Raw_ID, Amount FROM raw_disbursment WHERE RD_ID = ?";
            $fetchStmt = $conn->prepare($fetchSql);
            if ($fetchStmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $fetchStmt->bind_param("i", $RD_ID);
            if (!$fetchStmt->execute()) {
                throw new Exception("Execute failed: " . $fetchStmt->error);
            }

            $fetchResult = $fetchStmt->get_result();
            if ($fetchResult->num_rows === 0) {
                throw new Exception("No disbursment record found with the provided ID.");
            }

            $disbursment = $fetchResult->fetch_assoc();
            $Raw_ID = $disbursment['Raw_ID'];
            $Amount = $disbursment['Amount'];

            // Delete the disbursment record
            $deleteSql = "DELETE FROM raw_disbursment WHERE RD_ID = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            if ($deleteStmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $deleteStmt->bind_param("i", $RD_ID);
            if (!$deleteStmt->execute()) {
                throw new Exception("Execute failed: " . $deleteStmt->error);
            }

            // Restore the amount to raw_material
            $updateSql = "UPDATE raw_material SET Amount = Amount + ? WHERE Raw_ID = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $updateStmt->bind_param("di", $Amount, $Raw_ID);
            if (!$updateStmt->execute()) {
                throw new Exception("Execute failed: " . $updateStmt->error);
            }

            // Commit the transaction
            $conn->commit();
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        $fetchStmt->close();
        $deleteStmt->close();
        $updateStmt->close();
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No RD_ID provided.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}

$conn->close();
?>
