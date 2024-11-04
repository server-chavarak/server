<?php
session_start();
require_once '../db.php';

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

$username = $_SESSION['username'] ?? '';

if (!$username) {
    die('User not logged in.');
}

$query = "
    SELECT 
        u.Firstname, 
        u.Lastname, 
        u.Tell, 
        u.Email, 
        u.ProfilePicture, 
        r.R_Name AS Role 
    FROM 
        users u
    JOIN 
        role r ON u.R_ID = r.R_ID
    WHERE 
        u.Username = '$username'
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die('Query failed: ' . mysqli_error($conn));
}

$userData = mysqli_fetch_assoc($result);

if (!$userData) {
    die('User not found.');
}

$profilePicture = $userData['ProfilePicture'] ? "../uploads/" . $userData['ProfilePicture'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';


include 'manager_index.html';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <title>ข้อมูลส่วนตัว</title>
    <style>
        .container {
            margin-top: 2%;
            max-width: 600px;
            margin: 0 auto;
          
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 20px;
            margin-top: 3%;
            text-decoration: underline;
            
        }
        .profile-section {
            text-align: center;
            margin-bottom: 10px;
            margin-top: 2%;
        }
        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #ddd;
            transition: border-color 0.3s ease;
            margin: 0 auto; /* ไอคอนตรงกลาง */
            display: block; /* เพื่อให้เป็นบล็อกและตรงกลาง */
        }
        .profile-image:hover {
            border-color: #4CAF50;
        }

        .edit-picture-link {
            color:#1888d3;
            cursor: pointer;
            font-size: 14px;
            margin-left: 48%;
           
 
        }

        .edit-picture-link:hover {
            color: #3e9b41;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="text"], 
        .form-group input[type="email"],
        .form-group input[type="file"] {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group input[type="file"] {
            padding: 5px;
        }
        .button-container {
            text-align: center;
        }
        .button {
            background-color: #4CAF50;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .button:hover {
            background-color: #45a049;
        }
        .delete-picture-icon {
            color: red;
            font-size: 18px;
            cursor: pointer;
            position: relative;
            top: -15px; 
            left: 28%; 
            margin-right: 50%; 
            display: inline-block; 
        }
        .delete-picture-icon:hover {
            color: #e60000;
        }

    </style>
</head>
<body>
        <div class="profile-section">
            <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" class="profile-image" onclick="document.getElementById('profilePicture').click()">
            <!-- <span class="delete-picture-icon" onclick="deleteProfilePicture()">&times;</span> -->
        </div>


    <div class="container">
    <h2>ข้อมูลส่วนตัว</h2>
        
        <form id="personalInfoForm" method="post" action="update_user.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="fullName">ชื่อ-นามสกุล</label>
                <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($userData['Firstname'] . ' ' . $userData['Lastname']); ?>" disabled>
            </div>
            <div class="form-group">
                <label for="position">ตำแหน่ง</label>
                <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($userData['Role']); ?>" disabled>
            </div>
            <div class="form-group">
                <label for="phone">เบอร์โทรศัพท์</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['Tell']); ?>" disabled>
            </div>
            <div class="form-group">
                <label for="email">อีเมล</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['Email']); ?>" disabled>
            </div>

            <div class="form-group">
                <input type="file" id="profilePicture" name="profilePicture" style="display: none;" onchange="previewAndSubmitImage(this)">
            </div>
        </form>
    </div>

</body>
</html>