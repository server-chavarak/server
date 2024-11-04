<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'get_amount') {
        $Raw_ID = $_POST['Raw_ID'];

        $checkSql = "SELECT Amount FROM raw_material WHERE Raw_ID = ?";
        $checkStmt = $conn->prepare($checkSql);
        if ($checkStmt === false) {
            echo json_encode(['error' => "Prepare failed: " . $conn->error]);
            exit();
        }

        $checkStmt->bind_param("i", $Raw_ID);
        if (!$checkStmt->execute()) {
            echo json_encode(['error' => "Execute failed: " . $checkStmt->error]);
            exit();
        }

        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows === 0) {
            echo json_encode(['error' => "No raw material found with the provided ID."]);
            exit();
        }

        $rawMaterial = $checkResult->fetch_assoc();
        echo json_encode(['Amount' => $rawMaterial['Amount']]);
        exit();
    }

    // Process form submission for adding disbursment
    $S_ID = $_POST['S_ID'];
    $Raw_ID = $_POST['Raw_ID'];
    $WO_No = $_POST['WO_No'];
    $dateReceived = $_POST['Date_Time'];
    $Amount = $_POST['Amount'];

    $dateReceived = str_replace('T', ' ', $dateReceived);

    $conn->begin_transaction();

    try {
        $checkSql = "SELECT Amount FROM raw_material WHERE Raw_ID = ?";
        $checkStmt = $conn->prepare($checkSql);
        if ($checkStmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $checkStmt->bind_param("i", $Raw_ID);
        if (!$checkStmt->execute()) {
            throw new Exception("Execute failed: " . $checkStmt->error);
        }

        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows === 0) {
            throw new Exception("No raw material found with the provided ID.");
        }

        $rawMaterial = $checkResult->fetch_assoc();
        $currentAmount = $rawMaterial['Amount'];
        
        if ($currentAmount < $Amount) {
            $_SESSION['alertMessage'] = "จำนวนที่กรอกเกินกว่าจำนวนที่มีในคลังวัตถุดิบ!";
            $_SESSION['alertType'] = "error";
             // เก็บค่าฟอร์มใน SESSION เพื่อนำกลับมาเติมในฟอร์ม
            $_SESSION['formData'] = $_POST;
            header('Location: add_disbursment.php');
            exit();
        }
        
        $sql = "INSERT INTO raw_disbursment (S_ID, Raw_ID, WO_No, Date_Time, Amount) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("iissd", $S_ID, $Raw_ID, $WO_No, $dateReceived, $Amount);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $updateSql = "UPDATE raw_material SET Amount = Amount - ? WHERE Raw_ID = ?";
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $updateStmt->bind_param("di", $Amount, $Raw_ID);

        if (!$updateStmt->execute()) {
            throw new Exception("Execute failed: " . $updateStmt->error);
        }

        $conn->commit();

        $_SESSION['alertMessage'] = 'บันทึกข้อมูลสำเร็จ';
        header('Location: add_disbursment.php');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['alertMessage'] = "Error: " . $e->getMessage();
        $_SESSION['alertType'] = "error";
        header('Location: add_disbursment.php');
        exit();
    }
    
}

$sections = $conn->query("SELECT S_ID, S_Name FROM section")->fetch_all(MYSQLI_ASSOC);
$raw_materials = $conn->query("SELECT Raw_ID, Raw_Name, Coil_No FROM raw_material")->fetch_all(MYSQLI_ASSOC);
$orderss = $conn->query("SELECT WO_No FROM orders")->fetch_all(MYSQLI_ASSOC);

include 'admin_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/disbursment.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <title>เพิ่มข้อมูลเบิกวัตถุดิบ</title>
</head>
<body>
<a href="../admin/raw_disbursment.php" class="back-link">ย้อนกลับ</a>

