<?php
session_start();
require_once 'db.php';

$error = '';
$alertMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $sql = "SELECT * FROM users WHERE Username = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            die("การเตรียมคำสั่ง SQL ล้มเหลว: " . $conn->error);
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close(); 

        if ($user) {
            if (password_verify($password, $user['Password'])) {
                
                if ($user['Approve'] == 0) {
                    $_SESSION['alertMessage'] = 'รอการอนุมัติจากผู้ดูแลระบบ';
                } else {
                    $username = $user['Username'];
                    $_SESSION['r_id'] = $user['R_ID']; // เปลี่ยนจาก role เป็น r_id
                    $_SESSION['s_id'] = $user['S_ID'];
                    $_SESSION['username'] = $username;

                    $r_id = $user['R_ID']; // เปลี่ยนจาก role เป็น r_id
                    $user_s_id = $user['S_ID'];

                    if ($r_id == '0' || $r_id == '1') {
                        // กรณี Admin หรือ Manager
                        switch ($r_id) { // เปลี่ยนจาก role เป็น r_id
                            case '0': 
                                header("Location: admin/home.php?username=" . urlencode($username));
                                exit();
                            case '1': 
                                header("Location: manager/home.php?username=" . urlencode($username));
                                exit();
                        }
                    } elseif ($r_id == '2') {
                        // กรณี Section Head
                        switch ($user_s_id) { 
                            case '1':
                                header("Location: spiral/home_spiral.php?username=" . urlencode($username));
                                exit();
                            case '2':
                                header("Location: fitting/home_fitting.php?username=" . urlencode($username));
                                exit();
                            case '3':
                                header("Location: hydrotest/home_hydrotest.php?username=" . urlencode($username));
                                exit();
                            case '4':
                                header("Location: blast/home_blast.php?username=" . urlencode($username));
                                exit();
                            case '5':
                                header("Location: pu/home_pu.php?username=" . urlencode($username));
                                exit();
                            case '6':
                                header("Location: inner_paint/home_inner_paint.php?username=" . urlencode($username));
                                exit();
                            case '7':
                                header("Location: outer_paint/home_outer_paint.php?username=" . urlencode($username));
                                exit();
                            default:
                                $alertMessage = "แผนกไม่ถูกต้อง";
                                break;
                        }
                    } else {
                        $alertMessage = "ระดับผู้ใช้ไม่ถูกต้อง";
                    }
                }
            } else {
                $alertMessage = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            }
        } else {
            $alertMessage = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $alertMessage = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chavarak</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
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
</header>

<div class="main_div">
    <div class="title">
        <img src="images/logo-chava.png" alt="Login Image">
    </div>
    <form action="login.php" method="POST" id="loginForm">
        <div class="input_box">
            <label for="username" class="icon"><i class="fas fa-user"></i></label>
            <input type="text" id="username" name="username" placeholder="ชื่อผู้ใช้งาน" required autocomplete="username">
        </div>
        <div class="input_box">
            <label for="password" class="icon"><i class="fas fa-lock"></i></label>
            <input type="password" id="password" name="password" placeholder="รหัสผ่าน" required autocomplete="current-password">
            <span class="toggle-password" onclick="togglePassword('password')">
              <i class="fas fa-eye-slash"></i>
            </span>
        </div>
        <div class="option_div">
            <div class="forget_div">
                <a href="forgot_password.php">Forgot password?</a>
            </div>
        </div>
        <div class="button_container">
            <div class="input_box button login_button">
                <button type="submit">เข้าสู่ระบบ</button>
            </div>
            <hr class="my-3"> <!--ขีดคั่น -->
            <div class="input_box button signup_button">
                <button id="signupButton" type="button">สมัครสมาชิก</button>
            </div>
        </div>
        <?php if ($alertMessage != '') { ?>
            <div id="alert-message" style="color: red; text-align: center;">
                <?php echo $alertMessage; ?>
            </div>
        <?php } ?>
    </form>
</div>
<script>
    function togglePassword(id) {
        const input = document.getElementById(id);
        const icon = input.nextElementSibling.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }

    document.getElementById('signupButton').addEventListener('click', function() {
        window.location.href = 'signup.php';
    });

    <?php if (isset($_SESSION['alertMessage'])) { ?>
        Swal.fire({
            icon: 'info',
            title: 'แจ้งเตือน',
            text: '<?php echo $_SESSION['alertMessage']; ?>',
            confirmButtonText: 'ตกลง'
        });
        <?php unset($_SESSION['alertMessage']); ?>
    <?php } ?>
</script>
</body>
</html>
