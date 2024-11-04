<?php
require_once '../db.php';
session_start();
 
// กำหนดโซนเวลาให้เป็น Asia/Bangkok
date_default_timezone_set('Asia/Bangkok');
 
// ตรวจสอบการอัปเดตเปอร์เซ็นต์จากฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // อัปเดตเปอร์เซ็นต์
    $greenPercentage = $_POST['green_percentage'];
    $orangePercentage = $_POST['orange_percentage'];
    $redPercentage = $_POST['red_percentage'];
 
    // อัปเดตค่าของสีต่างๆ ในตาราง percentages
    $updateQuery = "UPDATE percentages SET Percentage = CASE LOWER(Status_Color)
                WHEN 'green' THEN ?
                WHEN 'orange' THEN ?
                WHEN 'red' THEN ?
                END WHERE LOWER(Status_Color) IN ('green', 'orange', 'red')";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('ddd', $greenPercentage, $orangePercentage, $redPercentage);
    $stmt->execute();
}
 
// ดึงเปอร์เซ็นต์จากฐานข้อมูล
$percentQuery = "SELECT LOWER(Status_Color) AS Status_Color, Percentage FROM percentages";
$percentResult = $conn->query($percentQuery);
 
// ตรวจสอบคีย์ใน $percentages ถ้าไม่พบให้กำหนดค่าเป็น null
$percentages = [];
while ($row = $percentResult->fetch_assoc()) {
    $percentages[$row['Status_Color']] = $row['Percentage'];
}
 
// ตรวจสอบค่าจากฐานข้อมูล และกำหนดค่าเริ่มต้นหากไม่พบ
$greenPercentage = $percentages['green'] ?? 80;  // ถ้าไม่พบค่าในฐานข้อมูล กำหนดค่าเริ่มต้นเป็น 80
$orangePercentage = $percentages['orange'] ?? 60; // ถ้าไม่พบ กำหนดเป็น 60
$redPercentage = $percentages['red'] ?? 0;       // ถ้าไม่พบ กำหนดเป็น 0
 
if (is_null($greenPercentage) || is_null($orangePercentage) || is_null($redPercentage)) {
    die("ไม่สามารถดึงค่าจากตาราง percentages ได้ กรุณาตรวจสอบว่ามีข้อมูลในตารางหรือไม่");
}
 
// ดึงเวลาทำงานทั้งหมดจากตาราง working_time
$startTimeQuery = "SELECT Work_Time FROM working_time WHERE Work_ID = 1";
$endTimeQuery = "SELECT Work_Time FROM working_time WHERE Work_ID = 2";  
 
$startTimeResult = $conn->query($startTimeQuery);
$endTimeResult = $conn->query($endTimeQuery);
 
$workStartTime = $startTimeResult->fetch_assoc()['Work_Time'];
$workEndTime = $endTimeResult->fetch_assoc()['Work_Time'];    
 
// เปลี่ยนเป็น DateTime object เพื่อคำนวณส่วนต่างของเวลา
$startDateTime = new DateTime($workStartTime);
$endDateTime = new DateTime($workEndTime);
 
$interval = $startDateTime->diff($endDateTime);
$totalHours = $interval->h + ($interval->i / 60);
 
// ตรวจสอบวันที่ล่าสุดที่มีข้อมูลในตาราง actual_production
$filterDate = $_GET['date'] ?? null;
$filterWO = $_GET['wo'] ?? '';
$filterProduct = $_GET['product'] ?? '';
 
if (!$filterDate) {
    $defaultDateQuery = "SELECT DATE(Date_Time) AS latestDate FROM actual_production ORDER BY Date_Time DESC LIMIT 1";
    $defaultDateResult = $conn->query($defaultDateQuery);
    if ($defaultDateRow = $defaultDateResult->fetch_assoc()) {
        $filterDate = $defaultDateRow['latestDate'];
    } else {
        $filterDate = date('Y-m-d'); // กำหนดเป็นวันที่ปัจจุบันถ้าไม่มีข้อมูล
    }
}
 
