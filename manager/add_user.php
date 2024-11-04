<?php
session_start();
require_once '../db.php';

// Initialize variables
$firstname = $lastname = $r_id = $s_id = $tell = $email = $username = $error_message = "";

// Fetch roles from the database
$role_query = "SELECT R_ID, R_Name FROM role";
$role_result = $conn->query($role_query);

if ($role_result === false) {
    die("Error fetching roles: " . $conn->error);
}

// Fetch sections from the database
$section_query = "SELECT S_ID, S_Name FROM section";
$section_result = $conn->query($section_query);

if ($section_result === false) {
    die("Error fetching sections: " . $conn->error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = htmlspecialchars($_POST['firstname']);
    $lastname = htmlspecialchars($_POST['lastname']);
    $r_id = htmlspecialchars($_POST['r_id']);
    $s_id = ($r_id === '2') ? htmlspecialchars($_POST['s_id']) : null;
    $tell = htmlspecialchars($_POST['tell']);
    $email = htmlspecialchars($_POST['email']);
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);
    $confirm_password = htmlspecialchars($_POST['confirm-password']);

    // Validate password
    if (strlen($password) < 8) {
        $error_message = "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร";
    } elseif ($password !== $confirm_password) {
        $error_message = "รหัสผ่านไม่ตรงกัน กรุณาลองใหม่อีกครั้ง";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        list($username_part, $domain) = explode('@', $email);
        if (!checkdnsrr($domain, 'MX')) {
            $error_message = "ไม่พบอีเมลที่ระบุในระบบ";
        }
    }

    // Validate phone number format
    if (!preg_match("/^[0-9]{10}$/", $tell)) {
        $error_message = "เบอร์โทรศัพท์ต้องประกอบด้วยตัวเลข 10 หลักเท่านั้น";
    }

    // Check for errors and insert data if none
    if (empty($error_message)) {
        $stmt = $conn->prepare("SELECT * FROM Users WHERE Username = ?");
        if ($stmt === false) {
            die("การเตรียมคำสั่ง SQL ล้มเหลว: " . $conn->error);
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error_message = "Username ถูกใช้งานแล้ว กรุณาเปลี่ยนใหม่";
        } else {
            $approve = 1; // Default approval

            $sql = "INSERT INTO Users (Firstname, Lastname, R_ID, S_ID, Tell, Email, Username, Password, Approve) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql);

            if ($stmt_insert === false) {
                die("การเตรียมคำสั่ง SQL ล้มเหลว: " . $conn->error);
            }

            $stmt_insert->bind_param("ssisssssi", $firstname, $lastname, $r_id, $s_id, $tell, $email, $username, $password_hash, $approve);
            $stmt_insert->execute();

            if ($stmt_insert->affected_rows > 0) {
                $_SESSION['alertMessage'] = "เพิ่มบัญชีผู้ใช้งานสำเร็จแล้ว";
                header('Location: add_user.php'); // Redirect to the same page to show the success message
                exit;
            } else {
                $error_message = "มีข้อผิดพลาดในการเพิ่มบัญชีผู้ใช้งาน";
            }

            $stmt_insert->close();
        }
        $stmt->close();
    }

    $conn->close();
}

include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chavarak</title>
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>
  
