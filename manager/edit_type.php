<?php
session_start();
require_once '../db.php'; 

// Fetch pipe types from the database
$sql = "SELECT TD_ID, TD_Name FROM Type_Detail"; 
$pipeNameResult = $conn->query($sql);

// Fetch pipe ends from the database
$sql = "SELECT PE_ID, PE_Name FROM PipeEnd_Detail"; 
$pipeEndResult = $conn->query($sql);

// Check if we are editing an existing entry
if (isset($_GET['T_ID'])) {
    $t_id = $_GET['T_ID'];
 
    // Fetch existing data from the type table
    $sql = "SELECT * FROM type WHERE T_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $t_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingData = $result->fetch_assoc();
    $stmt->close();
    $pipeSizes = explode('*', $existingData['Pipe_Size'] . '**'); // Ensure that we always have three parts
    $pipeSize1 = $pipeSizes[0] ?? ''; // Main diameter
    $pipeSize2 = $pipeSizes[1] ?? ''; // Thickness
    $pipeSize3 = $pipeSizes[2] ?? ''; // Length
 
}
 
// If the form is submitted, process the update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['T_ID'])) {
    $t_id = $_POST['T_ID']; // Hidden field for the ID of the entry being edited
    $t_name_id = $_POST['TD_ID'];
    $pipeSize1 = $_POST['Pipe_Size1'] ?? '';
    $pipeSize2 = $_POST['Pipe_Size2'] ?? '';
    $pipeSize3 = $_POST['Pipe_Size3'] ?? '';
   
    // Constructing the pipe size string
    $result = $pipeSize1;
    if (!empty($pipeSize2)) {
        $result .= (!empty($result) ? '*' : '') . $pipeSize2;
    }
    if (!empty($pipeSize3)) {
        $result .= (!empty($result) ? '*' : '') . $pipeSize3;
    }
    $pipe_end = $_POST['Pipe_End'];
    $degree = $_POST['degree'];
 
    // Update the existing record
    $sql = "UPDATE type SET TD_ID = ?, Pipe_Size = ?, PE_ID = ?, degree = ? WHERE T_ID = ?";
    $stmt = $conn->prepare($sql);
 
    if ($stmt === false) {
        die('Error preparing SQL statement: ' . htmlspecialchars($conn->error));
    }
 
    $stmt->bind_param("isssi", $t_name_id, $result, $pipe_end, $degree, $t_id);
    $stmt->execute();
 
    if ($stmt->affected_rows > 0) {
        $_SESSION['alertMessage'] = "แก้ไขประเภทและขนาดท่อเรียบร้อยแล้ว";
        $_SESSION['alertIcon'] = "success";
    } else {
        $_SESSION['alertMessage'] = "ไม่สามารถแก้ไขประเภทและขนาดท่อได้";
        $_SESSION['alertIcon'] = "error";
    }
 
    $stmt->close();
 
    // Redirect back to the edit page
    header('Location: edit_type.php?T_ID=' . $t_id);
    exit();
}
 
include 'manager_index.html';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขประเภทและขนาดท่อ</title>
    <link rel="stylesheet" href="../css/type.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
</head>
<body>
    <a href="../manager/type.php" class="back-link">ย้อนกลับ</a>
    <div class="add">
        <form action="edit_type.php" method="POST" id="editTypeForm">
            <h2>แก้ไขประเภทและขนาดท่อ</h2>

            <div class="form-group">
                <label for="TD_ID" class="asterisk">ชื่อประเภทท่อ</label>
                <select id="TD_ID" name="TD_ID" required>
                    <option value="">เลือก...</option>
                    <?php
                    if ($pipeNameResult && $pipeNameResult->num_rows > 0) {
                        while ($row = $pipeNameResult->fetch_assoc()) {
                            $selected = ($row['TD_ID'] == $existingData['TD_ID']) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($row['TD_ID']) . "\" $selected>" . htmlspecialchars($row['TD_Name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            

            <div class="form-group">
    <label for="Pipe_Size" class="asterisk">ขนาดท่อ :</label>
 
    <span class="input-group-text" id="basic-addon1">ขนาด</span>
    <input type="number" class="form-control" name="Pipe_Size1" placeholder=" " aria-label="Pipe_Size1" aria-describedby="basic-addon1" step="0.01" min="0" value="<?php echo htmlspecialchars($pipeSize1); ?>">
   
    <span class="input-group-text" id="basic-addon2">ความหนา</span>
    <input type="number" class="form-control" name="Pipe_Size2" placeholder=" " aria-label="Pipe_Size2" aria-describedby="basic-addon2" step="0.01" min="0" value="<?php echo htmlspecialchars($pipeSize2); ?>">
 
    <span class="input-group-text" id="basic-addon3">ความยาว</span>
    <input type="number" class="form-control" name="Pipe_Size3" placeholder=" " aria-label="Pipe_Size3" aria-describedby="basic-addon3" step="0.01" min="0" value="<?php echo htmlspecialchars($pipeSize3); ?>">
 
</div>

            <div class="form-group">
                <label for="Pipe_End" class="asterisk">ลักษณะปลายท่อ</label>
                <select id="Pipe_End" name="Pipe_End" required>
                    <option value="">เลือก...</option>
                    <?php
                    if ($pipeEndResult && $pipeEndResult->num_rows > 0) {
                        while ($row = $pipeEndResult->fetch_assoc()) {
                            $selected = ($row['PE_ID'] == $existingData['PE_ID']) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($row['PE_ID']) . "\" $selected>" . htmlspecialchars($row['PE_Name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="degree" class="asterisk">องศา</label>
                <input type="number" id="degree" name="degree" value="<?php echo htmlspecialchars($existingData['degree']); ?>" step="0.01" min="0" max="360">
            </div>

            <div class="footer">
                <button type="submit" class="approve">บันทึกการเปลี่ยนแปลง</button>
                <button type="reset" class="delete">ยกเลิก</button>
            </div>
        </form>
    </div>

    <!-- JavaScript for SweetAlert2 -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php
            if (isset($_SESSION['alertMessage']) && isset($_SESSION['alertIcon'])) {
                echo "Swal.fire({
                    icon: '{$_SESSION['alertIcon']}',
                    title: '{$_SESSION['alertMessage']}',
                    showConfirmButton: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'type.php';
                    }
                });";
                unset($_SESSION['alertMessage']);
                unset($_SESSION['alertIcon']);
            }
            ?>
        });
    </script>
</body>
</html>