// ดึงค่า WO_No สำหรับวันที่เลือก
$wo_query = "SELECT DISTINCT WO_No FROM actual_production WHERE DATE(Date_Time) = ?";
$wo_stmt = $conn->prepare($wo_query);
$wo_stmt->bind_param('s', $filterDate);
$wo_stmt->execute();
$wo_result = $wo_stmt->get_result();
 
// กำหนด WO_No ให้เป็นค่าเริ่มต้นถ้ามีข้อมูลในวันนั้น
if (empty($filterWO) && $wo_result->num_rows > 0) {
    $first_wo_row = $wo_result->fetch_assoc();
    $filterWO = $first_wo_row['WO_No'];
}
 
// SQL เพื่อดึงข้อมูลของแต่ละแผนกและขั้นตอนการทำงานจาก actual_production โดยรวม FinishGood, currentPlan
$sql = "SELECT S.S_ID, S.S_Name, ws.St_Name, ws.St_ID,
                SUM(AP.currentPlan) AS currentPlan,
                SUM(AP.FinishGood) AS FinishGood,
                AP.Date_Time,
                AP.product_queue
        FROM actual_production AP
        LEFT JOIN work_step ws ON AP.St_ID = ws.St_ID
        LEFT JOIN section S ON ws.S_ID = S.S_ID
        WHERE DATE(AP.Date_Time) = ?
        AND (AP.WO_No = ? OR ? = '')
        AND (? = '' OR (AP.Product_ID = ?))
        GROUP BY S.S_ID, ws.St_Name, AP.Date_Time, AP.product_queue
        ORDER BY ws.St_ID ASC";
 
$stmt = $conn->prepare($sql);
$stmt->bind_param('sssss', $filterDate, $filterWO, $filterWO, $filterProduct, $filterProduct);
$stmt->execute();
$result = $stmt->get_result();
 
