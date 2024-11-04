<?php
session_start();
require_once '../db.php';
 
// Fetch pipe types from the database
$sql = "SELECT TD_ID, TD_Name FROM Type_Detail";
$pipeNameResult = $conn->query($sql);
 
// Fetch pipe end details
$sql = "SELECT PE_ID, PE_Name FROM PipeEnd_Detail";
$pipeEndResult = $conn->query($sql);
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t_name_id = $_POST['TD_ID'];
    $pipeSize1 = $_POST['Pipe_Size1'] ?? '';
    $pipeSize2 = $_POST['Pipe_Size2'] ?? '';
    $pipeSize3 = $_POST['Pipe_Size3'] ?? '';
   
    // Constructing the pipe size string
    $result = $pipeSize1;
    if (!empty($pipeSize2)) {
        $result .= (!empty($result) ? '*' : '') . $pipeSize2;
    }
    if (!empty($pipeSize3)) {
        $result .= (!empty($result) ? '*' : '') . $pipeSize3;
    }
   
    $pipe_end = $_POST['Pipe_End'];
    $degree = $_POST['degree'];
 
    // Fetch the type name using its ID
    $get_name_sql = "SELECT TD_Name FROM Type_Detail WHERE TD_ID = ?";
    $get_name_stmt = $conn->prepare($get_name_sql);
    $get_name_stmt->bind_param("i", $t_name_id);
    $get_name_stmt->execute();
    $get_name_stmt->bind_result($t_name);
    $get_name_stmt->fetch();
    $get_name_stmt->close();
 
    // Fetch the pipe end name using its ID
    $get_end_sql = "SELECT PE_Name FROM PipeEnd_Detail WHERE PE_ID = ?";
    $get_end_stmt = $conn->prepare($get_end_sql);
    $get_end_stmt->bind_param("i", $pipe_end);
    $get_end_stmt->execute();
    $get_end_stmt->bind_result($Pipe_End);
    $get_end_stmt->fetch();
    $get_end_stmt->close();
 
    // Check for duplicate entries
    $check_sql = "SELECT * FROM type WHERE TD_ID = ? AND Pipe_Size = ? AND PE_ID = ? AND degree = ?";
    $check_stmt = $conn->prepare($check_sql);
    if ($check_stmt === false) {
        die('SQL Prepare Error: ' . htmlspecialchars($conn->error));
    }
 
    $check_stmt->bind_param("isss", $t_name_id, $result, $pipe_end, $degree);
    $check_stmt->execute();
    $check_stmt->store_result();
 
    if ($check_stmt->num_rows > 0) {
        $_SESSION['alertMessage'] = "ข้อมูลนี้มีอยู่ในระบบแล้วไม่สามารถเพิ่มได้";
        $_SESSION['alertIcon'] = "error";
    } else {
        // Insert new data
        $insert_sql = "INSERT INTO type (TD_ID, Pipe_Size, PE_ID, degree) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        if ($insert_stmt === false) {
            die('SQL Prepare Error: ' . htmlspecialchars($conn->error));
        }
        $insert_stmt->bind_param("isss", $t_name_id, $result, $pipe_end, $degree);
        $insert_stmt->execute();
 
        if ($insert_stmt->affected_rows > 0) {
            $_SESSION['alertMessage'] = "เพิ่มประเภทและขนาดท่อเรียบร้อยแล้ว";
            $_SESSION['alertIcon'] = "success";
        } else {
            $_SESSION['alertMessage'] = "ไม่สามารถเพิ่มประเภทและขนาดท่อได้";
            $_SESSION['alertIcon'] = "error";
        }
 
        $insert_stmt->close();
    }
    $check_stmt->close();
 
    // Redirect to the same page after completion
    header('Location: add_type.php');
    exit();
}
 
include 'admin_index.html';
?>
 
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มประเภทและขนาดท่อ</title>
    <link rel="stylesheet" href="../css/type.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
  
    </head>
<body>
    <a href="../admin/type.php" class="back-link">ย้อนกลับ</a>
    <div class="add">
        <form action="add_type.php" method="POST" id="addcustomer" >
            <h2>เพิ่มประเภทและขนาดท่อ</h2>
 
            <div class="form-group">
                <label for="TD_ID" class="asterisk">ชื่อประเภทท่อ :</label>
                <select id="TD_ID" name="TD_ID" required>
                    <option value="">เลือก...</option>
                    <?php
                    if ($pipeNameResult && $pipeNameResult->num_rows > 0) {
                        while ($row = $pipeNameResult->fetch_assoc()) {
                            echo "<option value=\"" . htmlspecialchars($row['TD_ID']) . "\">" . htmlspecialchars($row['TD_Name']) . "</option>";
                        }
                    }
                    ?>
                </select>
                <div class="add_1"><a href="../admin/add_Type_Detail.php" class="add-link"> +</a></div>
            </div>
 
            <div class="form-group">
    <label for="Pipe_Size" class="asterisk">ขนาดท่อ :</label>
 
   
    <span class="input-group-text" id="basic-addon1">ขนาด</span>
    <input type="number" class="form-control" name="Pipe_Size1" aria-label="Pipe_Size1" aria-describedby="basic-addon1" step="0.01" min="0">
 
    <span class="input-group-text" id="basic-addon2">ความหนา</span>
    <input type="number" class="form-control" name="Pipe_Size2" aria-label="Pipe_Size2" aria-describedby="basic-addon2" step="0.01" min="0">
 
    <span class="input-group-text" id="basic-addon3">ความยาว</span>
    <input type="number" class="form-control" name="Pipe_Size3" aria-label="Pipe_Size3" aria-describedby="basic-addon3" step="0.01" min="0">
 
 
</div>
 
 
 
 
            <div class="form-group">
                <label for="Pipe_End" class="asterisk">ลักษณะปลายท่อ :</label>
                <select id="Pipe_End" name="Pipe_End" required>
                    <option value="">เลือก...</option>
                    <?php
                    if ($pipeEndResult && $pipeEndResult->num_rows > 0) {
                        while ($row = $pipeEndResult->fetch_assoc()) {
                            echo "<option value=\"" . htmlspecialchars($row['PE_ID']) . "\">" . htmlspecialchars($row['PE_Name']) . "</option>";
                        }
                    }
                    ?>
                </select>
 
                <div class="add_1"><a href="../admin/add_PipeEnd_Detail.php" class="add-link"> +</a></div>
            </div>
           
            <div class="form-group">
                <label for="degree" class="asterisk">องศา : </label>
                <input type="number" id="degree" name="degree" step="0.01" min="0" max="360">
            </div>
   
            <!-- แสดงข้อความผิดพลาด (ถ้ามี) -->
            <?php if (!empty($error_message)): ?>
                <div class="error"><?php echo $error_message; ?></div>
            <?php endif; ?>
 
            <div class="footer">
                <button type="submit" class="approve">เพิ่ม</button>
                <button type="reset" class="delete">ยกเลิก</button>
            </div>
        </form>
    </div>
 
    <!-- สคริปต์ JavaScript สำหรับแสดง SweetAlert2 -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php
            if (isset($_SESSION['alertMessage']) && isset($_SESSION['alertIcon'])) {
                echo "Swal.fire({
                    icon: '{$_SESSION['alertIcon']}',
                    title: '{$_SESSION['alertMessage']}',
                    showConfirmButton: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'type.php';
                    }
                });";
                unset($_SESSION['alertMessage']);
                unset($_SESSION['alertIcon']);
            }
            ?>
           
        });
    </script>
</body>
</html>