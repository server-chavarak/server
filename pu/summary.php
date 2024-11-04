<?php
require_once '../db.php';

// กำหนด Time Zone และ Locale สำหรับประเทศไทย
date_default_timezone_set('Asia/Bangkok');
setlocale(LC_TIME, 'th_TH.UTF-8');

// ฟังก์ชันแปลงวันที่เป็นรูปแบบภาษาไทย
function convertDateToThai($date, $filterType) {
    $thai_months_short = array(
        "01" => "ม.ค.", "02" => "ก.พ.", "03" => "มี.ค.", "04" => "เม.ย.", 
        "05" => "พ.ค.", "06" => "มิ.ย.", "07" => "ก.ค.", "08" => "ส.ค.", 
        "09" => "ก.ย.", "10" => "ต.ค.", "11" => "พ.ย.", "12" => "ธ.ค."
    );
    
    $month = date('m', strtotime($date));
    $year = date('Y', strtotime($date)) + 543;
    $day = date('d', strtotime($date));

    if ($filterType === 'year') {
        return $thai_months_short[$month];
    } else if ($filterType === 'month') {
        return $day . ' ' . $thai_months_short[$month] . ' ' . $year;
    } else {
        return $day . ' ' . $thai_months_short[$month] . ' ' . $year;
    }
}

// ฟังก์ชันกรองข้อมูลสำหรับแผนก pu
function filterData($filterType, $date) {
    global $conn;
    $departmentData = [];

    // SQL Query เฉพาะแผนก pu (S_ID = 5)
    switch ($filterType) {
        case 'day':
            $sql = "SELECT S_ID, SUM(currentPlan) AS currentPlan, SUM(FinishGood) AS FinishGood, DATE(Date_Time) AS Date_Time 
                    FROM actual_production 
                    WHERE DATE(Date_Time) = '$date' AND S_ID = 5 
                    GROUP BY S_ID, DATE(Date_Time) 
                    ORDER BY S_ID, Date_Time";
            break;
        case 'month':
            $sql = "SELECT S_ID, SUM(currentPlan) AS currentPlan, SUM(FinishGood) AS FinishGood, DATE(Date_Time) AS Date_Time 
                    FROM actual_production 
                    WHERE YEAR(Date_Time) = YEAR('$date') AND MONTH(Date_Time) = MONTH('$date') AND S_ID = 5 
                    GROUP BY S_ID, DATE(Date_Time)
                    ORDER BY S_ID, Date_Time";
            break;
        case 'year':
            $sql = "SELECT S_ID, SUM(currentPlan) AS currentPlan, SUM(FinishGood) AS FinishGood, 
                           DATE_FORMAT(Date_Time, '%Y-%m') AS Month 
                    FROM actual_production 
                    WHERE YEAR(Date_Time) = YEAR('$date') AND S_ID = 5 
                    GROUP BY S_ID, Month 
                    ORDER BY S_ID, Month";
            break;
        default:
            $sql = "SELECT S_ID, SUM(currentPlan) AS currentPlan, SUM(FinishGood) AS FinishGood, DATE(Date_Time) AS Date_Time 
                    FROM actual_production 
                    WHERE DATE(Date_Time) = CURDATE() AND S_ID = 5
                    GROUP BY S_ID, DATE(Date_Time) 
                    ORDER BY S_ID, Date_Time";
            break;
    }
    $result = $conn->query($sql);

    while ($row = $result->fetch_assoc()) {
        $departmentData[] = [
            'date' => ($filterType === 'year') ? $row['Month'] : $row['Date_Time'],
            'plan' => $row['currentPlan'],
            'finish' => $row['FinishGood'],
        ];
    }

    return $departmentData;
}

// รับค่าการกรองจากผู้ใช้
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'day'; // กรองรายวันเป็นค่าเริ่มต้น
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); // วันที่ปัจจุบันเป็นค่าเริ่มต้น

// เรียกฟังก์ชันเพื่อกรองข้อมูล
$departmentData = filterData($filterType, $date);

