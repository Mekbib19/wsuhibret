<?php
session_start();
include 'db.php';   // your db.php with $conn

$msg = '';
$success = false;

function validName($text) {
    return preg_match("/^[A-Za-z]+$/", $text);
}
function validPhone($text) {
    return preg_match("/^(09\d{8}|\+2519\d{8})$/", $text);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $otp        = trim($_POST['otp'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $confirm    = trim($_POST['confirm'] ?? '');

    // Basic validation
    if (empty($student_id) || empty($otp) || empty($first_name) || empty($last_name) || empty($password) || empty($confirm)) {
        $msg = "<div class='message error'>All required fields must be filled.</div>";
    } elseif (!validName($first_name) || !validName($last_name)) {
        $msg = "<div class='message error'>Valid first and last names are required.</div>";
    } elseif (!empty($phone) && !validPhone($phone)) {
        $msg = "<div class='message error'>Valid phone number required.</div>";
    } elseif ($password !== $confirm) {
        $msg = "<div class='message error'>Passwords must match.</div>";
    } else {
        // Step 1: Get OTP record
        $stmt = $conn->prepare("
            SELECT id, temp_block, used, expires_at
            FROM registration_tokens
            WHERE student_id = ? AND otp = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $student_id, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $msg = "<div class='message error'>Invalid Student ID or OTP.</div>";
        } else {
            $row = $result->fetch_assoc();

            if ((int)$row['used'] === 1) {
                $msg = "<div class='message error'>OTP has already been used.</div>";
            } elseif (strtotime($row['expires_at']) < time()) {
                $msg = "<div class='message error'>OTP has expired.</div>";
            } else {
                // OTP is valid → proceed
                $block_id = $row['temp_block'];
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert / update student
                $stmt2 = $conn->prepare("
                    INSERT INTO students (student_id, first_name, last_name, phone, password, block_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        first_name = VALUES(first_name),
                        last_name  = VALUES(last_name),
                        phone      = VALUES(phone),
                        password   = VALUES(password),
                        block_id   = VALUES(block_id)
                ");
                $stmt2->bind_param("sssssi", $student_id, $first_name, $last_name, $phone, $hashed_password, $block_id);

                if ($stmt2->execute()) {
                    // Mark OTP as used
                    $stmt3 = $conn->prepare("UPDATE registration_tokens SET used = 1 WHERE id = ?");
                    $stmt3->bind_param("i", $row['id']);
                    $stmt3->execute();
                    $stmt3->close();

                    $msg = "<div class='message success'>Account activated successfully! You can now <a href='login.php'>login</a>.</div>";
                    $success = true;
                } else {
                    $msg = "<div class='message error'>Database error: " . htmlspecialchars($conn->error) . "</div>";
                }
                $stmt2->close();
            }
        }
        $stmt->close();
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration / Activation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 520px;
            margin: 40px auto;
            padding: 20px;
            background: #f9f9f9;
        }
        h2 { color: #2c3e50; text-align: center; }
        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #444;
        }
        input, button {
            width: 100%;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        input:focus { border-color: #3498db; outline: none; }
        button {
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover { background: #2980b9; }
        .message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
        }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .error   { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
    </style>
</head>
<body>

<h2>Activate Your Dorm Account</h2>
<p style="text-align:center;">Enter your Student ID and the OTP provided by the Admin.</p>

<?php if ($msg): ?>
    <div class="message <?= $success ? 'success' : 'error' ?>">
        <?= $msg ?>
    </div>
<?php endif; ?>

<?php if (!$success): ?>
<form method="post">
    <div class="form-group">
        <label>Student ID <span style="color:red">*</span></label>
        <input type="text" name="student_id" placeholder="e.g. UGR/919/001" 
               value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
    </div>

    <div class="form-group">
        <label>OTP <span style="color:red">*</span></label>
        <input type="number" name="otp" required maxlength="10">
    </div>

    <div class="form-group">
        <label>First Name <span style="color:red">*</span></label>
        <input type="text" name="first_name" required>
    </div>

    <div class="form-group">
        <label>Last Name <span style="color:red">*</span></label>
        <input type="text" name="last_name" required>
    </div>

    <div class="form-group">
        <label>Phone Number</label>
        <input type="tel" name="phone" placeholder="09XXXXXXXX">
    </div>

    <div class="form-group">
        <label>Choose Password <span style="color:red">*</span></label>
        <input type="password" name="password" required>
    </div>
    <div class="form-group">
        <label>Confirm Password <span style="color:red">*</span></label>
        <input type="password" name="confirm" required>
    </div>

    <button type="submit">Activate Account</button>
</form>
<?php endif; ?>

<br>
<p style="text-align:center;">
    Already activated? <a href="login.php" style="color:#3498db;">Login here</a>
</p>

</body>
</html>