<?php
session_start();
require_once '../db.php';

// ตรวจสอบว่ามี PD_ID ใน URL หรือไม่ (ใช้สำหรับแก้ไข)
if (isset($_GET['pd_id'])) {
    $pd_id = $_GET['pd_id'];

    // ดึงข้อมูลจาก production_plan เพื่อแสดงในฟอร์ม
    $plan_query = "SELECT pp.*, 
                          pd.P_Name,    -- ชื่อผลิตภัณฑ์จาก product_detail
                          td.TD_Name,   -- ชื่อประเภทจาก type_detail
                          pe.PE_Name,   -- ชื่อปลายท่อจาก pipeend_detail
                          t.Pipe_Size,  -- ขนาดท่อจาก type
                          t.degree,     -- องศาจาก type
                          s.S_Name,     -- ชื่อแผนกจาก section
                          ws.St_Name    -- ชื่อขั้นตอนจาก work_step
                   FROM production_plan pp
                   INNER JOIN product p ON pp.Product_ID = p.Product_ID
                   INNER JOIN product_detail pd ON p.P_ID = pd.P_ID  -- JOIN กับ product_detail เพื่อดึง P_Name
                   INNER JOIN type t ON p.T_ID = t.T_ID  -- JOIN กับ type เพื่อดึงข้อมูล Pipe_Size และ degree
                   INNER JOIN type_detail td ON t.TD_ID = td.TD_ID  -- JOIN กับ type_detail เพื่อดึง TD_Name
                   INNER JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID  -- JOIN กับ pipeend_detail เพื่อดึง PE_Name
                   INNER JOIN section s ON pp.S_ID = s.S_ID  -- JOIN กับ section เพื่อดึงชื่อแผนก
                   INNER JOIN work_step ws ON pp.St_ID = ws.St_ID  -- JOIN กับ work_step เพื่อดึงชื่อขั้นตอน
                   WHERE pp.PD_ID = ?";

    $stmt_plan = $conn->prepare($plan_query);
    $stmt_plan->bind_param('i', $pd_id);
    $stmt_plan->execute();
    $result_plan = $stmt_plan->get_result();

    if ($result_plan->num_rows > 0) {
        $plan_data = $result_plan->fetch_assoc();
    } else {
        $_SESSION['alertMessage'] = "ไม่พบข้อมูลแผนผลิตที่ต้องการแก้ไข";
        $_SESSION['alertType'] = 'error';
        header('Location: product_plan.php');
        exit();
    }

    $stmt_plan->close();
}

// ตรวจสอบว่ามีการส่งฟอร์มแก้ไขข้อมูลหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan = $_POST['plan'];  // รับค่า plan ที่แก้ไขจากฟอร์ม
    $pd_id = $_POST['pd_id'];  // รับ PD_ID จาก hidden field

    // ตรวจสอบว่ามีข้อมูลที่จำเป็นครบหรือไม่
    if (!empty($plan) && isset($pd_id)) {

        // เริ่มต้น transaction
        mysqli_begin_transaction($conn);

        try {
            // อัปเดตข้อมูลเฉพาะ plan ในตาราง production_plan
            $update_plan_query = "UPDATE production_plan SET Plan = ? WHERE PD_ID = ?";
            $stmt_update = $conn->prepare($update_plan_query);
            $stmt_update->bind_param('ii', $plan, $pd_id);
            $stmt_update->execute();

            // อัปเดตข้อมูลใน actual_production โดยใช้ PD_ID เดียวกัน
            $update_actual_query = "UPDATE actual_production SET currentPlan = ? WHERE PD_ID = ?";
            $stmt_actual_update = $conn->prepare($update_actual_query);
            $stmt_actual_update->bind_param('ii', $plan, $pd_id);
            $stmt_actual_update->execute();

            // บันทึก transaction ถ้าสำเร็จ
            mysqli_commit($conn);

            $_SESSION['alertMessage'] = "แก้ไขแผนผลิตเรียบร้อยแล้ว";
            $_SESSION['alertType'] = 'success';
            header('Location: edit_product_plan.php?pd_id=' . $pd_id);
            exit();

        } catch (Exception $e) {
            // ยกเลิก transaction ถ้ามีข้อผิดพลาด
            mysqli_rollback($conn);
            $_SESSION['alertMessage'] = "เกิดข้อผิดพลาดในการแก้ไขแผนผลิต";
            $_SESSION['alertType'] = 'error';
            header('Location: edit_product_plan.php?pd_id=' . $pd_id);
            exit();
        }
    } else {
        $_SESSION['alertMessage'] = "กรุณากรอกข้อมูลให้ครบถ้วน";
        $_SESSION['alertType'] = 'error';
        header('Location: edit_product_plan.php?pd_id=' . $pd_id);
        exit();
    }
}