<form action="add_disbursment.php" method="POST" id="addcustomer">
    <h2>เพิ่มข้อมูลเบิกวัตถุดิบ</h2>

    <div class="form-group">
    <label for="S_ID">แผนก:</label>
    <select name="S_ID" id="S_ID" required>
        <option value="">--เลือกแผนก--</option>
        <?php foreach ($sections as $section): ?>
            <option value="<?php echo htmlspecialchars($section['S_ID']); ?>"
                <?php echo (isset($_SESSION['formData']['S_ID']) && $_SESSION['formData']['S_ID'] == $section['S_ID']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($section['S_Name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group">
    <label for="Raw_ID">ชื่อวัตถุดิบ:</label>
    <select name="Raw_ID" id="Raw_ID" required onchange="fetchRawMaterialAmount()">
        <option value="">--เลือกชื่อวัตถุดิบ--</option>
        <?php foreach ($raw_materials as $raw_material): ?>
            <option value="<?php echo htmlspecialchars($raw_material['Raw_ID']); ?>"
                <?php echo (isset($_SESSION['formData']['Raw_ID']) && $_SESSION['formData']['Raw_ID'] == $raw_material['Raw_ID']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($raw_material['Raw_Name'] . ' - ' . $raw_material['Coil_No']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group">
    <label for="WO_No">W/O:</label>
    <select name="WO_No" id="WO_No" required>
        <option value="">--เลือก W/O--</option>
        <?php foreach ($orderss as $orders): ?>
            <option value="<?php echo htmlspecialchars($orders['WO_No']); ?>"
                <?php echo (isset($_SESSION['formData']['WO_No']) && $_SESSION['formData']['WO_No'] == $orders['WO_No']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($orders['WO_No']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group">
    <label for="Date_Time">วันที่:</label>
    <input type="datetime-local" id="Date_Time" name="Date_Time" required
           value="<?php echo isset($_SESSION['formData']['Date_Time']) ? $_SESSION['formData']['Date_Time'] : ''; ?>">
</div>

<div class="form-group">
    <label for="Amount">จำนวน/ชิ้น:</label>
    <input type="number" id="Amount" name="Amount" step="0.01" required
           value="<?php echo isset($_SESSION['formData']['Amount']) ? $_SESSION['formData']['Amount'] : ''; ?>">
</div>


    <div class="footer">
        <button type="submit" class="approve">เพิ่มสินค้า</button>
        <button type="reset" class="delete">ยกเลิก</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Initializing Select2 on the Raw_ID and WO_No select elements
    $('#Raw_ID').select2({
        placeholder: '--เลือกชื่อวัตถุดิบ--',
        allowClear: true
    });

    $('#WO_No').select2({
        placeholder: '--เลือก W/O--',
        allowClear: true
    });

    // ฟังก์ชันเพื่อดึงจำนวนวัตถุดิบจากเซิร์ฟเวอร์
    window.fetchRawMaterialAmount = function () {
        const rawID = document.getElementById('Raw_ID').value;

        if (rawID) {
            fetch('add_disbursment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=get_amount&Raw_ID=${rawID}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('ข้อผิดพลาด:', data.error);
                    document.getElementById('Available_Amount').value = '';
                } else {
                    document.getElementById('Available_Amount').value = data.Amount;
                }
            })
            .catch(error => {
                console.error('ข้อผิดพลาดในการดึงข้อมูล:', error);
            });
        } else {
            document.getElementById('Available_Amount').value = '';
        }
    };

});

<?php
if (isset($_SESSION['alertMessage']) && isset($_SESSION['alertType'])) {
    echo "Swal.fire({
        icon: '" . $_SESSION['alertType'] . "',
        title: 'ข้อผิดพลาด!',
        text: '" . $_SESSION['alertMessage'] . "',
        confirmButtonText: 'ตกลง'
    });";
    unset($_SESSION['alertMessage']);
    unset($_SESSION['alertType']);
     // อย่าลืมเคลียร์ฟอร์มดาต้าใน SESSION
     unset($_SESSION['formData']);
}
?>


<?php
if (isset($_SESSION['alertMessage'])) {
    echo "Swal.fire({
        icon: 'success',
        title: '" . $_SESSION['alertMessage'] . "',
        confirmButtonText: 'ตกลง'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'raw_disbursment.php';
        }
    });";
    unset($_SESSION['alertMessage']);
}
?>

</script>
</body>
</html>

