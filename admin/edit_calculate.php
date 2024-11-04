<?php
session_start();
require_once '../db.php';

// ตรวจสอบว่ามีการส่งพารามิเตอร์ `Product_ID` หรือไม่
if (!isset($_GET['Product_ID']) || empty($_GET['Product_ID'])) {
    die("คำขอไม่ถูกต้อง: ไม่มีการระบุผลิตภัณฑ์");
}

// รับค่าพารามิเตอร์ `Product_ID`
$product_id = intval($_GET['Product_ID']);

// เช็คการส่งข้อมูลจากฟอร์มหรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $Working_Hours = $_POST['Working_Hours'];
    $cycletime = $_POST['cycletime'];
    $losstime = $_POST['losstime'];

    // คำนวณจำนวนที่ผลิตได้ (Amount)
    if ($cycletime > 0) {
        $Amount = floor(($Working_Hours * 60 - $losstime) / $cycletime);
    } else {
        $Amount = 0;
    }

    // คิวรีเพื่ออัปเดตข้อมูลในตาราง calculate
    $update_sql = "
        UPDATE calculate 
        SET Working_Hours = ?, cycletime = ?, losstime = ?, Amount = ?
        WHERE Product_ID = ?
    ";

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('ddddi', $Working_Hours, $cycletime, $losstime, $Amount, $product_id);

    if ($stmt->execute()) {
        // Redirect ไปยังหน้า calculate.php หลังจากบันทึกสำเร็จ
        header("Location: calculate.php");
        exit; // หยุดการทำงานของสคริปต์
    } else {
        echo "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error;
    }
}

// คิวรีเพื่อดึงข้อมูลที่ต้องการแก้ไข
$sql = "SELECT 
        p.Product_ID,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø' , t.pipe_size, ' ', pe.PE_Name, ' ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS product,
        c.Working_Hours,
        IFNULL(c.cycletime, 0) AS cycletime, 
        IFNULL(c.losstime, 0) AS losstime,
        FLOOR((c.Working_Hours * 60 - IFNULL(c.losstime, 0)) / IFNULL(c.cycletime, 1)) AS Amount
    FROM calculate c
    JOIN product p ON c.Product_ID = p.Product_ID
    JOIN product_detail pd ON p.P_ID = pd.P_ID
    JOIN type t ON p.t_id = t.t_id
    JOIN type_detail td ON t.TD_ID = td.TD_ID
    JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID
    WHERE p.Product_ID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$result = $stmt->get_result();

// ตรวจสอบว่ามีข้อมูลหรือไม่
if ($result->num_rows === 0) {
    die("ไม่พบข้อมูลสำหรับผลิตภัณฑ์นี้");
}

// ดึงข้อมูลแถวแรกที่ได้จากคิวรี
$row = $result->fetch_assoc();

include 'admin_index.html';
?>



<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Maitree">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=K2D">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .header {
            font-size: 24px;
            font-family: 'K2D', sans-serif;
            color: #000000;
            margin: 30px;
            text-align: center;
            padding: 10px;
        }

        .form-container {
            width: 80%;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            font-family: 'Maitree', sans-serif;
        }

        .form-container input,
        .form-container select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
            font-family: 'K2D', sans-serif;
        }

        .form-container button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'K2D', sans-serif;
            
        }

        .form-container button:hover {
            background-color: #45a049;
        }

        .back-link {
            color: #a20c8c;
            margin-left: 85%;
            font-family: 'K2D', sans-serif;
        }

        .back-link:hover {
        color: #cc2121;
        }
    </style>
</head>
<body>
<a href="../admin/calculate.php" class="back-link">ย้อนกลับ</a>
    <div class="header">แก้ไขข้อมูลการคำนวณ</div>
    <div class="form-container">
        <form action="" method="POST">
            <input type="hidden" name="Product_ID" value="<?php echo htmlspecialchars($row['Product_ID']); ?>">

            <label for="product">ผลิตภัณฑ์</label>
            <input type="text" id="product" name="product" value="<?php echo htmlspecialchars($row['product']); ?>" readonly>

            <label for="working_hours">เวลาทำงาน/ชม.</label>
            <input type="number" id="working_hours" name="Working_Hours" value="<?php echo htmlspecialchars($row['Working_Hours']); ?>" required>

            <label for="cycletime">เวลาผลิต 1 ท่อน/นาที</label>
            <input type="number" id="cycletime" name="cycletime" value="<?php echo htmlspecialchars($row['cycletime']); ?>" required>

            <label for="losstime">เวลาที่สูญเสีย/นาที</label>
            <input type="number" id="losstime" name="losstime" value="<?php echo htmlspecialchars($row['losstime']); ?>" required>

            <label for="Amount">จำนวนที่ผลิตได้(ชิ้น)/วัน</label>
            <input type="number" id="Amount" name="Amount" value="<?php echo htmlspecialchars($row['Amount']); ?>" required readonly>

            <button type="submit">บันทึกการเปลี่ยนแปลง</button>
        </form>
    </div>
</body>
</html>
