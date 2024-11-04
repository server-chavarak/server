<?php
session_start();
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $date_time = $_POST['date_time'];
    $plans = $_POST['plan'];  // ข้อมูลแผนที่ส่งมาจากฟอร์ม (array)

    if (!empty($product_id) && !empty($date_time) && !empty($plans)) {
        mysqli_begin_transaction($conn);  // เริ่มต้น transaction

        try {
            // วนลูปเพื่อบันทึกแผนสำหรับแต่ละขั้นตอน
            $first_step = true;
            foreach ($plans as $st_id => $plan) {
                // ดึง S_ID จาก work_step โดยใช้ St_ID
                $s_id_query = "SELECT S_ID FROM work_step WHERE St_ID = ? AND Product_ID = ?";
                $stmt_s_id = $conn->prepare($s_id_query);
                $stmt_s_id->bind_param('ii', $st_id, $product_id);
                $stmt_s_id->execute();
                $result_s_id = $stmt_s_id->get_result();
                $s_id_row = $result_s_id->fetch_assoc();

                if ($s_id_row) {
                    $s_id = $s_id_row['S_ID'];

                    // สำหรับลำดับแรก Product_queue จะถูกตั้งค่าตามแผน, ส่วนขั้นตอนที่เหลือจะเป็น 0
                    $product_queue = $first_step ? $plan : 0;
                    $first_step = false;

                    // เพิ่มข้อมูลใน actual_production
                    $insert_actual_query = "INSERT INTO actual_production (Product_ID, S_ID, St_ID, currentPlan, Product_queue, Date_Time) 
                                            VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_actual = $conn->prepare($insert_actual_query);
                    $stmt_actual->bind_param('iiisis', $product_id, $s_id, $st_id, $plan, $product_queue, $date_time);
                    $stmt_actual->execute();

                    if ($stmt_actual->affected_rows <= 0) {
                        throw new Exception("ไม่สามารถเพิ่มแผนผลิตใน actual_production ได้");
                    }
                } else {
                    throw new Exception("ไม่พบข้อมูล S_ID ใน work_step");
                }
            }

            mysqli_commit($conn);  // บันทึก transaction ถ้าสำเร็จ
            $_SESSION['alertMessage'] = "เพิ่มแผนผลิตเรียบร้อยแล้ว";
            $_SESSION['alertType'] = 'success';

        } catch (Exception $e) {
            mysqli_rollback($conn);  // ยกเลิก transaction ถ้ามีข้อผิดพลาด
            $_SESSION['alertMessage'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $_SESSION['alertType'] = 'error';
        } finally {
            mysqli_close($conn);  // ปิดการเชื่อมต่อ
        }

        header('Location: add_spiral_actual.php');  // Redirect
        exit();
    } else {
        $_SESSION['alertMessage'] = "กรุณากรอกข้อมูลให้ครบถ้วน";
        $_SESSION['alertType'] = 'error';
        header('Location: add_spiral_actual.php');
        exit();
    }
}



$product_query = "SELECT DISTINCT p.Product_ID, 
    CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.Pipe_Size, ' ', pe.PE_Name, 
    IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS display_name
    FROM product p
    INNER JOIN product_detail pd ON p.P_ID = pd.P_ID
    INNER JOIN type t ON p.T_ID = t.T_ID
    INNER JOIN type_detail td ON t.TD_ID = td.TD_ID
    INNER JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
    WHERE (SELECT s.S_Name FROM work_step ws
           JOIN section s ON ws.S_ID = s.S_ID
           WHERE ws.Product_ID = p.Product_ID
           ORDER BY ws.St_ID LIMIT 1) = 'Spiral'";  // เงื่อนไขให้แสดงเฉพาะผลิตภัณฑ์ที่เริ่มที่แผนก Fitting

$product_result = mysqli_query($conn, $product_query);