include 'pu_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>pu Production Summary</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=K2D:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'K2D', sans-serif;
        }
        h1 {
            text-align: center; 
            font-size: 25px;
            margin-top: 1%;
        }

        .chart-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 80vh; /* ใช้ความสูงของหน้าจอ */
            padding: 40px; /* เอา padding ออกเพื่อให้กราฟชิดขอบจอ */
            margin: 0;
            border: 1px solid #ddd;
            
        }

        .chart-box {
            width: 90vw; /* ความกว้างเต็มหน้าจอ */
            height: 80vh; /* ความสูงเต็มหน้าจอ */
            padding: 10px; /* เพิ่ม padding เล็กน้อยถ้าต้องการ */
            box-sizing: border-box;
            
        }
      

        .filter-container {
            display: flex;
            justify-content: flex-end;
            margin-right: 50px;
            align-items: center;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .filter-container label, 
        .filter-container select, 
        .filter-container input {
            margin-left: 10px;
            font-family: 'K2D', sans-serif;
        }

        .filter-container select, 
        .filter-container input {
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<h1>PU Production Summary</h1>

<!-- Dropdown สำหรับการกรองข้อมูล -->
<div class="filter-container">
    <label for="filter">เลือกช่วงเวลา:</label>
    <select id="filter" onchange="updateFilter()">
        <option value="day" <?php if ($filterType == 'day') echo 'selected'; ?>>รายวัน</option>
        <option value="month" <?php if ($filterType == 'month') echo 'selected'; ?>>รายเดือน</option>
        <option value="year" <?php if ($filterType == 'year') echo 'selected'; ?>>รายปี</option>
    </select>
    
    <!-- Input สำหรับเลือกวันที่ -->
    <input type="date" id="date" value="<?php echo $date; ?>" onchange="updateFilter()" style="display: <?php echo ($filterType == 'day') ? 'inline-block' : 'none'; ?>;">

    
    <!-- Dropdown สำหรับเลือกเดือน -->
    <select id="month" onchange="updateFilter()" style="display: <?php echo ($filterType == 'month') ? 'inline-block' : 'none'; ?>;">
        <?php 
        $months = ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
        for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo $m; ?>" <?php if (date('m', strtotime($date)) == $m) echo 'selected'; ?>><?php echo $months[$m - 1]; ?></option>
        <?php endfor; ?>
    </select>

    <!-- Dropdown สำหรับเลือกปีสำหรับตัวกรองรายเดือน -->
    <select id="monthYear" onchange="updateFilter()" style="display: <?php echo ($filterType == 'month') ? 'inline-block' : 'none'; ?>;">
        <?php for ($y = date('Y'); $y >= 2010; $y--): ?>
            <option value="<?php echo $y; ?>" <?php if (date('Y', strtotime($date)) == $y) echo 'selected'; ?>><?php echo $y + 543; ?></option>
        <?php endfor; ?>
    </select>
    
   <!-- Dropdown สำหรับเลือกปี -->
    <select id="year" onchange="updateFilter()" style="display: <?php echo ($filterType == 'year') ? 'inline-block' : 'none'; ?>;">
        <?php for ($y = date('Y'); $y >= 2010; $y--): ?> <!-- ขยายช่วงปีจาก 2020 เป็น 2010 -->
            <option value="<?php echo $y; ?>" <?php if (date('Y', strtotime($date)) == $y) echo 'selected'; ?>>
                <?php echo $y + 543; ?> <!-- แปลงปี ค.ศ. เป็น พ.ศ. -->
            </option>
        <?php endfor; ?>
    </select>

</div>

<div class="chart-container">
    <div class="chart-box">
        <h2>PU</h2>
        <canvas id="chart-pu" width="900" height="300"></canvas>
    </div>
    <script>
        const ctxpu = document.getElementById('chart-pu').getContext('2d');

        <?php if ($filterType === 'day') { ?>
        // กรณีรายวัน แสดงแท่งคู่สำหรับแผนผลิตและผลิตจริง พร้อมวันที่ใต้กราฟ
        new Chart(ctxpu, {
            type: 'bar',
            data: {
                labels: ['แผนผลิต', 'ผลิตจริง'], // ชื่อของแท่งกราฟ
                datasets: [{
                    label: 'pu',
                    data: [<?php foreach ($departmentData as $data) { echo $data['plan'] . "," . $data['finish'] . ","; } ?>],
                    backgroundColor: ['rgba(255, 165, 0, 0.6)', 'rgba(0, 128, 0, 0.6)'],
                    borderColor: ['rgba(255, 165, 0, 1)', 'rgba(0, 128, 0, 1)'],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: '<?php echo convertDateToThai($date, "day"); ?>' // แสดงวันที่ใต้กราฟ
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php } else { ?>
        // กรณีอื่น ๆ แสดงแผนภูมิแบบเดิม
        new Chart(ctxpu, {
            type: 'bar',
            data: {
                labels: [<?php 
                    foreach ($departmentData as $data) { 
                        echo "'" . convertDateToThai($data['date'], $filterType) . "',"; 
                    } 
                ?>],
                datasets: [
                    {
                        type: 'line',
                        label: 'แผนผลิต',
                        data: [<?php foreach ($departmentData as $data) { echo $data['plan'] . ","; } ?>],
                        borderColor: 'rgba(255, 165, 0, 1)',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(255, 165, 0, 1)',
                        fill: false
                    },
                    {
                        type: 'bar',
                        label: 'ผลิตจริง',
                        data: [<?php foreach ($departmentData as $data) { echo $data['finish'] . ","; } ?>],
                        backgroundColor: 'rgba(0, 128, 0, 0.6)',
                        borderColor: 'rgba(0, 128, 0, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php } ?>
    </script>
</div>

<script>
// ฟังก์ชันสำหรับการกรองข้อมูล
function updateFilter() {
    const filter = document.getElementById('filter').value;
    let date = document.getElementById('date').value;

    if (filter === 'month') {
        const month = document.getElementById('month').value;
        const year = document.getElementById('monthYear').value;
        date = `${year}-${month}-01`;
    } else if (filter === 'year') {
        const year = document.getElementById('year').value;
        date = `${year}-01-01`;
    }

    // ส่งค่า filter และ date กลับไปที่หน้าเว็บใหม่
    window.location.href = `?filter=${filter}&date=${date}`;
}

// เปลี่ยนการแสดงผลของ input เมื่อเปลี่ยนการกรอง
document.getElementById('filter').addEventListener('change', function() {
    const filter = this.value;
    document.getElementById('date').style.display = (filter === 'day') ? 'inline-block' : 'none';
    document.getElementById('month').style.display = (filter === 'month') ? 'inline-block' : 'none';
    document.getElementById('monthYear').style.display = (filter === 'month') ? 'inline-block' : 'none';
    document.getElementById('year').style.display = (filter === 'year') ? 'inline-block' : 'none';
});
</script>
