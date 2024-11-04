<?php
session_start();
require_once '../db.php';
 
$error_message = "";
$st_id = isset($_GET['st_id']) ? $_GET['st_id'] : '';
$stepCounter = isset($_GET['stepCounter']) ? $_GET['stepCounter'] : '';
 
// ตรวจสอบว่ามี st_id ที่ถูกส่งมาหรือไม่
if (empty($st_id)) {
    echo "ไม่มี st_id ที่ถูกส่งมา";
    exit;
}
 
// ดึงข้อมูลเดิมจากฐานข้อมูล
$select_query = "SELECT st_id, product_id, s_id, st_name, cycletime FROM work_step WHERE st_id = ?";
$stmt = mysqli_prepare($conn, $select_query);
 
if (!$stmt) {
    die("การเตรียมคำสั่ง SQL ล้มเหลว: " . mysqli_error($conn));
}
 
mysqli_stmt_bind_param($stmt, 's', $st_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$step = mysqli_fetch_assoc($result);
 
// ตรวจสอบว่ามีข้อมูลเดิมหรือไม่
if (!$step) {
    echo "ไม่พบข้อมูลสำหรับ st_id ที่ระบุ";
    exit;
}
 
// ดึงข้อมูลผลิตภัณฑ์จากฐานข้อมูลเพื่อนำมาแสดงใน dropdown
$product_query = "SELECT
        p.Product_ID,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.Pipe_Size, ' ', pe.PE_Name, ' ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS display_name
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
 
// ตรวจสอบว่ามีการส่งข้อมูล POST มาเพื่อบันทึกการแก้ไข
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $s_id = $_POST['s_id'];
    $st_name = $_POST['st_name'];
    $cycletime = $_POST['cycletime'];
 
    // อัปเดตข้อมูลในฐานข้อมูล
    $update_query = "UPDATE work_step SET product_id = ?, s_id = ?, st_name = ?, cycletime = ? WHERE st_id = ?";
 
    $stmt_update = mysqli_prepare($conn, $update_query);
 
    if (!$stmt_update) {
        die("การเตรียมคำสั่ง SQL ล้มเหลว: " . mysqli_error($conn));
    }
 
    mysqli_stmt_bind_param($stmt_update, 'sssss', $product_id, $s_id, $st_name, $cycletime, $st_id);
 
    if (mysqli_stmt_execute($stmt_update)) {
        // เปลี่ยนเส้นทางกลับไปยังหน้าแสดงข้อมูล
        header("Location: work_step.php");
        exit;
    } else {
        $error_message = "ไม่สามารถอัปเดตข้อมูลได้: " . mysqli_error($conn);
    }
}
 
mysqli_close($conn);
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
        <link rel="stylesheet" href="../css/work_step.css">
        <title>แก้ไขลำดับการทำงาน</title>
       
    </head>
    <body>
        <a href="../manager/work_step.php" class="back-link">ย้อนกลับ</a>
         
                <form action="edit_work_step.php?st_id=<?php echo urlencode($st_id); ?>" method="POST"  id = "addcustomer">
                    <h2>แก้ไขลำดับการทำงาน</h2>
                   
                    <div class="form-group">
                        <label for="stepCounter">ลำดับ:</label>
                        <input type="text" id="stepCounter" name="stepCounter" value="<?php echo htmlspecialchars($stepCounter); ?>" readonly>
                    </div>
                                       
                    <div class="form-group">
                        <label for="product_id">ผลิตภัณฑ์:</label>
                        <select id="product_id" name="product_id">
                            <option value="">เลือกผลิตภัณฑ์</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['Product_ID']; ?>" <?php echo ($product['Product_ID'] == $step['product_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['display_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select><br><br>
                    </div>
 
                   
                    <div class="form-group">
                        <label for="s_id">แผนก:</label>
                        <select id="s_id" name="s_id">
                            <option value="">เลือกแผนก</option>
                            <option value="1" <?php echo ($step['s_id'] == 1) ? 'selected' : ''; ?>>Spiral (สไปรอล)</option>
                            <option value="2" <?php echo ($step['s_id'] == 2) ? 'selected' : ''; ?>>Fitting (ประกอบเชื่อม)</option>
                            <option value="3" <?php echo ($step['s_id'] == 3) ? 'selected' : ''; ?>>Hydrotest (เทสน้ำ)</option>
                            <option value="4" <?php echo ($step['s_id'] == 4) ? 'selected' : ''; ?>>Blast (พ่นทราย)</option>
                            <option value="5" <?php echo ($step['s_id'] == 5) ? 'selected' : ''; ?>>PU (ทำสีด้านนอก)</option>
                            <option value="6" <?php echo ($step['s_id'] == 6) ? 'selected' : ''; ?>>Inner Paint (พ่นสีภายในท่อ)</option>
                            <option value="7" <?php echo ($step['s_id'] == 7) ? 'selected' : ''; ?>>Outer Paint (พ่นสีภายนอกท่อ)</option>
                        </select>
                    </div>
                   
                    <div class="form-group">
                        <label for="st_name">ชื่อขั้นตอน:</label>
                        <input type="text" id="st_name" name="st_name" value="<?php echo isset($step['st_name']) ? htmlspecialchars($step['st_name']) : ''; ?>">
                    </div>
                   
                    <div class="form-group">
                        <label for="cycletime">เวลาผลิต (นาที):</label>
                        <input type="text" id="cycletime" name="cycletime" value="<?php echo isset($step['cycletime']) ? htmlspecialchars($step['cycletime']) : ''; ?>">
                    </div>
 
                    <?php if (!empty($error_message)): ?>
                        <div class="error"><?php echo $error_message; ?></div>
                    <?php endif; ?>
 
                    <div class="footer">
                        <button type="submit" class="approve">บันทึก</button>
                        <button type="reset" class="delete">ยกเลิก</button>
                    </div>
                </form>
           
    </body>
</html>
 