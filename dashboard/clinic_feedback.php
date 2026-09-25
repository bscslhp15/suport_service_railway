<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'clinic') {
    header('Location: /auth/teacher_login.php');
    exit;
}

// Initialize clinic schema
ensure_clinic_schema();

// Get feedback data
$pendingFeedback = get_pending_clinic_feedback();

// Handle form submissions
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_feedback'])) {
        $feedbackId = (int)$_POST['feedback_id'];

        if (approve_clinic_feedback($feedbackId, $user['id'])) {
            $message = 'Feedback approved and published!';
            $pendingFeedback = get_pending_clinic_feedback();
        } else {
            $message = 'Failed to approve feedback.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feedback Management | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-light: #f8f9fa;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--clinic-maroon);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background: var(--clinic-maroon);
            color: white;
        }

        .btn-primary:hover {
            background: #660000;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .alert-card {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-card.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-card.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0;
            color: var(--clinic-maroon);
        }

        .feedback-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--clinic-maroon);
        }

        .feedback-card .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .feedback-card .patient-info {
            font-weight: 600;
            color: var(--clinic-maroon);
        }

        .feedback-card .date {
            color: #666;
            font-size: 0.9rem;
        }

        .feedback-card .rating {
            color: #ffc107;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .feedback-card .comments {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #e9ecef;
        }

        .feedback-card .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }

        .metric-item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .metric-item .label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 5px;
        }

        .metric-item .value {
            font-weight: 600;
            color: var(--clinic-maroon);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid var(--clinic-maroon);
        }

        .stat-card h4 {
            margin: 0 0 10px 0;
            color: var(--clinic-maroon);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--clinic-maroon);
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>Clinic Head</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Menu</h3>
                <a href="nurse_home.php"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>Dashboard</a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false">
                        <span class="nav-icon">▸</span>
                        Services
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_home.php"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Guidance</a>
                        <a href="librarian_home.php"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Library</a>
                        <a href="nurse_home.php"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>Clinic</a>
                        <a href="ssc_head_home.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>SSC / Scholarship</a>
                        <a href="ssc_head_home.php#scholarship"><span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>Scholarship</a>
                        <a href="ssaa_home.php"><span class="nav-icon"><i class="fa-solid fa-users"></i></span>SSAA</a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false">
                        <span class="nav-icon">▸</span>
                        Manage
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="clinic_status.php"><span class="nav-icon"><i class="fa-solid fa-clock"></i></span>Clinic Status</a>
                        <a href="clinic_health_records.php"><span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>Health Records</a>
                        <a href="clinic_medicine_inventory.php"><span class="nav-icon"><i class="fa-solid fa-pills"></i></span>Medicine Inventory</a>
                        <a href="clinic_visit_logs.php"><span class="nav-icon"><i class="fa-solid fa-notes-medical"></i></span>Visit Logs</a>
                        <a href="clinic_clearance_requests.php"><span class="nav-icon"><i class="fa-solid fa-clipboard-check"></i></span>Clearance Requests</a>
                        <a href="clinic_announcements.php"><span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>Announcements</a>
                        <a href="clinic_analytics.php"><span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>Analytics</a>
                    </div>
                </div>
                <a href="#notifications"><span class="nav-icon">⚑</span>Notifications</a>
                <a href="#support"><span class="nav-icon">⚙</span>Support</a>
                <a href="#profile"><span class="nav-icon">👤</span>Profile</a>
                <a href="about.php"><span class="nav-icon">ℹ</span>About</a>
            </div>
            <div class="nav-footer">
                <a href="#settings"><span class="nav-icon"><i class="fa-solid fa-gear"></i></span>Settings</a>
                <a href="../logout.php"><span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="search" placeholder="Search feedback..." />
                        <button type="button" class="mic-button"><i class="fa-solid fa-microphone"></i></button>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Clinic Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></button>
                    <button type="button" class="topbar-icon" aria-label="About"><i class="fa-solid fa-question-circle"></i></button>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel">
                    <?php if ($message): ?>
                        <div class="alert-card <?= $messageType ?>">
                            <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Feedback Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-clock"></i> Pending Reviews</h4>
                            <div class="stat-value"><?= count($pendingFeedback) ?></div>
                            <div class="stat-label">Awaiting approval</div>
                        </div>
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-star"></i> Average Rating</h4>
                            <div class="stat-value">
                                <?php
                                $pdo = get_db();
                                $stmt = $pdo->prepare('SELECT AVG(rating) as avg_rating FROM clinic_feedback WHERE is_approved = 1');
                                $stmt->execute();
                                $avgRating = $stmt->fetchColumn();
                                echo $avgRating ? number_format($avgRating, 1) : '0.0';
                                ?>
                            </div>
                            <div class="stat-label">Overall satisfaction</div>
                        </div>
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-comments"></i> Total Feedback</h4>
                            <div class="stat-value">
                                <?php
                                $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_feedback WHERE is_approved = 1');
                                $stmt->execute();
                                echo $stmt->fetchColumn();
                                ?>
                            </div>
                            <div class="stat-label">Approved reviews</div>
                        </div>
                    </div>

                    <!-- Pending Feedback Reviews -->
                    <?php if (!empty($pendingFeedback)): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-comments"></i> Pending Feedback Reviews</h2>
                        </div>

                        <?php foreach ($pendingFeedback as $feedback): ?>
                        <div class="feedback-card">
                            <div class="header">
                                <div class="patient-info">
                                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars($feedback['patient_name']) ?>
                                </div>
                                <div class="date">
                                    <i class="fa-solid fa-calendar"></i> <?= htmlspecialchars(date('M d, Y H:i', strtotime($feedback['created_at']))) ?>
                                </div>
                            </div>

                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-solid fa-star<?= $i <= $feedback['rating'] ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                                <span style="color: #333; margin-left: 10px; font-size: 0.9rem;">(<?= $feedback['rating'] ?>/5)</span>
                            </div>

                            <?php if ($feedback['comments']): ?>
                            <div class="comments">
                                <strong>Comments:</strong><br>
                                <?= nl2br(htmlspecialchars($feedback['comments'])) ?>
                            </div>
                            <?php endif; ?>

                            <div class="metrics">
                                <?php if ($feedback['service_quality']): ?>
                                <div class="metric-item">
                                    <div class="label">Service Quality</div>
                                    <div class="value"><?= htmlspecialchars($feedback['service_quality']) ?>/5</div>
                                </div>
                                <?php endif; ?>

                                <?php if ($feedback['staff_attitude']): ?>
                                <div class="metric-item">
                                    <div class="label">Staff Attitude</div>
                                    <div class="value"><?= htmlspecialchars($feedback['staff_attitude']) ?>/5</div>
                                </div>
                                <?php endif; ?>

                                <?php if ($feedback['waiting_time']): ?>
                                <div class="metric-item">
                                    <div class="label">Waiting Time</div>
                                    <div class="value"><?= htmlspecialchars($feedback['waiting_time']) ?>/5</div>
                                </div>
                                <?php endif; ?>

                                <?php if ($feedback['facility_cleanliness']): ?>
                                <div class="metric-item">
                                    <div class="label">Facility Cleanliness</div>
                                    <div class="value"><?= htmlspecialchars($feedback['facility_cleanliness']) ?>/5</div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 15px;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="feedback_id" value="<?= $feedback['id'] ?>">
                                    <button type="submit" name="approve_feedback" class="btn btn-success btn-sm">
                                        <i class="fa-solid fa-check"></i> Approve & Publish
                                    </button>
                                </form>
                                <button type="button" class="btn btn-primary btn-sm" style="background: #6c757d;" onclick="rejectFeedback(<?= $feedback['id'] ?>)">
                                    <i class="fa-solid fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-comments"></i> Feedback Management</h2>
                        </div>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fa-solid fa-comments" style="font-size: 48px; margin-bottom: 20px; color: var(--clinic-maroon);"></i>
                            <h3>No Pending Feedback</h3>
                            <p>All feedback has been reviewed and processed.</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Published Feedback History -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-history"></i> Published Feedback</h2>
                        </div>

                        <?php
                        $stmt = $pdo->prepare('SELECT cf.*, u.full_name as patient_name FROM clinic_feedback cf JOIN users u ON cf.user_id = u.id WHERE cf.is_approved = 1 ORDER BY cf.created_at DESC LIMIT 20');
                        $stmt->execute();
                        $publishedFeedback = $stmt->fetchAll();

                        if (!empty($publishedFeedback)):
                        ?>
                        <?php foreach ($publishedFeedback as $feedback): ?>
                        <div class="feedback-card" style="border-left-color: #28a745; opacity: 0.8;">
                            <div class="header">
                                <div class="patient-info">
                                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars($feedback['patient_name']) ?>
                                </div>
                                <div class="date">
                                    <i class="fa-solid fa-calendar"></i> <?= htmlspecialchars(date('M d, Y H:i', strtotime($feedback['created_at']))) ?>
                                    <span style="margin-left: 10px; color: #28a745; font-weight: bold;">✓ Published</span>
                                </div>
                            </div>

                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-solid fa-star<?= $i <= $feedback['rating'] ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                                <span style="color: #333; margin-left: 10px; font-size: 0.9rem;">(<?= $feedback['rating'] ?>/5)</span>
                            </div>

                            <?php if ($feedback['comments']): ?>
                            <div class="comments">
                                <strong>Comments:</strong><br>
                                <?= nl2br(htmlspecialchars($feedback['comments'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fa-solid fa-star" style="font-size: 48px; margin-bottom: 20px; color: #ffc107;"></i>
                            <h3>No Published Feedback</h3>
                            <p>Approved feedback will appear here.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        function rejectFeedback(feedbackId) {
            if (confirm('Are you sure you want to reject this feedback? It will be permanently deleted.')) {
                // For now, just show an alert. In a real implementation, you'd send a request to delete it.
                alert('Feedback rejection functionality would be implemented here.');
            }
        }
    </script>
</body>
</html>