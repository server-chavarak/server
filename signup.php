<?php
session_start();
require_once 'db.php';

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
    // Read POST data
    $firstname = htmlspecialchars($_POST['firstname']);
    $lastname = htmlspecialchars($_POST['lastname']);
    $r_id = htmlspecialchars($_POST['r_id']);
    $s_id = ($r_id === '2') ? htmlspecialchars($_POST['s_id']) : null;
    $tell = htmlspecialchars($_POST['tell']);
    $email = htmlspecialchars($_POST['email']);
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);
    $confirm_password = htmlspecialchars($_POST['confirm-password']);

    // ตรวจสอบรหัสผ่าน
    if (strlen($password) < 8) {
        $error_message = "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร";
    } elseif ($password !== $confirm_password) {
        $error_message = "รหัสผ่านไม่ตรงกัน กรุณาลองใหม่อีกครั้ง";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
    }

    // ตรวจสอบรูปแบบอีเมล
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        list($username_part, $domain) = explode('@', $email);
        if (!checkdnsrr($domain, 'MX')) {
            $error_message = "ไม่พบอีเมลที่ระบุในระบบ";
        }
    }

    // ตรวจสอบเบอร์โทรศัพท์
    if (!preg_match("/^[0-9]{10}$/", $tell)) {
        $error_message = "เบอร์โทรศัพท์ต้องประกอบด้วยตัวเลข 10 หลักเท่านั้น";
    }

    // หากไม่มีข้อผิดพลาด
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
            $approve = 0; 

            $sql = "INSERT INTO Users (Firstname, Lastname, R_ID, S_ID, Tell, Email, Username, Password, Approve) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql);
            
            if ($stmt_insert === false) {
                die("การเตรียมคำสั่ง SQL ล้มเหลว: " . $conn->error);
            }
            
            // ตรวจสอบและกำหนดค่า $s_id ที่จะถูกใช้ในการ bind_param
            $s_id_bind = $s_id ?: null;
            
            // ตรวจสอบการผูกค่าที่ตรงตามข้อมูล
            $stmt_insert->bind_param("ssisssssi", $firstname, $lastname, $r_id, $s_id_bind, $tell, $email, $username, $password_hash, $approve);
            
            // รันคำสั่ง SQL และตรวจสอบข้อผิดพลาด
            $stmt_insert->execute();
            
            if ($stmt_insert->affected_rows > 0) {
                $_SESSION['alertMessage'] = "สมัครสมาชิกเรียบร้อยแล้ว";
            } else {
                $error_message = "มีข้อผิดพลาดในการสมัครสมาชิก: " . $stmt_insert->error;
            }
            
            $stmt_insert->close();
            
        }
        $stmt->close(); 
    }
    $conn->close(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chavarak</title>
    <link rel="stylesheet" href="css/signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>
    <header>
        <div>
            <p>
                <a href="mailto:sales.chavarak@gmail.com"><i class="fas fa-envelope"></i> sales.chavarak@gmail.com</a> 
                <span> | </span>
                <a href="tel:089-8110421"><i class="fas fa-phone"></i> 089-8110421</a>, 
                <a href="tel:097-1814614"> 097-1814614</a>, 
                <a href="tel:062-5249961"> 062-5249961</a>
            </p>
        </div>
        <i class='bx bx-arrow-back back-icon' id="back-icon"></i>
    </header>

    <div class="center">
        สมัครสมาชิก
    </div>

    <div class="main_div">
        <div class="title">
            <img src="images/logo-chava.png" alt="Login Image">
        </div>
    </div>

    <div class="form-container">
        <form action="signup.php" method="POST" id="signupForm" class="signup-form">
            <div class="form-row">
                <div class="form-group">
                    <input type="text" id="firstname" name="firstname"  value="<?php echo $firstname; ?>"  placeholder="ชื่อ" required>
                </div>
                <div class="form-group">
                    <input type="text" id="lastname" name="lastname"  value="<?php echo $lastname; ?>" placeholder="นามสกุล" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
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
            </div>

            <div class="form-row">
                <div class="form-group" id="section-group" style="display: <?php echo (isset($r_id) && $r_id === '2') ? 'block' : 'none'; ?>;">
                    <div class="form-group">
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
            </div>

            <div class="form-row">
            <div class="form-group">
                <input type="tel" id="tell" name="tell"  value="<?php echo $tell; ?>" placeholder="เบอร์โทรศัพท์" pattern="[0-9]{10}" required>
                <span id="tell-error" class="error"></span>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <input type="email" id="email" name="email"  value="<?php echo $email; ?>" placeholder="อีเมล" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <input type="text" id="username" name="username"  value="<?php echo $username; ?>" placeholder="ชื่อผู้ใช้งาน" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <input type="password" id="password" name="password"  value="<?php echo $password; ?>" placeholder="รหัสผ่าน" required>
                    <span id="password-error" class="error"></span>
                    <span class="toggle-password" onclick="togglePassword('password')">
                        <i class="fas fa-eye-slash" id="password-eye-slash"></i>
                    </span>
                </div>
                <div class="form-group">
                    <input type="password" id="confirm-password" name="confirm-password"   placeholder="ยืนยันรหัสผ่าน" required>
                    <span id="confirm-password-error" class="error"></span>
                    <span class="toggle-password" onclick="togglePassword('confirm-password')">
                        <i class="fas fa-eye-slash" id="confirm-password-eye-slash"></i>
                    </span>
                </div>
            </div>
            <?php if (!empty($error_message)): ?>
                <div class="error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit">สมัครสมาชิก</button>
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

        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('r_id');
            const sectionGroup = document.getElementById('section-group');

            function updateSectionVisibility() {
                if (roleSelect.value === '2') {
                    sectionGroup.style.display = 'block';
                } else {
                    sectionGroup.style.display = 'none';
                }
            }

            roleSelect.addEventListener('change', updateSectionVisibility);

            // Initialize the section visibility based on the current role
            updateSectionVisibility();
        });



        document.addEventListener('DOMContentLoaded', function() {
            const tellInput = document.getElementById('tell');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm-password');
            const tellError = document.getElementById('tell-error');
            const passwordError = document.getElementById('password-error');
            const confirmPasswordError = document.getElementById('confirm-password-error');

            // ตรวจสอบเบอร์โทร
            tellInput.addEventListener('input', function() {
                if (tellInput.value.length !== 10) {
                    tellError.textContent = 'กรุณากรอกเบอร์โทร 10 ตัว';
                    tellError.style.display = 'block';
                } else {
                    tellError.style.display = 'none';
                }
            });

            // ตรวจสอบความยาวของรหัสผ่าน
            passwordInput.addEventListener('input', function() {
                if (passwordInput.value.length < 8) {
                    passwordError.textContent = 'กรุณาใส่รหัสผ่านอย่างน้อย 8 ตัว';
                    passwordError.style.display = 'block';
                } else {
                    passwordError.style.display = 'none';
                }
            });

            // ตรวจสอบความตรงกันของรหัสผ่านและการยืนยันรหัสผ่าน
            confirmPasswordInput.addEventListener('input', function() {
                if (confirmPasswordInput.value !== passwordInput.value) {
                    confirmPasswordError.textContent = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน กรุณาลองใหม่อีกครั้ง';
                    confirmPasswordError.style.display = 'block';
                } else {
                    confirmPasswordError.style.display = 'none';
                }
            });

            // ฟังก์ชันตรวจสอบฟอร์มก่อนส่ง
            document.getElementById('signupForm').addEventListener('submit', function(event) {
                if (tellInput.value.length !== 10) {
                    event.preventDefault();
                    tellError.textContent = 'กรุณากรอกเบอร์โทร 10 ตัว';
                    tellError.style.display = 'block';
                }
                if (passwordInput.value.length < 8) {
                    event.preventDefault();
                    passwordError.textContent = 'กรุณาใส่รหัสผ่านอย่างน้อย 8 ตัว';
                    passwordError.style.display = 'block';
                }
                if (confirmPasswordInput.value !== passwordInput.value) {
                    event.preventDefault();
                    confirmPasswordError.textContent = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน กรุณาลองใหม่อีกครั้ง';
                    confirmPasswordError.style.display = 'block';
                }
            });
        });

        document.getElementById('back-icon').addEventListener('click', function() {
            window.location.href = 'login.php';
        });

        <?php if (isset($_SESSION['alertMessage'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ',
            text: '<?php echo $_SESSION['alertMessage']; ?>',
            confirmButtonText: 'ตกลง'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'login.php';
                <?php unset($_SESSION['alertMessage']); ?>
            }
        });
        <?php endif; ?>

        // Clear error messages on input change
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                const errorElements = document.querySelectorAll('.error');
                errorElements.forEach(error => {
                    error.textContent = '';
                });
            });
        });
        
      
    </script>
</body>
</html>
