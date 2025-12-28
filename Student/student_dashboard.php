<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

include __DIR__ . '/../db.php';

$student_id = $_SESSION['student_id'] ?? 0;
$msg = '';

// Handle resolve request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_report'])) {
    $report_id = (int)$_POST['report_id'];

    $stmt = $conn->prepare("UPDATE maintenance_reports SET status = 'Resolved' WHERE id = ? AND student_id = ? AND status != 'Resolved'");
    $stmt->bind_param("ii", $report_id, $student_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $msg = "<div style='color:#155724; background:#d4edda; padding:12px; border-radius:8px; margin-bottom:20px; text-align:center;'>
                    <strong>Success!</strong> Report marked as Resolved.
                </div>";
    } else {
        $msg = "<div style='color:#721c24; background:#f8d7da; padding:12px; border-radius:8px; margin-bottom:20px; text-align:center;'>
                    Error: Could not update report (already resolved or not yours).
                </div>";
    }
    $stmt->close();
}

// Fetch student info
$stmt = $conn->prepare("
    SELECT s.student_id, s.first_name, s.last_name, s.phone, 
           b.block_number, s.room_number
    FROM students s
    LEFT JOIN blocks b ON s.block_id = b.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

// Fetch student's maintenance reports
$stmt = $conn->prepare("
    SELECT id, type, description, status, created_at 
    FROM maintenance_reports 
    WHERE student_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$reports = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - WSU Dorm</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f8f9fa;
            --card: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: var(--bg);
            margin: 0;
            padding: 30px 20px;
            color: var(--text);
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { font-size: 2.4rem; color: var(--primary); margin-bottom: 12px; }
        .info-card {
            background: var(--card);
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
            font-size: 1.05rem;
        }
        .label { color: var(--muted); font-weight: 600; }
        .reports-section { margin-bottom: 48px; }
        .report-card {
            background: var(--card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            transition: all 0.2s;
        }
        .report-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .report-type {
            font-weight: 600;
            font-size: 1.15rem;
        }
        .status {
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .status-pending { background: #fee2e2; color: #991b1b; }
        .status-progress { background: #fef3c7; color: #92400e; }
        .status-resolved { background: #d1fae5; color: #065f46; }
        .resolve-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .resolve-btn:hover { background: #059669; }
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: center;
        }
        .action-btn {
            padding: 14px 28px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .action-btn:hover {
            background: #4f46e5;
            transform: translateY(-2px);
        }
        .logout { background: #ef4444; }
        .logout:hover { background: #dc2626; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Welcome, <?= htmlspecialchars($me['first_name'] ?? 'Student') ?></h1>
    </div>

    <div class="info-card">
        <div class="info-row">
            <span class="label">Student ID</span>
            <span><?= htmlspecialchars($me['student_id'] ?? '—') ?></span>
        </div>
        <div class="info-row">
            <span class="label">Full Name</span>
            <span><?= htmlspecialchars(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Phone</span>
            <span><?= htmlspecialchars($me['phone'] ?: 'Not set') ?></span>
        </div>
        <div class="info-row">
            <span class="label">Block</span>
            <span><?= $me['block_number'] ? "Block {$me['block_number']}" : 'Not assigned' ?></span>
        </div>
        <div class="info-row">
            <span class="label">Room</span>
            <span><?= $me['room_number'] ?: 'Not assigned' ?></span>
        </div>
    </div>

    <div class="reports-section">
        <h2>Your Maintenance Reports</h2>

        <?php if ($reports->num_rows === 0): ?>
            <div style="text-align:center; padding:40px; background:white; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.06);">
                <i class="fas fa-info-circle" style="font-size:3rem; color:var(--primary); margin-bottom:16px;"></i>
                <h3>No reports submitted yet</h3>
                <p style="color:var(--muted);">Use the button below to report any maintenance issues.</p>
            </div>
        <?php else: ?>
            <?php while ($report = $reports->fetch_assoc()): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div class="report-type"><?= htmlspecialchars($report['type']) ?></div>
                        <span class="status status-<?= strtolower($report['status']) ?>">
                            <?= htmlspecialchars($report['status']) ?>
                        </span>
                    </div>

                    <div class="report-desc" title="<?= htmlspecialchars($report['description']) ?>">
                        <?= htmlspecialchars(substr($report['description'] ?? '', 0, 120)) . (strlen($report['description'] ?? '') > 120 ? '...' : '') ?>
                    </div>

                    <div style="color:var(--muted); font-size:0.95rem; margin-top:12px;">
                        Reported on: <?= date('d M Y • H:i', strtotime($report['created_at'])) ?>
                    </div>

                    <?php if ($report['status'] !== 'Resolved'): ?>
                        <form method="post" style="margin-top:16px; text-align:right;">
                            <input type="hidden" name="resolve_report" value="1">
                            <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                            <button type="submit" class="resolve-btn" 
                                    onclick="return confirm('Are you sure this problem is resolved?');">
                                <i class="fas fa-check-circle"></i> Mark as Resolved
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
    <div class="quick-actions">
        <?php

        if($me['room_number']){
           echo "<a href='report.php' class='action-btn'>";
           echo " <i class='fas fa-plus-circle'></i> Report Maintenance Problem";
           echo " </a>";
           echo "<a href='clearance.php' class='action-btn'>";
           echo "<i class='fas fa-file-alt'></i> Generate Clearance Form
        </a>";
        }
             
         ?>
       
        <a href="edit_profile.php" class="action-btn">
            <i class="fas fa-user-edit"></i> Edit Profile
        </a>
        
        <a href="../logout.php" class="action-btn logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

</body>
</html>