<a href="../manager/user_account.php" class="back-link">ย้อนกลับ</a>
    <div class="add">
        <form action="add_user.php" method="POST" id="signupForm" class="signup-form">
        <h2>เพิ่มบัญชีผู้ใช้งาน</h2>

                <div class="form-group">
                <label for="firstname"  class="asterisk">ชื่อ</label>
                    <input type="text" id="firstname" name="firstname" value="<?php echo $firstname; ?>" title="กรอกชื่อ" required>
                </div>

                <div class="form-group">
                <label for="firstname" class="asterisk">นามสกุล</label>
                    <input type="text" id="lastname" name="lastname" value="<?php echo $lastname; ?>" title="กรอกนามสกุล" required>
                </div>
           
            
                <div class="form-group">
                    <label for="r_id" class="asterisk">ตำแหน่งงาน</label>
                    <select id="r_id" name="r_id" title="กรอกตำแหน่ง" required>
                        <option value="">--เลือกตำแหน่ง--</option>
                        <?php
                        if ($role_result->num_rows > 0) {
                            while($row = $role_result->fetch_assoc()) {
                                echo '<option value="' . $row["R_ID"] . '" ' . ($r_id === $row["R_ID"] ? 'selected' : '') . '>' . $row["R_Name"] . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" id="section-group" style="display: <?php echo (isset($r_id) && $r_id === '2') ? 'block' : 'none'; ?>;">
                    <div class="form-group">
                    <label for="s_id" class="asterisk">แผนก</label>
                    <select id="s_id" name="s_id" title="กรอกแผนก">
                        <option value="">--เลือกแผนก--</option>
                        <?php
                        if ($section_result->num_rows > 0) {
                            while($row = $section_result->fetch_assoc()) {
                                echo '<option value="' . $row["S_ID"] . '" ' . (isset($s_id) && $s_id === $row["S_ID"] ? 'selected' : '') . '>' . $row["S_Name"] . '</option>';
                            }
                        }
                        ?>
                    </select>
                    </div>
                </div>

           
            <div class="form-group">
            <label for="tell" class="asterisk">เบอร์โทร</label>
                <input type="tel" id="tell" name="tell" value="<?php echo $tell; ?>" title="กรอกเบอร์โทรศัพท์ 10 ตัว" pattern="[0-9]{10}" required>
                <span id="tell-error" class="error"></span>
            </div>
           
            
                <div class="form-group">
                <label for="email" class="asterisk">อีเมล</label>
                    <input type="email" id="email" name="email" value="<?php echo $email; ?>" title="กรอกอีเมล" required>
                </div>
           
           
                <div class="form-group">
                <label for="username" class="asterisk">ชื่อผู้ใช้งาน</label>
                    <input type="text" id="username" name="username" value="<?php echo $username; ?>" title="กรอกชื่อผู้ใช้งาน" required>
                </div>
           
            
                <div class="form-group">
                <label for="password" class="asterisk">รหัสผ่าน</label>
                    <input type="password" id="password" name="password"  value="<?php echo $password; ?>"  title="รหัสผ่านอย่างน้อย 8 ตัว" required>
                    <span id="password-error" class="error"></span>
                    <span class="toggle-password" onclick="togglePassword('password')">
                        <i class="fas fa-eye-slash" id="password-eye-slash"></i>
                    </span>
                    
                </div>

                <div class="form-group">
                <label for="confirm-password" class="asterisk">ยืนยันรหัสผ่าน</label>
                    <input type="password" id="confirm-password" name="confirm-password"   title="กรอกยืนยันรหัสผ่านอีกครั้ง" required>
                    <span id="confirm-password-error" class="error"></span>
                    <span class="toggle-password" onclick="togglePassword('confirm-password')">
                        <i class="fas fa-eye-slash" id="confirm-password-eye-slash"></i>
                    </span>
                   
                </div>
           
                <?php if (!empty($error_message)): ?>
                    <div class="error"><?php echo $error_message; ?></div>
                <?php endif; ?>

            <div class="footer">
            <button type="submit" class="approve">เพิ่ม</button>
            <button type="reset" class="delete">ยกเลิก</button>
            </div>
        </form>
    </div>
   
    <script>
        function togglePassword(inputId) {
            var input = document.getElementById(inputId);
            var eyeIcon = document.getElementById(inputId + '-eye-slash');
            
            if (input.type === "password") {
                input.type = "text";
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                input.type = "password";
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        }

        document.getElementById('r_id').addEventListener('change', function() {
            var sectionGroup = document.getElementById('section-group');
            if (this.value === '2') {
                sectionGroup.style.display = 'block';
                document.getElementById('s_id').required = true;
            } else {
                sectionGroup.style.display = 'none';
                document.getElementById('s_id').required = false;
            }
        });


       
        document.addEventListener("DOMContentLoaded", function() {
            const activeMenu = localStorage.getItem("activeMenu");
            if (activeMenu) {
                document.getElementById(activeMenu).classList.add("active");
            }
        });

        <?php if (isset($_SESSION['alertMessage'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ',
            text: '<?php echo $_SESSION['alertMessage']; ?>',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'user_account.php';
                <?php unset($_SESSION['alertMessage']); ?>
            }
        });
        <?php endif; ?>

        // Clear error messages on input change
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                document.querySelectorAll('.error').forEach(error => {
                    error.textContent = '';
                });
            });
        });

     
    </script>
</body>
</html>
