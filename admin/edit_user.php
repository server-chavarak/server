<?php
session_start();
require_once '../db.php';

$error_message = "";

if (!isset($_GET['username'])) {
    die("ไม่พบผู้ใช้ที่ระบุ");
}

$username = $_GET['username'];

// ดึงข้อมูลผู้ใช้จากฐานข้อมูล
$sql = "SELECT * FROM users WHERE Username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("ไม่พบผู้ใช้ที่ระบุ");
}

$approve = $user['Approve']; // เก็บค่าการอนุมัติเดิม

// ดึงข้อมูลตำแหน่งงานจากฐานข้อมูล
$sql = "SELECT * FROM role";
$role_result = $conn->query($sql);

// ดึงข้อมูลแผนกจากฐานข้อมูล
$sql = "SELECT * FROM section";
$section_result = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $r_id = $_POST['r_id']; 
    $s_id = ($r_id == '2') ? $_POST['s_id'] : null;
    $tell = $_POST['tell'];
    $email = $_POST['email'];
    $new_username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    // Validate password length and match
    if (!empty($password) && (strlen($password) < 8)) {
        $error_message = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัว";
    } elseif ($password !== $confirm_password) {
        $error_message = "รหัสผ่านไม่ตรงกัน กรุณาลองใหม่อีกครั้ง";
    } else {
        // ตรวจสอบว่า Username ใหม่มีอยู่ในฐานข้อมูลหรือไม่
        $sql = "SELECT Username FROM users WHERE Username = ? AND Username != ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("SQL statement preparation failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $new_username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error_message = "Username ถูกใช้งานในระบบแล้ว กรุณาเปลี่ยนใหม่";
        } else {
            // อัพเดทข้อมูลผู้ใช้ในฐานข้อมูล
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET Firstname = ?, Lastname = ?, R_ID = ?, S_ID = ?, Tell = ?, Email = ?, Username = ?, Password = ?, Approve = ? WHERE Username = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiissssis", $firstname, $lastname, $r_id, $s_id, $tell, $email, $new_username, $password_hash, $approve, $username);
            } else {
                $sql = "UPDATE users SET Firstname = ?, Lastname = ?, R_ID = ?, S_ID = ?, Tell = ?, Email = ?, Username = ?, Approve = ? WHERE Username = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiisssis", $firstname, $lastname, $r_id, $s_id, $tell, $email, $new_username, $approve, $username);
            }
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $_SESSION['alertMessage'] = "อัพเดทข้อมูลสำเร็จ";
            }

            $stmt->close();
            $conn->close();
        }
    }
}

include 'admin_index.html';
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../css/user.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <title>Edit User</title>
</head>
<body>
<a href="../admin/user_account.php" class="back-link">ย้อนกลับ</a>
    <div class="add">
        <form action="" method="post">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['Username']); ?>">
            <h2>แก้ไขบัญชีผู้ใช้งาน</h2>
            
            <div class="form-group">
                <label for="firstname">ชื่อ</label>
                <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['Firstname']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="lastname">นามสกุล</label>
                <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['Lastname']); ?>" required>
            </div>

            <div class="form-group">
                <label for="r_id">ตำแหน่งงาน</label>
                <select id="r_id" name="r_id" required>
                    <option value="">--เลือกตำแหน่ง--</option>
                    <?php while ($row = $role_result->fetch_assoc()): ?>
                        <option value="<?php echo $row['R_ID']; ?>" <?php if ($user['R_ID'] == $row['R_ID']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($row['R_Name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group" id="section-group" style="display: <?php echo ($user['R_ID'] == 2) ? 'block' : 'none'; ?>">
                <div class="form-group">
                    <label for="s_id">แผนก</label>
                    <select id="s_id" name="s_id">
                        <option value="">--เลือกแผนก--</option>
                        <?php while ($row = $section_result->fetch_assoc()): ?>
                            <option value="<?php echo $row['S_ID']; ?>" <?php if ($user['S_ID'] == $row['S_ID']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($row['S_Name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="tell">เบอร์โทร</label>
                <input type="tel" id="tell" name="tell" value="<?php echo htmlspecialchars($user['Tell']); ?>" pattern="[0-9]{10}" required title="เบอร์โทรศัพท์ 10 หลัก">
            </div>

            <div class="form-group">
                <label for="email">อีเมล</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['Email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="username">ชื่อผู้ใช้งาน</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['Username']); ?>">
            </div>

            <div class="form-group">
                <label for="password">รหัสผ่านใหม่</label>
                <input type="password" id="password" name="password">
                <span class="toggle-password" onclick="togglePassword('password')">
                    <i class="fas fa-eye-slash" id="password-eye-slash"></i>
                </span>
            </div>

            <div class="form-group">
                <label for="confirm-password">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" id="confirm-password" name="confirm-password">
                <span class="toggle-password" onclick="togglePassword('confirm-password')">
                    <i class="fas fa-eye-slash" id="confirm-password-eye-slash"></i>
                </span>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="footer">
                <button type="submit" class="approve">บันทึก</button>
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

        // แสดงแผนกเมื่อเลือก Role ที่เป็นหัวหน้าแผนก (R_ID = 2)
        document.getElementById('r_id').addEventListener('change', function() {
            var sectionGroup = document.getElementById('section-group');
            if (this.value == '2') {
                sectionGroup.style.display = 'block';
                document.getElementById('s_id').required = true;
            } else {
                sectionGroup.style.display = 'none';
                document.getElementById('s_id').required = false;
            }
        });

        <?php
        if (isset($_SESSION['alertMessage'])) {
            echo "Swal.fire({
                icon: 'success',
                title: 'แก้ไขสำเร็จ',
                text: 'แก้ไขบัญชีผู้ใช้งานเรียบร้อยแล้ว',
                showConfirmButton: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'user_account.php';
                }
            });";
            unset($_SESSION['alertMessage']);
        }
        ?>

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
