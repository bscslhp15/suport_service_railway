<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if (!$user) {
    header('Location: ../auth/student_login.php');
    exit;
}
if (!in_array($user['role'], ['student', 'teacher'], true)) {
    header('Location: ../auth/student_login.php');
    exit;
}

$displayCourse = $user['course'] ?? $user['course_year'] ?? 'Course / Department';
$displayYear = $user['year_level'] ?? null;
if (!$displayYear && !empty($user['course_year'])) {
    $parts = explode(' ', trim($user['course_year']));
    $lastPart = end($parts);
    if (in_array($lastPart, ['1', '2', '3', '4'], true)) {
        $displayYear = $lastPart;
        array_pop($parts);
        $displayCourse = implode(' ', $parts) ?: $displayCourse;
    }
}
if (!$displayYear) {
    $displayYear = 'N/A';
}

$roleLabel = $user['role'] === 'teacher' ? 'Teacher' : 'Student';
$dashboardLink = $user['role'] === 'teacher' ? 'teacher_home.php' : 'student_home.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        mark_notification_read((int)$_POST['notification_id']);
    } elseif ($_POST['action'] === 'mark_all_read') {
        mark_all_notifications_read($user['id']);
    }
}

$notifications = get_user_notifications($user['id'], 50);
$unreadCount = get_unread_notifications_count($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>Library Portal</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Menu</h3>
                <a href="<?= htmlspecialchars($dashboardLink) ?>"><span class="nav-icon">⌂</span>Dashboard</a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="true">
                        <span class="nav-icon">▸</span>
                        Services
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu open" aria-hidden="false">
                        <a href="guidance_home.php"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Guidance</a>
                        <a href="library_dashboard.php" class="active"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Library</a>
                        <a href="nurse_home.php"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>Clinic</a>
                        <a href="ssc_head_home.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>SSC</a>
                        <a href="ssaa_home.php"><span class="nav-icon"><i class="fa-solid fa-users"></i></span>SSAA</a>
                    </div>
                </div>
                <a href="notification.php" class="active"><span class="nav-icon">⚑</span>Notifications</a>
                <a href="#support"><span class="nav-icon">⚙</span>Support</a>
                <a href="#profile"><span class="nav-icon">👤</span>Profile</a>
                <a href="about.php"><span class="nav-icon">ℹ</span>About</a>
            </div>
            <div class="nav-footer">
                <a href="#settings"><span class="nav-icon">⚙</span>Settings</a>
                <a href="../logout.php"><span class="nav-icon">↪</span>Logout</a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="search" placeholder="Search notifications" />
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($displayCourse) ?> · <?= htmlspecialchars($displayYear) ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Help"><i class="fa-solid fa-question-circle"></i></button>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel">
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow">System Notifications</p>
                            <h1>Your Notifications</h1>
                            <p class="dashboard-subtitle">Stay updated on your library reservations, returns, and system activities.</p>
                        </div>
                        <?php if ($unreadCount > 0): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="primary-button" style="margin-top:16px;">Mark all as read</button>
                            </form>
                        <?php endif; ?>
                    </section>

                    <section class="library-panel">
                        <div class="library-table-card">
                            <?php if (empty($notifications)): ?>
                                <div style="text-align: center; padding: 40px 20px;">
                                    <p class="book-meta"><i class="fa-solid fa-inbox" style="font-size: 32px; color: #cbd5e1; margin-bottom: 16px; display: block;"></i>You're all caught up!</p>
                                    <p class="book-meta">No notifications at the moment.</p>
                                </div>
                            <?php else: ?>
                                <ul class="notification-list">
                                    <?php foreach ($notifications as $notif): ?>
                                        <li class="notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?>">
                                            <div class="notification-content">
                                                <h4 class="notification-title"><?= htmlspecialchars($notif['title']) ?></h4>
                                                <p class="notification-message"><?= htmlspecialchars($notif['message']) ?></p>
                                                <span class="notification-time"><?= (new DateTime($notif['created_at']))->format('M d, Y · h:i A') ?></span>
                                            </div>
                                            <?php if (!$notif['is_read']): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?= htmlspecialchars($notif['id']) ?>">
                                                    <button type="submit" class="notification-action" title="Mark as read"><i class="fa-solid fa-check"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.querySelector('.nav-toggle')?.addEventListener('click', function () {
            this.nextElementSibling.classList.toggle('open');
            this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
        });
    </script>
</body>
</html>
