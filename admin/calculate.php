<?php
session_start();
require_once '../db.php';

include 'admin_index.html';

$sql = "SELECT 
        p.Product_ID,
        CONCAT(pd.P_Name, ' - ', td.TD_Name, ' Ø' , t.pipe_size, ' ', pe.PE_Name, ' ',IF(t.degree IS NOT NULL AND t.degree != '', CONCAT(' - ', t.degree, ' องศา'), '')) AS product,
        c.Working_Hours,
        IFNULL(c.cycletime, 0) AS cycletime, 
        IFNULL(c.losstime, 0) AS losstime,
        FLOOR((c.Working_Hours * 60 - IFNULL(c.losstime, 0)) / IFNULL(c.cycletime, 1)) AS Amount
    FROM calculate c
    JOIN product p ON c.Product_ID = p.Product_ID
    JOIN product_detail pd ON p.P_ID = pd.P_ID  -- ดึง P_Name จาก product_detail
    JOIN type t ON p.t_id = t.t_id
    JOIN type_detail td ON t.TD_ID = td.TD_ID  -- ดึง TD_Name จาก type_detail
    JOIN pipeend_detail pe ON t.PE_ID = pe.PE_ID  -- ดึง PE_Name จาก pipeend_detail
";

$result = $conn->query($sql);

if (!$result) {
    die("Error in query: " . $conn->error);
}

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
            font-size: 20px;
            font-family: K2D;
            font-weight: bold;
            margin-left: 46%;
            margin-top: 3%;
        }
        .table-container {
            max-height: 470px;
            overflow-y: auto;
            margin: 20px;
            display: flex;
            flex-direction: column;
            align-content: center;
            align-items: center;
        }

        table {
            width: 90%;
            margin: 0 auto;
            border-collapse: collapse;
            box-sizing: border-box;
        }

        thead th {
            position: -webkit-sticky; 
            position: sticky;
            top: 0; 
            background-color: #142338;
            color: white; 
            z-index: 0;
            border-bottom: 2px solid #ddd; 
            text-align: center;
        }


        th, td {
            border: 1px solid #bdbdbd; 
            padding: 8px; 
            text-align: left;
            background-color: #f8f8f8;
            font-family: Maitree;
        }

        th {
            color: white;
            z-index: 500
        }
        td {
            text-align: left;
        }
        td:first-child {
            width: 32%;
            
            
        }
        td:nth-child(2), td:nth-child(3) {
            width: 10%;
            text-align: center;
        }
        td:nth-child(4){
            width: 10%;
            text-align: center;
        
        }
        td:nth-child(5) {
            width: 13%;
            text-align: center;
            
        }
        td:nth-child(6) {
            width: 8%;
            text-align: center;
        }


        .button-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 0 130px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .button:hover {
            background-color: #45a049;
        }

        .approve-button i {
            font-size: 18px;
            color: #1a49bf;
            cursor: pointer;
            margin-left: 5px;
        }

      
    </style>
</head>
<body>
    <div class="header">คำนวณยอดการผลิต</div>
    <div class="button-container">
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ผลิตภัณฑ์</th>
                    <th>เวลาทำงาน/ชม.</th>
                    <th>เวลาผลิต 1 ท่อน/นาที</th>
                    <th>เวลาที่สูญเสีย/นาที</th>
                    <th>จำนวนที่ผลิตได้(ชิ้น)/วัน</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    if ($result->num_rows > 0) {
                        // Output data for each row
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row["product"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["Working_Hours"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["cycletime"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["losstime"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["Amount"]) . "</td>";
                            echo "<td class='action-links'>
                                    <a href='edit_calculate.php?Product_ID=" . urlencode($row["Product_ID"]) . "' class='approve-button'><i class='fa-solid fa-pen'></i></a>

                                </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6'>ไม่มีข้อมูล</td></tr>";
                    }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>

























