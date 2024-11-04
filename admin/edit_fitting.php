<?php
require_once '../db.php';
session_start();

// ตรวจสอบว่ามี Act_ID ใน URL หรือไม่ (ใช้สำหรับแก้ไข)
if (isset($_GET['Act_ID'])) {
    $act_id = $_GET['Act_ID'];

    $sql = "SELECT ap.Act_ID, ap.WO_No, 
            CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.Pipe_Size, ' ', pe.PE_Name, '  ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS Product_name,
            s.S_Name AS Section_Name, 
            ap.St_ID, 
            ap.PD_ID, 
            ap.Product_queue, ap.Date_Time, ap.Actual, ap.NoGood, ap.FinishGood, ap.Losstime, ap.Diff, 
            pp.Plan AS currentPlan, ws.St_Name, ws.cycletime, c.Working_Hours
            FROM actual_production ap
            LEFT JOIN production_plan pp ON ap.PD_ID = pp.PD_ID
            LEFT JOIN product pr ON ap.Product_ID = pr.Product_ID
            LEFT JOIN product_detail pd ON pr.P_ID = pd.P_ID
            LEFT JOIN type t ON pr.T_ID = t.T_ID
            LEFT JOIN type_detail td ON t.TD_ID = td.TD_ID
            LEFT JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
            LEFT JOIN section s ON ap.S_ID = s.S_ID
            LEFT JOIN work_step ws ON ap.St_ID = ws.St_ID
            LEFT JOIN calculate c ON ws.Product_ID = c.Product_ID
            WHERE ap.Act_ID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $act_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $actual_production = $result->fetch_assoc();

    if (!$actual_production) {
        $_SESSION['alertMessage'] = "ไม่พบข้อมูลการผลิตที่ต้องการแก้ไข";
        $_SESSION['alertType'] = 'error';
        header('Location: edit_fitting.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actual = isset($_POST['Actual']) && is_numeric($_POST['Actual']) ? $_POST['Actual'] : 0;
    $nogood = isset($_POST['NoGood']) && is_numeric($_POST['NoGood']) ? $_POST['NoGood'] : 0;
    $finishgood = isset($_POST['FinishGood']) && is_numeric($_POST['FinishGood']) ? $_POST['FinishGood'] : 0;
    $losstime = isset($_POST['Losstime']) && is_numeric($_POST['Losstime']) ? $_POST['Losstime'] : 0;
    $act_id = $_POST['Act_ID'];
    $currentPlan = isset($_POST['currentPlan']) && is_numeric($_POST['currentPlan']) ? $_POST['currentPlan'] : 0;
    $product_queue = isset($_POST['Product_queue']) && is_numeric($_POST['Product_queue']) ? $_POST['Product_queue'] : 0;

    // ดึง FinishGood เดิมจากฐานข้อมูล
    $sql = "SELECT FinishGood FROM actual_production WHERE Act_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $act_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $current_finishgood = $row['FinishGood']; // ค่า FinishGood เดิม

    // ตรวจสอบการเปลี่ยนแปลงของ FinishGood
    $diff_finishgood = $finishgood - $current_finishgood;

    // คำนวณค่าใหม่สำหรับ Product_queue โดยใช้ค่าปัจจุบัน - ความแตกต่างของ FinishGood
    $product_queue_new = $product_queue - $diff_finishgood;
    $product_queue_new = max(0, $product_queue_new); // ห้ามค่าติดลบ


    // คำนวณ Diff ใหม่
    $new_diff = $currentPlan - $finishgood;
    $diff_sign = ($new_diff > 0) ? '-' : (($new_diff < 0) ? '+' : '');
    $final_diff = $diff_sign . abs($new_diff);


    // อัปเดตข้อมูล Actual, NoGood, FinishGood, Losstime, Diff, Product_queue
    $update_query = "UPDATE actual_production 
                     SET Actual = ?, NoGood = ?, FinishGood = ?, Losstime = ?, Diff = ?, Product_queue = ? 
                     WHERE Act_ID = ?";
    $stmt_update = $conn->prepare($update_query);
    $stmt_update->bind_param('iiidsii', $actual, $nogood, $finishgood, $losstime, $final_diff, $product_queue_new, $act_id);
    if (!$stmt_update->execute()) {
        die('Error executing update query: ' . $stmt_update->error);
    }

    // อัปเดต Product_queue ของแผนกถัดไป
    $next_step_query = "SELECT ws.St_ID FROM work_step ws WHERE ws.Product_ID = ? AND ws.St_ID > ? ORDER BY ws.St_ID ASC LIMIT 1";
    $next_step_stmt = $conn->prepare($next_step_query);
    $next_step_stmt->bind_param('ii', $actual_production['Product_ID'], $actual_production['St_ID']);
    $next_step_stmt->execute();
    $next_step_result = $next_step_stmt->get_result();
    $next_step_row = $next_step_result->fetch_assoc();

// อัปเดตค่า Actual และ Product_queue ของแผนกถัดไป
if ($next_step_row) {
    $next_st_id = $next_step_row['St_ID'];

    // นำค่า Actual ของแผนกปัจจุบันไปอัปเดต Product_queue ของแผนกถัดไป
    $update_queue_query = "UPDATE actual_production 
                           SET Product_queue = Product_queue + ? 
                           WHERE St_ID = ? 
                           AND Product_ID = ?";
    $update_queue_stmt = $conn->prepare($update_queue_query);
    $update_queue_stmt->bind_param('iii', $actual, $next_st_id, $actual_production['Product_ID']);
    $update_queue_stmt->execute();
}


    if (!mysqli_commit($conn)) {
        die('Error committing the transaction: ' . mysqli_error($conn));
    }

    $_SESSION['alertMessage'] = "แก้ไขข้อมูลการผลิตเรียบร้อยแล้ว";
    $_SESSION['alertType'] = 'success';
    header('Location: edit_fitting.php?Act_ID=' . $act_id);
    exit();
}




include 'admin_index.html';
?>






<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลการผลิตจริง</title>
    <link rel="stylesheet" href="../css/actual.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
</head>
<body>
    <a href="fitting.php" class="back-link">ย้อนกลับ</a>

    <form action="edit_fitting.php" method="POST" id="addcustomer" class="form-container">
        <h2>แก้ไขข้อมูลการผลิตจริง</h2>

        <div class="form-group">
            <label for="WO_No">W/O:</label>
            <input type="text" id="WO_No" name="WO_No" value="<?php echo htmlspecialchars($actual_production['WO_No']); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="Product_name">ผลิตภัณฑ์:</label>
            <input type="text" id="Product_name" name="Product_name" value="<?php echo htmlspecialchars($actual_production['Product_name']); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="Section_Name">แผนก:</label>
            <input type="text" id="Section_Name" name="Section_Name" value="<?php echo htmlspecialchars($actual_production['Section_Name']); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="St_Name">ชื่อขั้นตอน:</label>
            <input type="text" id="St_Name" name="St_Name" value="<?php echo htmlspecialchars($actual_production['St_Name']); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="currentPlan">แผนผลิต:</label>
            <input type="text" id="currentPlan" name="currentPlan" value="<?php echo htmlspecialchars($actual_production['currentPlan']); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="Product_queue">รอผลิต:</label>
            <input type="number" id="Product_queue" name="Product_queue" value="<?php echo htmlspecialchars($actual_production['Product_queue']); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="Actual">ผลิตจริง:</label>
            <input type="number" id="Actual" name="Actual" value="0" step="1" min="0">
        </div>

        <div class="form-group">
            <label for="FinishGood">ยอดรวม:</label>
            <input type="number" id="FinishGood" name="FinishGood" value="<?php echo htmlspecialchars($actual_production['FinishGood']); ?>" step="0.01" min="0">
        </div>

        <div class="form-group">
            <label for="NoGood">ของเสีย/กก.:</label>
            <input type="number" id="NoGood" name="NoGood" value="<?php echo htmlspecialchars($actual_production['NoGood']); ?>" step="0.01" min="0">
        </div>

        <div class="form-group">
            <label for="Losstime">เวลาที่สูญเสีย/นาที:</label>
            <input type="number" id="Losstime" name="Losstime" value="<?php echo htmlspecialchars($actual_production['Losstime']); ?>" step="0.01" min="0">
        </div>

        <div class="form-group">
            <label for="Diff">ยอด+,-:</label>
            <input type="text" id="Diff" name="Diff" value="<?php echo htmlspecialchars($actual_production['Diff']); ?>" step="0.01" >
        </div>

        <input type="hidden" name="Act_ID" value="<?php echo $actual_production['Act_ID']; ?>">

        <div class="footer">
            <button type="submit" class="approve">บันทึกการแก้ไข</button>
            <button type="reset" class="delete">ยกเลิก</button>
        </div>
    </form>

    <script>
        <?php
       if (isset($_SESSION['alertMessage'])) {
        $alertType = isset($_SESSION['alertType']) ? $_SESSION['alertType'] : 'info';
        echo "Swal.fire({
            icon: '$alertType',
            title: '" . $_SESSION['alertMessage'] . "',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'fitting.php'; 
            }
        });";
        unset($_SESSION['alertMessage']);
        unset($_SESSION['alertType']);
    }
    
        ?>




