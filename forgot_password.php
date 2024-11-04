<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        // Separate email parts
        list($username, $domain) = explode('@', $email);

        // Check DNS records
        if (checkdnsrr($domain, 'MX')) {
            echo "อีเมล $email มีอยู่จริง";
            // ดำเนินการต่อไป เช่น ส่งอีเมลรีเซ็ตรหัสผ่าน
        } else {
            $error = "ไม่พบอีเมลที่ระบุในระบบ";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Forgot Password</h2>
        <p>Please enter your email address to receive instructions to reset your password.</p>
        <form action="reset_password.php" method="POST">
            <div class="input_box">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="input_box">
                <button type="submit">Submit</button>
            </div>
        </form>
    </div>
</body>
</html>
