<?php
session_start();
require_once '../db.php';

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// เริ่มต้นข้อความ
$message = '';

// หากมีการส่งฟอร์ม (POST) ให้ทำการบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $percent_green = isset($_POST['percent_green']) ? $_POST['percent_green'] : 0;
    $percent_orange = isset($_POST['percent_orange']) ? $_POST['percent_orange'] : 0;
    $percent_red = isset($_POST['percent_red']) ? $_POST['percent_red'] : 0;

    // คำสั่ง SQL สำหรับอัปเดตเปอร์เซ็นต์
    $sql = "UPDATE percentages SET 
                Percentage = CASE Status_Color
                    WHEN 'green' THEN ?
                    WHEN 'orange' THEN ?
                    WHEN 'red' THEN ?
                END
            WHERE Status_Color IN ('green', 'orange', 'red')";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ddd', $percent_green, $percent_orange, $percent_red);

        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "บันทึกสำเร็จ";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $message = "บันทีกไม่สำเร็จ " . $stmt->error;
        }

        $stmt->close();
    } else {
        $message = "Failed to prepare SQL statement: " . $conn->error;
    }
}

// ดึงข้อมูลเปอร์เซ็นต์จากฐานข้อมูล
$percentages = array();
$sql = "SELECT Status_Color, Percentage FROM percentages WHERE Status_Color IN ('green', 'orange', 'red')";
$result = $conn->query($sql);

// ตรวจสอบข้อผิดพลาดของ SQL
if (!$result) {
    die("SQL error: " . $conn->error);
}

// ตรวจสอบจำนวนแถว
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $color = strtolower($row['Status_Color']);
        $percentages[$color] = $row['Percentage'];
    }
} else {
    $message = "No data found in database.";
}

include 'admin_index.html';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Maitree">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=K2D">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>กำหนด % แสดงสถานะ</title>
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
        .color-status {
            font-weight: bold;
        }
        .color-green {
            color: green;
        }
        .color-orange {
            color: orange;
        }
        .color-red {
            color: red;
        }
        .percentage-control {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .percentage-control button {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            padding: 0 5px;
        }
        .percentage-control input {
            width: 80px;
            text-align: center;
            font-size: 16px;
            margin: 0 5px;
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
        .message {
            text-align: center;
            margin-top: 20px;
            color: green;
        }
        .back-link {
            color: #a20c8c;
            margin-left: 73%;
            font-family: Maitree;
        }

        .back-link:hover {
        color: #cc2121;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <form id="percentage-form" method="post" action="">
            <div class="header">กำหนด % แสดงสถานะ</div>
            <table>
                <thead>
                    <tr>
                        <th>สีบอกสถานะ</th>
                        <th>ค่า %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="color-status color-green">สีเขียว</td>
                        <td>
                            <div class="percentage-control">
                                <button type="button" onclick="adjustPercentage(this, -1)">-</button>
                                <input type="number" name="percent_green" class="percentage-value" value="<?php echo htmlspecialchars($percentages['green']); ?>" min="0" max="100">
                                <button type="button" onclick="adjustPercentage(this, 1)">+</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="color-status color-orange">สีส้ม</td>
                        <td>
                            <div class="percentage-control">
                                <button type="button" onclick="adjustPercentage(this, -1)">-</button>
                                <input type="number" name="percent_orange" class="percentage-value" value="<?php echo htmlspecialchars($percentages['orange']); ?>" min="0" max="100">
                                <button type="button" onclick="adjustPercentage(this, 1)">+</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="color-status color-red">สีแดง</td>
                        <td>
                            <div class="percentage-control">
                                <button type="button" onclick="adjustPercentage(this, -1)">-</button>
                                <input type="number" name="percent_red" class="percentage-value" value="<?php echo htmlspecialchars($percentages['red']); ?>" min="0" max="100">
                                <button type="button" onclick="adjustPercentage(this, 1)">+</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="submit" class="save-button">บันทึก</button>
        </form>
    </div>
    <script>
        function adjustPercentage(button, delta) {
            const percentageControl = button.parentNode;
            const percentageInput = percentageControl.querySelector('input');
            let currentValue = parseInt(percentageInput.value);
            let newValue = currentValue + delta;

            // Ensure percentage is between 0 and 100
            newValue = Math.max(0, Math.min(100, newValue));

            percentageInput.value = newValue;
        }

        // JavaScript to show SweetAlert based on PHP session variable
        window.onload = function() {
            <?php
            if (isset($_SESSION['alertMessage'])) {
                echo "Swal.fire({
                    icon: 'success',
                    title: '" . $_SESSION['alertMessage'] . "',
                    showConfirmButton: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'percentages.php';
                    }
                });";
                unset($_SESSION['alertMessage']);
            }
            ?>
        };
    </script>
</body>
</html>
