<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['generate_otp'])) {
    header("Location: add_student.php");
    exit;
}

$student_id = trim($_POST['student_id'] ?? '');
$block_id   = (int)($_POST['block_id'] ?? 0);

if ($student_id === '' || !$block_id) {
    $_SESSION['otp_message'] = "Student ID and Block are required.";
    header("Location: add_student.php");
    exit;
}

/* Generate secure OTP */
$otp = random_int(100000, 999999);

/* Calculate expiration */
$expire_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));


/* Get block number */
$stmt = $conn->prepare("SELECT block_number FROM blocks WHERE id = ?");
$stmt->bind_param("i", $block_id);
$stmt->execute();
$stmt->bind_result($block_number);
$stmt->fetch();
$stmt->close();

/* Insert / Update OTP */
$stmt = $conn->prepare("
    INSERT INTO registration_tokens (student_id, otp, temp_block, created_at, expires_at)
    VALUES (?, ?, ?, NOW(), ?)
    ON DUPLICATE KEY UPDATE
        otp = VALUES(otp),
        temp_block = VALUES(temp_block),
        used = 0,
        created_at = NOW(),
        expires_at = VALUES(expires_at)
");
$stmt->bind_param("siis", $student_id, $otp, $block_id, $expire_at);
$stmt->execute();
$stmt->close();

/* Session data for modal */
$_SESSION['otp_data'] = [
    'student_id' => $student_id,
    'block'      => 'Block ' . $block_number,
    'otp'        => $otp,
    'date'       => date('d M Y H:i'),
    'expires_at'  => $expire_at
];

$_SESSION['otp_message'] = "OTP generated successfully.";
header("Location: add_student.php");
exit;
