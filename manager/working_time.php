<?php
session_start();
require_once '../db.php';
 
// ตรวจสอบการเชื่อมต่อกับฐานข้อมูล
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
 
// ประกาศตัวแปรเริ่มต้นสำหรับฟอร์ม
$start_time = '';
$end_time = '';
 
// ตรวจสอบว่ามีการส่งฟอร์มแล้วหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['start_time']) && isset($_POST['end_time'])) {
        // รับค่าจากแบบฟอร์ม
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
 
        // อัพเดตข้อมูลในฐานข้อมูล
        $sql_start = "UPDATE working_time SET Work_Time = '$start_time' WHERE Work_ID = 1 AND Status_Time = 'เวลาเริ่มงาน'";
        $sql_end = "UPDATE working_time SET Work_Time = '$end_time' WHERE Work_ID = 2 AND Status_Time = 'เวลาเลิกงาน'";
 
        if ($conn->query($sql_start) === TRUE && $conn->query($sql_end) === TRUE) {
            $_SESSION['alertMessage'] = "บันทึกเวลาการทำงานสำเร็จ";  // เก็บข้อความยืนยันไว้ใน session
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $conn->error;
        }
 
        // ตรวจสอบการอัพเดตข้อมูล
        if ($conn->query($sql_start) === TRUE && $conn->query($sql_end) === TRUE) {
            echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'อัพเดตสำเร็จ',
                        text: 'เวลาการทำงานถูกอัพเดตเรียบร้อยแล้ว!'
                    }).then((result) => {
                        window.location.href = 'working_time.php'; // เปลี่ยนเป็นหน้าที่คุณต้องการจะให้ redirect ไป
                    });
                  </script>";
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        echo "กรุณากรอกข้อมูลให้ครบถ้วน";
    }
}
 
// ดึงข้อมูลเวลาปัจจุบันจากฐานข้อมูลเพื่อนำมาแสดงในฟอร์ม
$sql_get_times = "SELECT Work_ID, Work_Time FROM working_time WHERE Work_ID IN (1, 2)";
$result = $conn->query($sql_get_times);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        if ($row['Work_ID'] == 1) {
            $start_time = $row['Work_Time'];
        }
        if ($row['Work_ID'] == 2) {
            $end_time = $row['Work_Time'];
        }
    }
} else {
    echo "ไม่พบข้อมูลเวลาในฐานข้อมูล";
}
 
$conn->close();
include 'manager_index.html';
?>
 
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Maitree">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=K2D">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <title>เวลาการทำงาน</title>
        <style>
            .form-container {
                display: flex;
                justify-content: center;
                align-items: center;
                width: 100%;
                margin-top: 60px;
            }
            .header {
                font-size: 23px;
                font-family: K2D;
                font-weight: bold;
                margin-left: 30%;
                margin-top: 2%;
            }
            table {
                width: 100%;
                max-width: 500px;
                border-collapse: collapse;
                box-sizing: border-box;
                margin-top: 5%;
            }
            th, td {
                border: 1px solid #bdbdbd;
                padding: 12px 60px;
                text-align: center;
                font-family: Maitree;
                font-size: 18px;
            }
            th {
                background-color: #142338;
                color: #fff;
            }
            input[type="time"] {
                width: 100%;
                padding: 8px;
                box-sizing: border-box;
                font-family: Maitree;
                font-size: 16px;
                border: 1px solid #bdbdbd;
            }
            .save-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            font-size: 16px;
            color: #fff;
            background-color: #007bff;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            }
            .save-button:hover {
                background-color: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="form-container">
            <form id="workingTime-form" method="post" action="working_time.php"> <!-- เปลี่ยน action เป็นชื่อไฟล์นี้ -->
                <div class="header">เวลาการทำงาน</div>
                <table>
                    <thead>
                         <tr>
                        <th>สถานะเวลาทำงาน</th>
                        <th>เวลา</th>
                    </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>เวลาเริ่มงาน</td>
                            <td><input type="time" name="start_time" value="<?php echo htmlspecialchars($start_time); ?>" required></td>
                        </tr>
                        <tr>
                            <td>เวลาเลิกงาน</td>
                            <td><input type="time" name="end_time" value="<?php echo htmlspecialchars($end_time); ?>" required></td>
                        </tr>
                    </tbody>
                </table>
                <button type="submit" class="save-button">บันทึก</button>
            </form>
        </div>
        <script>
            window.onload = function() {
            <?php
            if (isset($_SESSION['alertMessage'])) {
                echo "Swal.fire({
                    icon: 'success',
                    title: '" . $_SESSION['alertMessage'] . "',
                    showConfirmButton: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'working_time.php';
                    }
                });";
                unset($_SESSION['alertMessage']);
            }
            ?>
        };
        </script>
    </body>
</html>