include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>แก้ไขแผนผลิต</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
    .addcustomer {
        background-color: #f5f5f5;
        padding: 15px 15px;
        border-radius: 5px;
        max-width: 900px;
        border: 1px solid #979797;
        margin-left: 23%;
        margin-top: 1%;
        max-height: 600px;
        overflow-y: auto;
    }

    .addcustomer h2 {
        margin-bottom: 20px;
        font-size: 18px;
        font-family: 'K2D', sans-serif;
        margin-left: 45%;
    }

    .form-group {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .form-group label {
        flex: 0 0 120px;
        font-family: Maitree;
        margin-left: 5%;
        text-align: left;
    }

    .addcustomer label {
        flex: 0 0 170px;
        font-family: Maitree;
        margin-right: 10px;
        text-align: left;
    }

    .addcustomer input[type="text"], 
    .addcustomer input[type="number"],
    .addcustomer select {
        flex: 1;
        padding: 10px 15px;
        border: 1px solid #e1dfdf;
        border-radius: 4px;
        font-family: 'Maitree', sans-serif;
        background-color: #ffffff;
        font-size: 15px;
    }

    .addcustomer input[readonly] {
        background-color: #e9ecef; /* สีพื้นหลังสำหรับ input ที่ readonly */
    }

    .back-link {
        color: #a20c8c;
        margin-left: 73%;
        font-family: Maitree;
    }

    .back-link:hover {
        color: #cc2121;
    }

    button.delete {
        background-color: #f44336;
        color: #fff;
        padding: 10px 20px;
        margin-left: 10px;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
    }

    button.approve {
        background-color: #2d9a47;
        color: #fff;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
    }

    button.delete:hover {
        background-color: #bc0a0a;
    }

    button.approve:hover {
        background-color: #148918;
    }

    .footer {
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }
</style>
</head>
<body>
    <a href="product_plan.php" class="back-link">ย้อนกลับ</a>
    <form action="edit_product_plan.php" method="POST" class="addcustomer">
        <h2>แก้ไขแผนผลิต</h2>

        <div class="form-group">
            <label for="wo_no">W/O:</label>
            <input type="text" id="wo_no" name="wo_no" value="<?php echo htmlspecialchars($plan_data['WO_No']); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="product_name">ผลิตภัณฑ์:</label>
            <input type="text" id="product_name" name="product_name" 
                value="<?php echo htmlspecialchars(
                    $plan_data['P_Name'] . ' - ' . 
                    $plan_data['TD_Name'] . ' - ' . 
                    ' Ø' . $plan_data['Pipe_Size'] . ' ' . 
                    $plan_data['PE_Name'] . 
                    (!empty($plan_data['degree']) ? ' - ' . $plan_data['degree'] . ' องศา' : '')
                ); ?>" 
                readonly>
        </div>


        <div class="form-group">
            <label for="section_name">แผนก:</label>
            <input type="text" id="section_name" name="section_name" value="<?php echo htmlspecialchars($plan_data['S_Name']); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="step_name">ขั้นตอน:</label>
            <input type="text" id="step_name" name="step_name" value="<?php echo htmlspecialchars($plan_data['St_Name']); ?>" readonly>
        </div>

        <div class="form-group">
            <label for="plan">แผนการผลิต:</label>
            <input type="number" id="plan" name="plan" value="<?php echo htmlspecialchars($plan_data['Plan']); ?>" step="0.01" required>
        </div>

        <input type="hidden" name="pd_id" value="<?php echo $plan_data['PD_ID']; ?>">

        <div class="footer">
            <button type="submit" class="approve">บันทึกการแก้ไข</button>
            <button type="reset" class="delete">ยกเลิก</button>
        </div>
    </form>

    <script>
        $(document).ready(function() {
            <?php
            if (isset($_SESSION['alertMessage'])) {
                $alertType = isset($_SESSION['alertType']) ? $_SESSION['alertType'] : 'info';  // กำหนดชนิดของข้อความแจ้งเตือน
                echo "Swal.fire({
                    icon: '$alertType',
                    title: '" . $_SESSION['alertMessage'] . "',
                    confirmButtonText: 'ตกลง'
                }).then((result) => {
                    if (result.isConfirmed) {";
                
                // ให้เปลี่ยนเส้นทางเฉพาะเมื่อกดปุ่มตกลง โดยไม่ต้องเช็ค alertType ที่ success
                echo "window.location.href = 'product_plan.php';";
                
                echo "    }
                });";
                
                // ล้างข้อมูลใน session หลังจากแสดงข้อความแจ้งเตือนแล้ว
                unset($_SESSION['alertMessage']);
                unset($_SESSION['alertType']);
            }
            ?>
        });
    </script>

</body>
</html>
