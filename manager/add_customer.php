<?php
require_once '../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $Cus_Fname = $_POST['Cus_Fname'];
    $Cus_Lname = $_POST['Cus_Lname'];
    $Project_Name = $_POST['Project_Name'];
    $Cus_Address = $_POST['Cus_Address'];
    $Email = $_POST['Email'];
    $Tell = $_POST['Tell'];


    

    $sql = "INSERT INTO customer (Cus_Fname, Cus_Lname, Project_Name , Cus_Address, Email, Tell) VALUES (?, ?, ? ,?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $Cus_Fname, $Cus_Lname,$Project_Name, $Cus_Address, $Email, $Tell);

    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['alertMessage'] = "เพิ่มข้อมูลลูกค้าเรียบร้อยแล้ว";
    } else {
        $_SESSION['alertMessage'] = "ไม่สามารถเพิ่มข้อมูลลูกค้าได้";
    }

    $stmt->close();
    $conn->close();
    
    // Redirect to the same page to process the alert message
    header('Location: add_customer.php');
    exit();
}
include 'manager_index.html';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/customer.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Add Customer</title>
</head>
<body>
<a href="../manager/customer.php" class="back-link">ย้อนกลับ</a>
    <form method="POST" action="add_customer.php" id = "addcustomer">
    <h2>เพิ่มข้อมูลลูกค้า</h2>

    <div class="form-group">
            <label for="Cus_Fname">ชื่อ:</label>
            <input type="text" id="Cus_Fname" name="Cus_Fname" required>
        </div>

        <div class="form-group">
            <label for="Cus_Lname">นามสกุล:</label>
            <input type="text" id="Cus_Lname" name="Cus_Lname" >
        </div>

        <div class="form-group">
            <label for="Project_Name">ชื่อโครงการ:</label>
            <input type="text" id="Project_Name" name="Project_Name" required>
        </div>

        <div class="form-group">
            <label for="Cus_Address">ที่อยู่:</label>
            <input type="text" id="Cus_Address" name="Cus_Address" >
        </div>

        <div class="form-group">
            <label for="Tell">เบอร์โทร:</label>
            <input type="text" id="Tell" name="Tell" >
        </div>

        <div class="form-group">
            <label for="Email">อีเมล:</label>
            <input type="email" id="Email" name="Email" >
        </div>

        <div class="footer">
            <button type="submit" class="approve">เพิ่ม</button>
            <button type="reset" class="delete">ยกเลิก</button>
        </div>
    </form>

    <script>
        <?php
        if (isset($_SESSION['alertMessage'])) {
            echo "Swal.fire({
                icon: 'success',
                title: '" . $_SESSION['alertMessage'] . "',
                confirmButtonText: 'ตกลง'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'customer.php';
                }
            });";
            unset($_SESSION['alertMessage']);
        }
        ?>
    </script>
</body>
</html>
