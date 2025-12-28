<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'proctor') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

// Get proctor's assigned block
$proctor_block_id = null;
$proctor_block_number = null;
$proctor_capacity = null;

if (isset($_SESSION['proctor_id'])) {
    $stmt = $conn->prepare("SELECT block_id FROM proctors WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['proctor_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $proctor_block_id = $row['block_id'];
    }
    $stmt->close();
}

if ($proctor_block_id !== null) {
    $stmt = $conn->prepare("SELECT block_number, capacity FROM blocks WHERE id = ?");
    $stmt->bind_param("i", $proctor_block_id);
    $stmt->execute();
    $block_info = $stmt->get_result()->fetch_assoc();
    if ($block_info) {
        $proctor_block_number = $block_info['block_number'];
        $proctor_capacity = (int)$block_info['capacity'];
    }
    $stmt->close();
}

$msg = '';
$search = trim($_GET['search'] ?? '');

// Handle assignment
if (isset($_POST['assign'])) {
    $student_id   = (int)$_POST['student_id'];
    $block_id     = (int)$_POST['block_id'];
    $room_number  = (int)$_POST['room_number'];

    if ($proctor_block_id !== null && $block_id != $proctor_block_id) {
        $msg = "<div class='alert alert-danger'>You can only assign students to Block $proctor_block_number.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE students SET block_id = ?, room_number = ? WHERE id = ?");
        $stmt->bind_param("iii", $block_id, $room_number, $student_id);
        
        if ($stmt->execute()) {
            $msg = $stmt->affected_rows > 0 
                ? "<div class='alert alert-success'>Student assigned/updated successfully.</div>"
                : "<div class='alert alert-warning'>No changes made (same values).</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Error: " . htmlspecialchars($conn->error) . "</div>";
        }
        $stmt->close();
    }
}

// Students query
$students_query = "
    SELECT s.id, s.student_id, s.first_name, s.last_name, s.phone,
           b.id AS block_id, b.block_number, s.room_number
    FROM students s
    LEFT JOIN blocks b ON s.block_id = b.id
";
$params = [];
$types = "";

if ($proctor_block_id !== null) {
    $students_query .= " WHERE s.block_id = ?";
    $types .= "i";
    $params[] = $proctor_block_id;
}

if ($search !== '') {
    $like = "%$search%";
    $students_query .= $proctor_block_id !== null ? " AND " : " WHERE ";
    $students_query .= "(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $types .= "sss";
    $params = array_merge($params, [$like, $like, $like]);
}

$students_query .= " ORDER BY s.student_id";

$stmt = $conn->prepare($students_query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result();

// Blocks (only if no fixed block)
$blocks = null;
if ($proctor_block_id === null) {
    $blocks = $conn->query("SELECT id, block_number, capacity FROM blocks ORDER BY block_number");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Students – Proctor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="container">
    <div class="page-header">
        <h1>Assign / Update Students</h1>
    </div>

    <?= $msg ?>

    <?php if ($proctor_block_id !== null): ?>
        <div class="assigned-notice">
            Assigned to: 
            <span class="badge badge-primary">
                Block <?= htmlspecialchars($proctor_block_number) ?> 
                (capacity: <?= $proctor_capacity ?? '—' ?>)
            </span>
        </div>
    <?php endif; ?>

    <form class="search-form" method="get">
        <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="search" placeholder="Search by ID, name..." 
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="?" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($students->num_rows === 0): ?>
        <div class="empty-state">
            <i class="fas fa-users-slash fa-3x"></i>
            <h3>No students found</h3>
            <p>
                <?php if ($search): ?>
                    No matches for "<strong><?= htmlspecialchars($search) ?></strong>".
                <?php elseif ($proctor_block_id !== null): ?>
                    No students assigned to your block yet.
                <?php else: ?>
                    No students in the system.
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
                    <div class="student-info">
                        <h3><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h3>
                        <p class="phone"><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($student['phone'] ?: '—') ?></p>
                        <p class="block-info">
                            <strong>Block:</strong> 
                            <?= $student['block_number'] 
                                ? "Block " . htmlspecialchars($student['block_number']) 
                                : 'Not assigned' ?>
                        </p>
                        <p class="room-info">
                            <strong>Room:</strong> <?= htmlspecialchars($student['room_number'] ?: '—') ?>
                        </p>
                    </div>
                    <form class="assign-form" method="post">
                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">

                        <?php if ($proctor_block_id !== null): ?>
                            <div class="fixed-block-info">
                                <input type="hidden" name="block_id" value="<?= $proctor_block_id ?>">
                                <span class="badge badge-info">Block <?= htmlspecialchars($proctor_block_number) ?></span>
                                <small>(max room: <?= $proctor_capacity ?? '—' ?>)</small>
                            </div>
                        <?php else: ?>
                            <select name="block_id" class="block-select" required>
                                <option value="">Select Block</option>
                                <?php 
                                if ($blocks) {
                                    while ($b = $blocks->fetch_assoc()): ?>
                                        <option value="<?= $b['id'] ?>"
                                                data-capacity="<?= $b['capacity'] ?>"
                                                <?= ($student['block_id'] == $b['id']) ? 'selected' : '' ?>>
                                            Block <?= $b['block_number'] ?> (cap: <?= $b['capacity'] ?>)
                                        </option>
                                    <?php endwhile; 
                                    $blocks->data_seek(0); ?>
                                <?php } ?>
                            </select>
                        <?php endif; ?>

                        <input type="number" name="room_number" min="1" 
                               max="<?= $proctor_block_id !== null ? ($proctor_capacity ?? 999) : 999 ?>" 
                               value="<?= htmlspecialchars($student['room_number'] ?? '') ?>" 
                               placeholder="Room #" required class="room-input">

                        <button type="submit" name="assign" class="btn btn-success">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>

    <?php endif; ?>

    <div class="footer-nav">
        <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <a href="../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<?php if ($proctor_block_id === null && $blocks && $blocks->num_rows > 0): ?>
<script>
document.querySelectorAll('.block-select').forEach(select => {
    const form = select.closest('.assign-form');
    const roomInput = form.querySelector('.room-input');
    
    function updateMax() {
        const selected = select.options[select.selectedIndex];
        const cap = selected.dataset.capacity || 999;
        roomInput.max = cap;
    }
    
    select.addEventListener('change', updateMax);
    updateMax();
});
</script>
<?php endif; ?>

</body>
</html>