// ฟังก์ชันคำนวณสีตามเปอร์เซ็นต์การผลิตต่อชั่วโมง
function calculateColorAndDisplay($finishGood, $currentPlan, $totalHours, $greenPercentage, $orangePercentage, $redPercentage, $workStartTime, $workEndTime, $productQueue, $st_id) {
    $productionTime = new DateTime(); // เวลาปัจจุบัน
 
    // แปลง workStartTime และ workEndTime เป็น DateTime
    $workStartTime = new DateTime($workStartTime);
    $workEndTime = new DateTime($workEndTime);
 
    // ตรวจสอบเวลาที่ทำการผลิต
    if ($productionTime < $workStartTime) {
        return ['color' => 'green']; // ลงก่อนเวลา แสดงสีเขียว
    } elseif ($productionTime > $workEndTime) {
        return ['color' => 'red']; // ลงหลังเวลา แสดงสีแดง
    }
 
    // กำหนดเวลาเริ่มต้นการผลิต
    if ($st_id == 1) {
        $effectiveStartTime = $workStartTime; // เริ่มนับจากเวลาทำงาน
    } else {
        if ($productQueue > 0) {
            $effectiveStartTime = $workStartTime; // สำหรับแผนกที่ไม่ใช่ St_ID = 1 ใช้เวลาทำงานเริ่มต้น
        } elseif ($productQueue == 0 && $finishGood > 0) {
            return ['color' => 'green']; // ถ้าไม่มีคิวแต่มี finishGood ให้แสดงสีเขียว
        } else {
            return ['color' => '#ddd']; // ถ้าไม่มีคิวและไม่มี finishGood ให้แสดงสีเทา
        }
    }
 
    // คำนวณเวลาที่ผ่านไปตั้งแต่เริ่มการผลิต
    $interval = $effectiveStartTime->diff($productionTime);
    $hoursPassed = $interval->h + ($interval->i / 60); // เวลาที่ผ่านไปเป็นชั่วโมง
    $hoursPassed = min($hoursPassed, $totalHours); // ไม่เกิน totalHours
 
    // คำนวณชิ้นที่ควรผลิตในช่วงเวลาที่ผ่านมา
    $expectedOutput = ($hoursPassed / $totalHours) * $currentPlan;
 
    // ตรวจสอบว่า expectedOutput ไม่เป็น 0
    if ($expectedOutput <= 0) {
        return ['color' => 'red']; // แสดงสีแดงถ้า expectedOutput เป็น 0 หรือไม่สามารถผลิตได้
    }
 
    // คำนวณเปอร์เซ็นต์การผลิตที่เสร็จสมบูรณ์
    $percentComplete = ($finishGood / $expectedOutput) * 100;
 
    if ($percentComplete >= $greenPercentage) {
        return ['color' => 'green']; // ผลิตได้ตามแผน
    } elseif ($percentComplete >= $orangePercentage) {
        return ['color' => 'orange']; // ผลิตใกล้เคียงกับแผน
    } else {
        return ['color' => 'red']; // ผลิตน้อยกว่าเกณฑ์สีแดง
    }
}
include 'admin_index.html';
?>
 
 
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Production Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=K2D:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'K2D', sans-serif;
    }
    .header-container {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        margin: 10px 40px;
    }
    h1 {
        font-size: 25px;
        color: #dd5009;
        text-decoration: underline;
        margin: 0;
    }
    .filter-container {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 20px;
        margin-left: 15px;
    }
    .filter-container select,
    .filter-container input {
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .chart-container {
        display: flex;
        flex-wrap: wrap;
        gap: 60px 160px;
        justify-content: center;
        margin-top: 35px;
        max-height: 75vh;
        overflow-y: auto;
        padding-right: 10px;
    }
 
    .chart-box {
        width: 215px;
        text-align: center;
    }
 
    .legend-container {
        display: flex;
        gap: 20px;
        margin: 13px ;
    }
 
    .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 14px;
    }
 
    .legend-color {
        width: 10px;
        height: 10px;
        border-radius: 2px;
    }
 
    .legend-color.red {
        background-color: #FF0000;
    }
 
    .legend-color.orange {
        background-color: #FFA500;
    }
 
    .legend-color.green {
        background-color: #32CD32;
    }
 
    </style>
</head>
<body>
 
<div class="header-container">
    <h1>Production Dashboard</h1>
 
    <div class="filter-container">
        <input type="date" id="filter-date" value="<?php echo $filterDate; ?>" onchange="applyFilters()">
        <select id="filter-wo" onchange="applyFilters()">
            <option value="">-- เลือก W/O --</option>
            <?php
            $wo_result->data_seek(0);
            while ($wo_row = $wo_result->fetch_assoc()) {
                $selected = ($filterWO == $wo_row['WO_No']) ? 'selected' : '';
                echo '<option value="'.$wo_row['WO_No'].'" '.$selected.'>'.$wo_row['WO_No'].'</option>';
            }
            ?>
        </select>
 
        <select id="filter-product" onchange="applyFilters()" <?php echo empty($filterWO) ? 'disabled' : ''; ?>>
            <option value="">-- เลือกผลิตภัณฑ์ --</option>
            <?php
            if (!empty($filterWO)) {
                $product_query = "SELECT
                                pr.Product_ID,
                                CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø', t.Pipe_Size, ' ', pe.PE_Name, '  ', IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS Product_name
                            FROM order_details od
                            LEFT JOIN product pr ON od.Product_ID = pr.Product_ID
                            LEFT JOIN product_detail pd ON pr.P_ID = pd.P_ID    
                            LEFT JOIN type t ON pr.T_ID = t.T_ID                
                            LEFT JOIN type_detail td ON t.TD_ID = td.TD_ID      
                            LEFT JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID  
                            WHERE od.WO_No = ?";
               
                $product_stmt = $conn->prepare($product_query);
                $product_stmt->bind_param('s', $filterWO);
                $product_stmt->execute();
                $product_result = $product_stmt->get_result();
                while ($product_row = $product_result->fetch_assoc()) {
                    echo '<option value="'.$product_row['Product_ID'].'" '.($filterProduct == $product_row['Product_ID'] ? 'selected' : '').'>'.$product_row['Product_name'].'</option>';
                }
            }
            ?>
        </select>
 
        <div class="legend-container">
            <div class="legend-item">
                <div class="legend-color red"></div>
                <span>ผลิตช้ากว่าแผน</span>
            </div>
            <div class="legend-item">
                <div class="legend-color orange"></div>
                <span>เริ่มผลิตช้ากว่าแผน</span>
            </div>
            <div class="legend-item">
                <div class="legend-color green"></div>
                <span>ผลิตตรงตามแผน</span>
            </div>
    </div>
 
