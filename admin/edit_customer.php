<?php
require_once '../db.php';
session_start();

$customer = null;

// ตรวจสอบว่ามีการส่ง Cus_ID มาหรือไม่
if (isset($_GET['Cus_ID'])) {
    $Cus_ID = $_GET['Cus_ID'];
    $sql = "SELECT * FROM customer WHERE Cus_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $Cus_ID);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();
} 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['Cus_ID'])) {
        $Cus_ID = $_POST['Cus_ID'];
        $Cus_Fname = $_POST['Cus_Fname'];
        $Cus_Lname = $_POST['Cus_Lname'];
        $Project_Name = $_POST['Project_Name'];
        $Cus_Address = $_POST['Cus_Address'];
        $Email = $_POST['Email'];
        $Tell = $_POST['Tell'];

        // อัปเดตข้อมูลในฐานข้อมูล
        $sql = "UPDATE customer SET Cus_Fname = ?, Cus_Lname = ?, Project_Name = ? , Cus_Address = ?, Email = ?, Tell = ? WHERE Cus_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $Cus_Fname, $Cus_Lname,$Project_Name , $Cus_Address, $Email, $Tell, $Cus_ID);

        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "แก้ไขข้อมูลลูกค้าเรียบร้อยแล้ว";
            $_SESSION['alertType'] = "success";
        } else {
            $_SESSION['alertMessage'] = "ไม่สามารถแก้ไขข้อมูลลูกค้าได้";
            $_SESSION['alertType'] = "error";
        }

        $stmt->close();
        header("Location: edit_customer.php?Cus_ID=$Cus_ID");
        exit;
    }
}

include 'admin_index.html';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/customer.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Edit Customer</title>
</head>
<body>
<a href="../admin/customer.php" class="back-link">ย้อนกลับ</a>
    <form method="POST" action="edit_customer.php" id="addcustomer">
    
        <input type="hidden" name="Cus_ID" value="<?php echo htmlspecialchars($customer['Cus_ID']); ?>">
        <h2>แก้ไขข้อมูลลูกค้า</h2>

        <div class="form-group">
            <label for="Cus_Fname">ชื่อ:</label>
            <input type="text" id="Cus_Fname" name="Cus_Fname" value="<?php echo htmlspecialchars($customer['Cus_Fname']); ?>" required>
        </div>

        <div class="form-group">
            <label for="Cus_Lname">นามสกุล:</label>
            <input type="text" id="Cus_Lname" name="Cus_Lname" value="<?php echo htmlspecialchars($customer['Cus_Lname']); ?>" >
        </div>

        <div class="form-group">
            <label for="Project_Name">ชื่อโครงการ:</label>
            <input type="text" id="Project_Name" name="Project_Name" value="<?php echo htmlspecialchars($customer['Project_Name']); ?>" required>
        </div>

        <div class="form-group">
            <label for="Cus_Address">ที่อยู่:</label>
            <input type="text" id="Cus_Address" name="Cus_Address" value="<?php echo htmlspecialchars($customer['Cus_Address']); ?>" >
        </div>

        <div class="form-group">
            <label for="Tell">เบอร์โทร:</label>
            <input type="text" id="Tell" name="Tell" value="<?php echo htmlspecialchars($customer['Tell']); ?>"  title="เบอร์โทรศัพท์ 10 หลัก">
        </div>

        <div class="form-group">
            <label for="Email">อีเมล:</label>
            <input type="email" id="Email" name="Email" value="<?php echo htmlspecialchars($customer['Email']); ?>"  title="อีเมลใส่ '@'">
        </div>

        <div class="footer">
            <button type="submit" class="approve">บันทึก</button>
            <button type="reset" class="delete">ยกเลิก</button>
        </div>
        
    </form>

  
    <?php if (isset($_SESSION['alertMessage'])): ?>
<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: '<?php echo $_SESSION['alertMessage']; ?>',
            icon: '<?php echo $_SESSION['alertType']; ?>',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'customer.php';
            }
        });
    });
</script>
<?php unset($_SESSION['alertMessage']); unset($_SESSION['alertType']); endif; ?>
</body>
</html>