// ตรวจสอบว่า SQL ทำงานสำเร็จหรือไม่
if (!$product_result) {
    die('Error in product query: ' . mysqli_error($conn));  // แสดงข้อผิดพลาดใน SQL query
}

$products = [];
while ($row = mysqli_fetch_assoc($product_result)) {
    $products[] = $row;
}

include 'manager_index.html';
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<title>เพิ่มแผนผลิต</title>
<style>
        .addcustomer {
            background-color: #f5f5f5;
            padding: 15px 15px;
            border-radius: 5px;
            max-width: 900px;
            border: 1px solid #979797; 
            margin-left: 23%;
            margin-top: 1%;
            max-height: 600px; /* กำหนดความสูงสูงสุด */
            overflow-y: auto; /* เพิ่มการเลื่อนในแนวตั้ง */
        }

        #steps-container {
            margin-bottom: 20px;
            border: 1px solid #e1dfdf;
            padding: 10px;
            background-color: #ffffff;
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
        .addcustomer input[type="datetime-local"],
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

        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }

      
</style>
</head>
<body>
    <a href="../manager/spiral.php" class="back-link">ย้อนกลับ</a>
    <form action="add_spiral_actual.php" method="POST" class="addcustomer">
        <h2>เพิ่มแผนผลิตแบบไม่มี W/O</h2>

        <div class="form-group">
            <label for="date_time">วันที่/เวลา:</label>
            <input type="datetime-local" id="date_time" name="date_time" required>
        </div>
    
        <div class="form-group">
            <label for="product_id">เลือกผลิตภัณฑ์:</label>
            <select id="product_id" name="product_id" required onchange="fetchSteps()">  <!-- เรียก fetchSteps() เมื่อเลือกผลิตภัณฑ์ -->
                <option value="">-- เลือกผลิตภัณฑ์ --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo htmlspecialchars($product['Product_ID']); ?>">
                        <?php echo htmlspecialchars($product['display_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>


        <div id="steps-container">
            <!-- ช่องสำหรับแสดงขั้นตอนและใส่แผนจะถูกสร้างที่นี่ด้วย AJAX -->
        </div>

        <div class="footer">
            <button type="submit" class="approve">เพิ่ม</button>
            <button type="reset" class="delete">ยกเลิก</button>
        </div>
    </form>

    <script>
     $(document).ready(function() {
        // ใช้ Select2 กับ dropdown ผลิตภัณฑ์
        $('#product_id').select2({
            placeholder: 'เลือกผลิตภัณฑ์',
            allowClear: true
        });

        // ตรวจสอบและแสดงข้อความแจ้งเตือนจาก Session
        <?php
        if (isset($_SESSION['alertMessage'])) {
            $alertType = isset($_SESSION['alertType']) ? $_SESSION['alertType'] : 'info';  // ตรวจสอบประเภทการแจ้งเตือน
            echo "Swal.fire({
                icon: '$alertType',
                title: '" . $_SESSION['alertMessage'] . "',
                confirmButtonText: 'ตกลง'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'spiral.php';  // เปลี่ยนเส้นทางไปยัง spiral.php หลังจากกดตกลง
                }
            });";
            
            // ล้างข้อมูลใน session หลังจากแสดงข้อความแจ้งเตือนแล้ว
            unset($_SESSION['alertMessage']);
            unset($_SESSION['alertType']);
        }
        ?>
    });

    function fetchSteps() {
        var product_id = $('#product_id').val();

        if (product_id) {
            $.ajax({
                url: 'fetch_steps_spiral.php',
                type: 'POST',
                data: { product_id: product_id },
                success: function(response) {
                    $('#steps-container').html(response);  // เติมข้อมูลขั้นตอนการทำงานในพื้นที่
                },
                error: function() {
                    Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลขั้นตอนได้', 'error');
                }
            });
        } else {
            $('#steps-container').html('');  // ล้างข้อมูลถ้ายังไม่เลือกผลิตภัณฑ์
        }
    }
</script>


</body>
</html>
