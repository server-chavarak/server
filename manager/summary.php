<?php
require_once '../db.php';

// กำหนด Time Zone ให้เป็นเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// กำหนดค่า locale เป็นภาษาไทย
setlocale(LC_TIME, 'th_TH.UTF-8');


function convertDateToThai($date, $filterType) {
    $thai_months_short = array(
        "01" => "ม.ค.", "02" => "ก.พ.", "03" => "มี.ค.", "04" => "เม.ย.", 
        "05" => "พ.ค.", "06" => "มิ.ย.", "07" => "ก.ค.", "08" => "ส.ค.", 
        "09" => "ก.ย.", "10" => "ต.ค.", "11" => "พ.ย.", "12" => "ธ.ค."
    );
    
    $month = date('m', strtotime($date));
    $year = date('Y', strtotime($date)) + 543; // แปลงเป็นปีพุทธศักราช
    $day = date('d', strtotime($date));

    // สำหรับการกรองแบบรายปี แสดงเฉพาะชื่อเดือนเต็ม
    if ($filterType === 'year') {
        return $thai_months_short[$month]; // แสดงเฉพาะชื่อเดือน
    } else if ($filterType === 'month') {
        // กรองแบบรายเดือนให้แสดงชื่อเดือนแบบย่อ
        return $day . ' ' . $thai_months_short[$month] . ' ' . $year; // แสดงวันที่และชื่อเดือนแบบย่อ
    } else {
        return $day . ' ' . $thai_months_short[$month] . ' ' . $year; // แสดงวันที่และชื่อเดือนแบบเต็ม
    }
}



// ฟังก์ชันสำหรับการกรองข้อมูล
function filterData($filterType, $date) {
    global $conn;

    // เตรียมข้อมูลสำหรับแผนกทั้ง 7
    $departments = [
        1 => ['name' => 'Spiral', 'data' => []],
        2 => ['name' => 'Fitting', 'data' => []],
        3 => ['name' => 'Hydrotest', 'data' => []],
        4 => ['name' => 'Blast', 'data' => []],
        5 => ['name' => 'PU', 'data' => []],
        6 => ['name' => 'Inner Paint', 'data' => []],
        7 => ['name' => 'Outer Paint', 'data' => []],
    ];

    // กำหนด SQL Query ตามประเภทการกรอง
    switch ($filterType) {
        case 'day':
            $sql = "SELECT S_ID, SUM(currentPlan) AS currentPlan, SUM(FinishGood) AS FinishGood, DATE(Date_Time) AS Date_Time 
                    FROM actual_production 
                    WHERE DATE(Date_Time) = '$date' 
                    GROUP BY S_ID, DATE(Date_Time) 
                    ORDER BY S_ID, Date_Time";
            break;
        case 'month':
            $sql = "SELECT S_ID, SUM(currentPlan) AS currentPlan, SUM(FinishGood) AS FinishGood, DATE(Date_Time) AS Date_Time 
                    FROM actual_production 
                    WHERE YEAR(Date_Time) = YEAR('$date') 
                    AND MONTH(Date_Time) = MONTH('$date') 
                    GROUP BY S_ID, DATE(Date_Time)
                    ORDER BY S_ID, Date_Time";
            break;
        case 'year':
            $sql = "SELECT S_ID, SUM(currentPlan) AS currentPlan, SUM(FinishGood) AS FinishGood, 
                           DATE_FORMAT(Date_Time, '%Y-%m') AS Month 
                    FROM actual_production 
                    WHERE YEAR(Date_Time) = YEAR('$date') 
                    GROUP BY S_ID, Month 
                    ORDER BY S_ID, Month";
            break;
        default:
            $sql = "SELECT S_ID, SUM(currentPlan) AS currentPlan, SUM(FinishGood) AS FinishGood, DATE(Date_Time) AS Date_Time 
                    FROM actual_production 
                    WHERE DATE(Date_Time) = CURDATE()
                    GROUP BY S_ID, DATE(Date_Time) 
                    ORDER BY S_ID, Date_Time";
            break;
    }

    $result = $conn->query($sql);

    // แยกข้อมูลตามแผนก
    while ($row = $result->fetch_assoc()) {
        $departments[$row['S_ID']]['data'][] = [
            'date' => ($filterType === 'year') ? $row['Month'] : $row['Date_Time'],
            'plan' => $row['currentPlan'],
            'finish' => $row['FinishGood'],
        ];
    }

    return $departments;
}

// รับค่าการกรองจากผู้ใช้
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'day'; // กรองรายวันเป็นค่าเริ่มต้น
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); // วันที่ปัจจุบันเป็นค่าเริ่มต้น

// เรียกฟังก์ชันเพื่อกรองข้อมูล
$departments = filterData($filterType, $date);

include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Production Summary</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=K2D:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'K2D', sans-serif; /* ใช้ฟอนต์ K2D */
        }
        h1 {
            text-align: center; 
            font-size: 25px;
            margin-top: 1%;
            font-family: 'K2D', sans-serif;
        }

        .chart-container {
            display: flex;
            flex-wrap: wrap;
            max-height: 80vh;
            overflow-y: auto;
            padding: 40px;
            border: 1px solid #ddd;
            gap: 50px;
        }

        .chart-box {
            width: 48%;
            margin-bottom: 5px;
            box-sizing: border-box;
        }

        .filter-container {
            display: flex;
            justify-content: flex-end; /* จัดให้อยู่ขวาสุดของจอ */
            margin-right: 50px;
            align-items: center;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .filter-container label, 
        .filter-container select, 
        .filter-container input {
            margin-left: 10px;
            font-family: 'K2D', sans-serif; /* ใช้ฟอนต์ K2D */
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
<h1>Production Summary</h1>

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
    <?php foreach ($departments as $id => $department): ?>
        <div class="chart-box">
            <h2><?php echo $department['name']; ?></h2>
            <canvas id="chart-<?php echo $id; ?>" width="450" height="250"></canvas>
        </div>
        <script>
            const ctx<?php echo $id; ?> = document.getElementById('chart-<?php echo $id; ?>').getContext('2d');

            <?php if ($filterType === 'day') { ?>
            // กรณีรายวัน แสดงแท่งคู่สำหรับแผนผลิตและผลิตจริง พร้อมวันที่ใต้กราฟ
            new Chart(ctx<?php echo $id; ?>, {
                type: 'bar',
                data: {
                    labels: ['แผนผลิต', 'ผลิตจริง'], // ชื่อของแท่งกราฟ
                    datasets: [{
                        label: '<?php echo $department['name']; ?>',
                        data: [<?php foreach ($department['data'] as $data) { echo $data['plan'] . "," . $data['finish'] . ","; } ?>],
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
            new Chart(ctx<?php echo $id; ?>, {
                type: 'bar',
                data: {
                    labels: [<?php 
                        foreach ($department['data'] as $data) { 
                            echo "'" . convertDateToThai($data['date'], $filterType) . "',"; 
                        } 
                    ?>],
                    datasets: [
                        {
                            type: 'line',
                            label: 'แผนผลิต',
                            data: [<?php foreach ($department['data'] as $data) { echo $data['plan'] . ","; } ?>],
                            borderColor: 'rgba(255, 165, 0, 1)',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            pointBackgroundColor: 'rgba(255, 165, 0, 1)',
                            fill: false
                        },
                        {
                            type: 'bar',
                            label: 'ผลิตจริง',
                            data: [<?php foreach ($department['data'] as $data) { echo $data['finish'] . ","; } ?>],
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

    <?php endforeach; ?>
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

</body>
</html>