// เก็บค่า FinishGood เดิมเมื่อโหลดหน้าเพจ
let previousFinishGood = 0; // ตัวแปรสำหรับเก็บค่า FinishGood ก่อนหน้า

window.onload = function() {
    let finishGoodInput = document.getElementById('FinishGood');
    previousFinishGood = parseFloat(finishGoodInput.value) || 0; // เก็บค่าเริ่มต้นของ FinishGood
    finishGoodInput.setAttribute('data-original-value', finishGoodInput.value); // เก็บค่าเริ่มต้นของ FinishGood ไว้ใน attribute
};

// เมื่อมีการเปลี่ยนแปลงค่า Actual
document.getElementById('Actual').addEventListener('input', function () {
    let actualValue = parseFloat(this.value) || 0; // ค่า Actual ที่กรอก
    let finishGoodInput = document.getElementById('FinishGood');
    let finishGoodValue = parseFloat(finishGoodInput.getAttribute('data-original-value')) || 0; // ค่าเริ่มต้นของ FinishGood

    // บวกค่า Actual กับ FinishGood ปัจจุบัน
    let newFinishGoodValue = finishGoodValue + actualValue;
    finishGoodInput.value = newFinishGoodValue;

    

    // อัปเดต Product_queue ของแผนกถัดไป โดยใช้ค่า FinishGood ที่เปลี่ยนแปลง
    updateProductQueue(newFinishGoodValue);
});

// เมื่อมีการเปลี่ยนแปลงค่า FinishGood
document.getElementById('FinishGood').addEventListener('input', function () {
    let finishGoodValue = parseFloat(this.value) || 0;

    // อัปเดต Product_queue ของแผนกถัดไป โดยใช้ค่า FinishGood ที่เปลี่ยนแปลง
    updateProductQueue(finishGoodValue);
});

// ฟังก์ชันสำหรับอัปเดต Product_queue ของแผนกถัดไป
function updateProductQueue(finishGoodValue) {
    let actId = document.querySelector('input[name="Act_ID"]').value; // ดึงค่า Act_ID เพื่อส่งไปกับการอัปเดต

    fetch('update_queue.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            finishGood: finishGoodValue, // ส่งค่า FinishGood ที่เปลี่ยนแปลง
            previousFinishGood: previousFinishGood, // ส่งค่า FinishGood ก่อนหน้าที่บันทึกไว้
            actId: actId,
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Product queue updated successfully');
        } else {
            console.error('Failed to update product queue:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });

    // อัปเดตค่า previousFinishGood หลังจากที่ส่งค่าไปแล้ว
    previousFinishGood = finishGoodValue;
}



    </script>
</body>
</html>