</div>
 
 
    </div>
</div>
 
<div class="chart-container">
    <?php
   // วนลูปดึงข้อมูลและแสดงแผนภูมิ
   while ($row = $result->fetch_assoc()) {
        $currentPlan = $row['currentPlan'] ?? 0;
        $finishGood = $row['FinishGood'] ?? 0;
        $productQueue = $row['product_queue'] ?? 0;
        $st_id = $row['St_ID'];
 
       
 
        // คำนวณสีและเวลาที่ล่าช้า
        $resultData = calculateColorAndDisplay($finishGood, $currentPlan, $totalHours, $greenPercentage, $orangePercentage, $redPercentage, $workStartTime, $workEndTime, $productQueue, $st_id);
        $color = $resultData['color'];
 
    ?>
        <div class="chart-box">
            <h3><?php echo "{$row['S_Name']} ({$row['St_Name']})"; ?></h3>
            <canvas id="chart-<?php echo $row['St_ID']; ?>"></canvas>
            <script>
    var ctx = document.getElementById('chart-<?php echo $row['St_ID']; ?>').getContext('2d');
   
    // กำหนดค่า labels และ datasets ตามเงื่อนไข productQueue
    var labels, data;
   
    if (<?php echo $productQueue, $finishGood; ?> >= 0) {
        // ถ้า productQueue, $finishGood == 0 ไม่แสดง labels
        labels = ['ผลิตจริง', 'แผนผลิต'];
        data = [<?php echo $finishGood; ?>, <?php echo max(0, $currentPlan - $finishGood); ?>];
        // หรือค่าอื่นที่คุณต้องการแสดงเมื่อไม่มีการผลิต
    }
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor:  ['<?php echo $color; ?>' , '#ddd'],
            }]
        },
        options: {
            responsive: true,
            cutout: '55%',
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(tooltipItem) {
                            var dataValue = tooltipItem.raw;
                            var total = <?php echo $currentPlan; ?>;
                            var percentage = (dataValue / total) * 100;
                            return Math.min(100, Math.round(percentage * 100) / 100) + '%';
                        }
                    }
                }
            },
        },
        plugins: [{
            beforeDraw: function(chart) {
                var width = chart.width,
                    height = chart.height,
                    ctx = chart.ctx;
                ctx.restore();
                var fontSize = (height / 180).toFixed(2);
                ctx.font = fontSize + "em sans-serif";
                ctx.textBaseline = "middle";
 
                var text = "<?php echo $finishGood . '/' . $currentPlan; ?>",
                    textX = Math.round((width - ctx.measureText(text).width) / 2),
                    textY = height / 1.7;
 
                ctx.fillText(text, textX, textY);
                ctx.save();
            }
        }]
    });
 
 
</script>
 
        </div>
    <?php } ?>
</div>
 
<script>
function applyFilters() {
    const date = document.getElementById('filter-date').value;
    const wo = document.getElementById('filter-wo').value;
    const product = document.getElementById('filter-product').value;
 
    window.location.href = `?date=${date}&wo=${wo}&product=${product}`;
}
</script>
 
</body>
</html>