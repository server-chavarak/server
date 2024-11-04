<?php
session_start();
require_once '../db.php';

$error_message = "";

// ดึงข้อมูลผลิตภัณฑ์จากฐานข้อมูลเพื่อนำมาแสดงใน dropdown
$product_query = "SELECT 
        p.Product_ID,
        pd.P_Name, 
        td.TD_Name, 
        t.Pipe_Size,
        t.degree,
        pe.PE_Name
    FROM 
        product p
    INNER JOIN 
        product_detail pd ON p.P_ID = pd.P_ID
    INNER JOIN 
        type t ON p.T_ID = t.T_ID
    INNER JOIN 
        type_detail td ON t.TD_ID = td.TD_ID
    INNER JOIN 
        pipeend_detail pe ON t.PE_ID = pe.PE_ID
";

$product_result = mysqli_query($conn, $product_query);
$products = [];
while ($row = mysqli_fetch_assoc($product_result)) {
    $products[] = $row;
}

// ตรวจสอบการส่งข้อมูลแบบ POST มาหรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = mysqli_real_escape_string($conn, $_POST['Product_ID']);
    $s_id = mysqli_real_escape_string($conn, $_POST['s_id']);
    $st_name = mysqli_real_escape_string($conn, $_POST['st_name']);
    $cycletime = mysqli_real_escape_string($conn, $_POST['cycletime']);

    if (empty($product_id) || empty($s_id) || empty($st_name) || empty($cycletime)) {
        $error_message = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        // ตรวจสอบว่ามี Product_ID อยู่ในฐานข้อมูลหรือไม่
        $product_check_query = "SELECT * FROM product WHERE Product_ID = ?";
        $stmt = $conn->prepare($product_check_query);
        $stmt->bind_param('s', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // ดึงค่า st_id ล่าสุด
            $last_st_id_query = "SELECT MAX(st_id) AS last_st_id FROM work_step";
            $last_st_id_result = mysqli_query($conn, $last_st_id_query);
            $last_st_id_row = mysqli_fetch_assoc($last_st_id_result);
            $st_id = $last_st_id_row['last_st_id'] + 1;

            // เตรียมคำสั่ง SQL สำหรับการเพิ่มข้อมูล
            $insert_query = "INSERT INTO work_step (st_id, product_id, s_id, st_name, cycletime) 
                            VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($insert_query);
            $stmt_insert->bind_param('issss', $st_id, $product_id, $s_id, $st_name, $cycletime);

            // ตรวจสอบการบันทึกข้อมูล
            if ($stmt_insert->execute()) {
                header("Location: work_step.php");
                exit;
            } else {
                $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt_insert->error;
            }
        } else {
            $error_message = "ไม่พบผลิตภัณฑ์ที่มี Product ID ที่ระบุ";
        }
    }
}


mysqli_close($conn);
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
<link rel="stylesheet" href="../css/work_step.css">
<title>ลำดับการทำงาน</title>

</head>
<body>
    <a href="../admin/work_step.php" class="back-link">ย้อนกลับ</a>
       
            <form action="add_work_step.php" method="POST" id="addcustomer">
                <h2>เพิ่มลำดับการทำงาน</h2>
                
                <div class="form-group">
    <label for="Product_ID">ผลิตภัณฑ์:</label>
    <select id="Product_ID" name="Product_ID">
        <option value="">--เลือกผลิตภัณฑ์--</option>
        <?php foreach ($products as $product): ?>
            <option value="<?php echo htmlspecialchars($product['Product_ID']); ?>">
                <?php 
                echo htmlspecialchars(
                    $product['P_Name'] . ' - ' . 
                    $product['TD_Name'] . ' - ' . 
                    ' Ø ' . $product['Pipe_Size'] . 'mm. - ' . 
                    $product['PE_Name'] . 
                    (!empty($product['degree']) ? ' - ' . $product['degree'] . ' องศา' : '')
                ); 
                ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>


                
                <div class="form-group">
                    <label for="s_id">แผนก:</label>
                    <select id="s_id" name="s_id">
                        <option value="">เลือกแผนก</option>
                        <option value="1">Spiral (สไปรอล)</option>
                        <option value="2">Fitting (ประกอบเชื่อม)</option>
                        <option value="3">Hydrotest (เทสน้ำ)</option>
                        <option value="4">Blast (พ่นทราย)</option>
                        <option value="5">PU (ทำสีด้านนอก)</option>
                        <option value="6">Inner Paint (พ่นสีภายในท่อ)</option>
                        <option value="7">Outer Paint (พ่นสีภายนอกท่อ)</option>
                    </select><br><br>
                </div>
                
                <div class="form-group">
                    <label for="st_name">ชื่อขั้นตอน:</label>
                    <input type="text" id="st_name" name="st_name">
                </div>
                
                <div class="form-group">
                    <label for="cycletime">เวลาผลิต (นาที):</label>
                    <input type="text" id="cycletime" name="cycletime"><br><br>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="footer">
                    <button type="submit" class="approve">เพิ่มสินค้า</button>
                    <button type="reset" class="delete">ยกเลิก</button>
                </div>
            </form>
       
</body>
</html>
