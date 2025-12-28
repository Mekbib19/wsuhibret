<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

// Optional: search filter
$search = trim($_GET['search'] ?? '');
$where = "";
$params = [];
$types = "";

if ($search !== '') {
    $like = "%$search%";
    $where = " WHERE (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $types = "sss";
    $params = [$like, $like, $like];
}

// Fetch students
$sql = "
    SELECT s.student_id, s.first_name, s.last_name, s.phone, 
           b.block_number, s.room_number 
    FROM students s 
    LEFT JOIN blocks b ON s.block_id = b.id
    $where
    ORDER BY s.student_id
";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students List - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="container">
        <div class="header">
            <h2>Students in Dorm</h2>

            <form class="search-form" method="get">
                <input type="text" name="search" placeholder="Search by ID or name..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
                <?php if ($search): ?>
                    <a href="?" style="color:var(--primary);">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($students->num_rows === 0): ?>
            <div class="empty-state">
                <i class="fas fa-users-slash" style="font-size:4rem; color:var(--muted); margin-bottom:20px;"></i>
                <h3>No students found</h3>
                <p>
                    <?php if ($search): ?>
                        No matches for your search.
                    <?php else: ?>
                        No students registered yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="student-grid">
                <?php while ($student = $students->fetch_assoc()): ?>
                    <div class="student-card">
                        <div class="student-id">
                            <?= htmlspecialchars($student['student_id'] ?? '—') ?>
                        </div>
                        <div class="info-row">
                            <span class="label">Name</span>
                            <span><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Phone</span>
                            <span><?= htmlspecialchars($student['phone'] ?: '—') ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Block</span>
                            <span><?= $student['block_number'] ? "Block {$student['block_number']}" : 'Not assigned' ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Room</span>
                            <span><?= htmlspecialchars($student['room_number'] ?: '—') ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

       
    </div>
</div>

</body>
</html>
