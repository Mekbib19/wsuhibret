<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter username and password.";
    } else {
        // 1. Try as STUDENT (most common)
        $stmt = $conn->prepare("SELECT id, password FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password,$row['password'])) {  // plain text – upgrade later to password_verify
                $_SESSION['role'] = 'student';
                $_SESSION['student_id'] = $row['id'];
                header("Location: student/student_dashboard.php");
                exit;
            }
        }
        $stmt->close();

        // 2. Try as PROCTOR
        if (!$error) {
            $stmt = $conn->prepare("SELECT id, password FROM proctors WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (password_verify($password,$row['password'])) {
                    $_SESSION['role'] = 'proctor';
                    $_SESSION['proctor_id'] = $row['id'];
                    header("Location: proctor/dashboard.php");
                    exit;
                }
            }
            $stmt->close();
        }

        // 3. Try as ADMIN (hardcoded)
        if (!$error) {
             $stmt = $conn->prepare("SELECT username, password FROM admin WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (password_verify($password,$row['password'])) {
                    $_SESSION['role'] = 'admin';
                   header("Location: admin/dashboard.php");
                    exit;
                }
            }
            $stmt->close();
        }

        // If we get here → login failed
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WSU Dorm Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 420px;
            margin: 60px auto;
            padding: 20px;
            background: #f8f9fa;
        }
        h2 { text-align: center; color: #333; }
        .error { color: #dc3545; text-align: center; font-weight: bold; }
        form { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        label { display: block; margin: 15px 0 5px; font-weight: bold; color: #444; }
        input { 
            width: 100%; 
            padding: 12px; 
            box-sizing: border-box; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            font-size: 16px;
        }
        input:focus { border-color: #007bff; outline: none; }
        .button-group { margin-top: 25px; text-align: center; }
        button {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover { background: #0056b3; }
        .register-link { margin-left: 20px; color: #007bff; text-decoration: none; }
        .register-link:hover { text-decoration: underline; }
        .info { margin-top: 20px; font-size: 0.9em; color: #666; text-align: center; }
    </style>
</head>
<body>

<h2>WSU Dorm Management System</h2>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post">
    <label>Student ID / Username</label>
    <input type="text" name="username" required autofocus placeholder="e.g. UGR/919/001 or proctor1 or admin">

    <label>Password</label>
    <input type="password" name="password" required>

    <div class="button-group">
        <button type="submit">Login</button>
        <a href="register.php" class="register-link">Register (for students)</a>
    </div>
</form>

<div class="info">
    <p><small>
        • Students: use your Student ID (e.g. UGR/919/001)<br>
        • Proctors: use your username from proctors table<br>
        • Admin: username <strong>admin</strong> / password <strong>admin123</strong>
    </small></p>
</div>

</body>
</html>