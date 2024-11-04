<?php
session_start();
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wo_no = $_POST['wo_no'];
    $product_id = $_POST['product_id'];
    $date_time = $_POST['date_time'];
    $plans = $_POST['plan'];  // ข้อมูลแผนที่ส่งมาจากฟอร์ม (array)

    // ตรวจสอบว่ามีข้อมูลที่จำเป็นครบหรือไม่
    if (!empty($wo_no) && !empty($product_id) && !empty($date_time) && !empty($plans)) {
        // เริ่มต้น transaction
        mysqli_begin_transaction($conn);

        try {
            // ตรวจสอบว่ามีข้อมูลในตาราง production_plan แล้วหรือไม่ โดยตรวจสอบทั้ง 3 ค่า WO_No, Product_ID, และ Date_Time
            $check_query = "SELECT * FROM production_plan WHERE WO_No = ? AND Product_ID = ? AND Date_Time = ?";
            $stmt_check = $conn->prepare($check_query);
            $stmt_check->bind_param('iis', $wo_no, $product_id, $date_time);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                // ถ้าข้อมูลทั้ง 3 ซ้ำกันแสดงว่ามีอยู่แล้ว
                $_SESSION['alertMessage'] = "แผนผลิตวันนี้มีอยู่แล้ว";
                $_SESSION['alertType'] = 'warning';
                header('Location: add_product_plan.php');
                exit();
            } else {
                // วนลูปเพื่อบันทึกแผนสำหรับแต่ละขั้นตอน
                $first_step = true; // กำหนดตัวแปรตรวจสอบว่าเป็นขั้นตอนแรกหรือไม่
                foreach ($plans as $st_id => $plan) {
                    // ดึง S_ID จาก work_step โดยใช้ St_ID
                    $s_id_query = "SELECT S_ID FROM work_step WHERE St_ID = ?";
                    $stmt_s_id = $conn->prepare($s_id_query);
                    $stmt_s_id->bind_param('i', $st_id);
                    $stmt_s_id->execute();
                    $result_s_id = $stmt_s_id->get_result();
                    $s_id_row = $result_s_id->fetch_assoc();

                    if ($s_id_row) {
                        $s_id = $s_id_row['S_ID'];

                        // ถ้าไม่มีข้อมูลซ้ำ ให้เพิ่มข้อมูลลงในตาราง production_plan พร้อม S_ID
                        $insert_plan_query = "INSERT INTO production_plan (WO_No, Product_ID, S_ID, St_ID, Date_Time, Plan) 
                                              VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($insert_plan_query);
                        $stmt->bind_param('iiiisi', $wo_no, $product_id, $s_id, $st_id, $date_time, $plan);
                        $stmt->execute();
                        $pd_id = $conn->insert_id; // รับค่า PD_ID ที่เพิ่งเพิ่ม

                        // สำหรับลำดับแรก Product_queue จะถูกตั้งค่าตามแผน, ส่วนขั้นตอนที่เหลือจะเป็น 0
                        $product_queue = $first_step ? $plan : 0;
                        $first_step = false; // เปลี่ยนตัวแปรเป็น false หลังจากบันทึกขั้นตอนแรกแล้ว

                        // เพิ่มข้อมูลใน actual_production
                        $insert_actual_query = "INSERT INTO actual_production (WO_No, Product_ID, S_ID, St_ID, PD_ID, currentPlan, Product_queue, Date_Time) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt_actual = $conn->prepare($insert_actual_query);
                        if ($stmt_actual) {
                            $stmt_actual->bind_param('iiiiiiss', $wo_no, $product_id, $s_id, $st_id, $pd_id, $plan, $product_queue, $date_time);
                            $stmt_actual->execute();

                            if ($stmt_actual->affected_rows > 0) {
                                $_SESSION['alertMessage'] = "เพิ่มแผนผลิตเรียบร้อยแล้ว";
                                $_SESSION['alertType'] = 'success';
                            } else {
                                $_SESSION['alertMessage'] = "ไม่สามารถเพิ่มแผนผลิตใน actual_production ได้";
                                $_SESSION['alertType'] = 'error';
                            }
                        }
                    }
                }
            }

            // บันทึก transaction ถ้าสำเร็จ
            mysqli_commit($conn);

            // ตั้งข้อความแจ้งเตือนและเปลี่ยนเส้นทางไปที่หน้า product_plan.php
            header('Location: add_product_plan.php');
            exit();

        } catch (Exception $e) {
            // ยกเลิก transaction ถ้ามีข้อผิดพลาด
            mysqli_rollback($conn);
            $_SESSION['alertMessage'] = "เกิดข้อผิดพลาดในการเพิ่มแผนผลิต";
            $_SESSION['alertType'] = 'error';
            header('Location: add_product_plan.php');
            exit();
        }
    } else {
        // ถ้าข้อมูลไม่ครบ ให้แสดงข้อผิดพลาด
        $_SESSION['alertMessage'] = "กรุณากรอกข้อมูลให้ครบถ้วน";
        $_SESSION['alertType'] = 'error';
        header('Location: add_product_plan.php');
        exit();
    }
}



