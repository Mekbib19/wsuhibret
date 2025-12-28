<?php
// db.php
$host = 'localhost';
$db   = 'dkam';               // ← changed to match your dump
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>