// Fetch work orders for the dropdown
$work_order_query = "SELECT DISTINCT WO_No FROM orders";
$work_order_result = mysqli_query($conn, $work_order_query);
$work_orders = [];
while ($row = mysqli_fetch_assoc($work_order_result)) {
    $work_orders[] = $row;
}

// Fetch products for the dropdown
$product_query = "SELECT DISTINCT p.Product_ID, 
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' ', 'Ø', t.Pipe_Size, ' ', pe.PE_Name,' - ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS display_name
    FROM product p
    INNER JOIN product_detail pd ON p.P_ID = pd.P_ID  -- JOIN เพื่อดึงชื่อผลิตภัณฑ์จาก product_detail
    INNER JOIN type t ON p.T_ID = t.T_ID  -- JOIN type เพื่อดึงข้อมูลจาก type
    INNER JOIN type_detail td ON t.TD_ID = td.TD_ID  -- JOIN เพื่อดึง TD_Name จาก type_detail
    INNER JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID  -- JOIN เพื่อดึง PE_Name จาก pipeend_detail
";

$product_result = mysqli_query($conn, $product_query);
$products = [];
while ($row = mysqli_fetch_assoc($product_result)) {
    $products[] = $row;
}

include 'admin_index.html';
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
    <a href="../admin/product_plan.php" class="back-link">ย้อนกลับ</a>
    <form action="add_product_plan.php" method="POST" class="addcustomer">
        <h2>เพิ่มแผนผลิต</h2>

        <div class="form-group">
            <label for="date_time">วันที่/เวลา:</label>
            <input type="datetime-local" id="date_time" name="date_time" required>
        </div>
    
        <div class="form-group">
        <label for="wo_no">เลือก W/O:</label>
        <select id="wo_no" name="wo_no" required class="select2" onchange="fetchProducts()">
            <option value="">-- เลือก W/O --</option>
            <?php foreach ($work_orders as $wo): ?>
                <option value="<?php echo htmlspecialchars($wo['WO_No']); ?>"><?php echo htmlspecialchars($wo['WO_No']); ?></option>
            <?php endforeach; ?>
        </select>
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
        // ใช้ Select2 กับ dropdown W/O
        $('.select2').select2({
            placeholder: 'เลือก W/O',
            allowClear: true
        });
    });

    function fetchProducts() {
        var wo_no = $('#wo_no').val();

        if (wo_no) {
            $.ajax({
                url: 'fetch_products.php',  // ไฟล์ PHP ที่ดึง product_id ตาม wo_no
                type: 'POST',
                data: { wo_no: wo_no },
                success: function(response) {
                    $('#product_id').html(response);  // เติมข้อมูลที่ได้กลับมาใน dropdown
                },
                error: function() {
                    Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลผลิตภัณฑ์ได้', 'error');
                }
            });
        } else {
            $('#product_id').html('<option value="">เลือกผลิตภัณฑ์</option>'); // รีเซ็ต dropdown ถ้ายังไม่เลือก W/O
        }
    }

    function fetchSteps() {
        var wo_no = $('#wo_no').val();
        var product_id = $('#product_id').val();

        console.log('WO No:', wo_no);
        console.log('Product ID:', product_id);

        if (wo_no && product_id) {
            $.ajax({
                url: 'fetch_steps.php',
                type: 'POST',
                data: { wo_no: wo_no, product_id: product_id },
                success: function(response) {
                    console.log('Response:', response);  // ดูว่าข้อมูลถูกส่งกลับมาหรือไม่
                    $('#steps-container').html(response);
                },
                error: function() {
                    Swal.fire('Error', 'ไม่สามารถโหลดข้อมูลขั้นตอนได้', 'error');
                }
            });
        } else {
            $('#steps-container').html('');
        }
    }



// Validation: Allow only positive integers in number input fields
document.addEventListener('input', function (event) {
            if (event.target.matches('input[type="number"]')) {
                event.target.value = event.target.value.replace(/[^0-9]/g, ''); // Remove non-numeric characters
            }
        });
        
    
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
            
            // ตรวจสอบประเภทการแจ้งเตือน เพื่อกำหนดหน้าที่ต้อง redirect
            if ($_SESSION['alertType'] === 'success') {
                echo "window.location.href = 'product_plan.php';";
            }
            
            echo "    }
            });";
            
            // ล้างข้อมูลใน session หลังจากแสดงข้อความแจ้งเตือนแล้ว
            unset($_SESSION['alertMessage']);
            unset($_SESSION['alertType']);
        }
    ?>
});


    </script>
</script>
</body>
</html>
