<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'library') {
    header('Location: /auth/teacher_login.php');
    exit;
}
ensure_library_schema();
auto_cancel_unpicked_reservations(); // Auto-cancel unpicked reservations after 1 day
finalize_library_visits_at_end_of_day(); // Auto-checkout active visits after 5pm

// Core metrics
$todayCheckins = get_today_library_checkins();
$pendingApprovalsData = get_pending_resource_approvals();
$pendingApprovals = count($pendingApprovalsData);
$activeReservations = get_active_library_reservations();
$overdueItems = get_overdue_library_items();
$calendarBlocks = get_library_calendar_blocks(5);
$recentEntries = get_recent_library_entries(4);
$allEntries = get_recent_library_entries(100); // For modal
$libraryCount = get_library_book_count();
$resourceCount = get_library_resource_count();
$activeVisits = get_active_library_visits(5);
$pendingVisits = get_pending_library_visits(5);

// Additional analytics data
$pdo = get_db();

// Weekly activity trend
$weeklyStats = $pdo->query("
    SELECT
        DATE(time_in) as date,
        COUNT(*) as checkins
    FROM library_visits
    WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(time_in)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

// Recent borrowing activity
$recentBorrows = $pdo->query("
    SELECT
        b.title,
        u.full_name as borrower,
        lb.borrow_date,
        lb.due_date,
        lb.status
    FROM library_borrows lb
    JOIN library_books b ON lb.book_id = b.id
    JOIN users u ON lb.user_id = u.id
    ORDER BY lb.borrow_date DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

// All borrowing activity for modal
$allBorrows = $pdo->query("
    SELECT
        b.title,
        u.full_name as borrower,
        lb.borrow_date,
        lb.due_date,
        lb.status
    FROM library_borrows lb
    JOIN library_books b ON lb.book_id = b.id
    JOIN users u ON lb.user_id = u.id
    ORDER BY lb.borrow_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Top borrowed books this month
$topBooks = $pdo->query("
    SELECT
        b.title,
        b.author,
        COUNT(lb.id) as borrow_count
    FROM library_books b
    JOIN library_borrows lb ON b.id = lb.book_id
    WHERE lb.borrow_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY b.id, b.title, b.author
    ORDER BY borrow_count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Fine statistics
$fineStats = $pdo->query("
    SELECT
        SUM(CASE WHEN paid = 1 THEN amount ELSE 0 END) as collected_fines,
        SUM(CASE WHEN paid = 0 THEN amount ELSE 0 END) as outstanding_fines,
        COUNT(CASE WHEN paid = 0 THEN 1 END) as unpaid_fines_count
    FROM library_fines
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);

// System status checks
$systemStatus = [
    'database' => true, // Assume OK if we got here
    'qr_system' => file_exists(__DIR__ . '/../library_entry.php'),
    'catalog' => $libraryCount > 0,
    'resources' => $resourceCount > 0
];

$currentPage = basename($_SERVER['PHP_SELF']);
$servicesOpen = in_array($currentPage, ['guidance_home.php', 'nurse_home.php', 'ssc_head_home.php', 'ssaa_student_home.php', 'library_dashboard.php'], true);
$manageOpen = false;
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$qrLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/library_entry.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Dashboard | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="manifest" href="../PWA/manifest-teacher.json">
    <meta name="theme-color" content="#800000">
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('../assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
    </style>
    <style>
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }
    </style>
</head>
<body class="librarian-dashboard swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Library Head</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="librarian_home.php" class="active" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="school_announcements.php?service=library" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_dashboard.php?service=library" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="library_dashboard.php?service=library" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="clinic_dashboard.php?service=library" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc/ssc_dashboard.php?service=library" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="scholarship/scholarship_dashboard.php?service=library" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="ssaa_student_home.php?service=library" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
                        </a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="../library_checkin.php" data-tooltip="QR Check-In">
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">QR Check-In</span>
                        </a>
                        <a href="../library_catalog.php" data-tooltip="Digital Catalog">
                            <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                            <span class="nav-text">Digital Catalog</span>
                        </a>
                        <a href="../library_inventory.php" data-tooltip="Inventory">
                            <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                            <span class="nav-text">Inventory</span>
                        </a>
                        <a href="../library_reports.php" data-tooltip="Reports">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span class="nav-text">Reports</span>
                        </a>
                        <a href="../library_settings.php" data-tooltip="Settings">
                            <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                            <span class="nav-text">Settings</span>
                        </a>
                        <a href="../library_qr.php" data-tooltip="Library QR">
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">Library QR</span>
                        </a>
                    </div>
                </div>
                <a href="profile.php?service=library" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="about.php?service=library" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                    <div class="topbar-left">
                        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                        </button>
                        <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Library Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Library Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Download App" data-tooltip="Download App" id="installAppBtn"><i class="fa-solid fa-download"></i></button>

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <div class="menu-empty">No new announcements.</div>
                        <a class="menu-link menu-footer-link" href="about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="profile.php" class="menu-action">View profile</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="about.php">Help center</a>
                            <span class="menu-subtext">View support resources and FAQs.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="mailto:support@passcollege.edu">Email support</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                <!-- Welcome Header -->
                <section class="dashboard-intro dashboard-intro--compact">
                    <div class="dashboard-intro-left">
                        <p class="eyebrow"><i class="fa-solid fa-star sparkle-icon"></i> Library Management System</p>
                        <p class="dashboard-date" style="color: white;"><strong style="color: white;">Today's Date</strong> <?= date('F j, Y') ?></p>
                        <h1>Welcome back, <span class="user-name-highlight">library</span>!</h1>
                        <p class="dashboard-subtitle">Monitor library operations, manage resources, and track user activity from your command center.</p>
                    </div>
                    <aside class="dashboard-intro-right">
                        <div class="dashboard-detail-panel">
                            <div class="detail-row role-row">
                                <div class="detail-icon"><i class="fa-solid fa-book"></i></div>
                                <div class="detail-content">
                                    <div class="detail-top">System</div>
                                    <div class="detail-bottom">Library Management</div>
                                </div>
                            </div>
                            <div class="detail-row course-row">
                                <div class="detail-icon"><i class="fa-solid fa-calendar-days"></i></div>
                                <div class="detail-content">
                                    <div class="detail-top">Date</div>
                                    <div class="detail-bottom"><?= date('F j, Y') ?></div>
                                </div>
                            </div>
                            <div class="detail-row year-row">
                                <div class="detail-icon"><i class="fa-solid fa-location-dot"></i></div>
                                <div class="detail-content">
                                    <div class="detail-top">Dashboard</div>
                                    <div class="detail-bottom">Library Command Center</div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </section>

                    <!-- Key Performance Indicators -->
                    <section class="dashboard-section">
                        <h2>Today's Activity Overview</h2>
                        <div class="metrics-grid">
                            <div class="metric-card">
                                <div class="metric-card-header">
                                    <div class="metric-icon"><i class="fa-solid fa-calendar-check"></i></div>
                                </div>
                                <div class="metric-card-body">
                                    <h3><?= htmlspecialchars($todayCheckins) ?></h3>
                                    <p class="metric-card-label">Check-ins Today</p>
                                    <span class="metric-trend"></span>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-card-header">
                                    <div class="metric-icon"><i class="fa-solid fa-book-open"></i></div>
                                </div>
                                <div class="metric-card-body">
                                    <h3><?= htmlspecialchars($activeReservations) ?></h3>
                                    <p class="metric-card-label">Active Reservations</p>
                                    <span class="metric-trend"></span>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-card-header">
                                    <div class="metric-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                                </div>
                                <div class="metric-card-body">
                                    <h3><?= htmlspecialchars($pendingApprovals) ?></h3>
                                    <p class="metric-card-label">Pending Approvals</p>
                                    <span class="metric-trend"></span>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-card-header">
                                    <div class="metric-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                </div>
                                <div class="metric-card-body">
                                    <h3><?= htmlspecialchars($overdueItems) ?></h3>
                                    <p class="metric-card-label">Overdue Items</p>
                                    <span class="metric-trend"></span>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-card-header">
                                    <div class="metric-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                                </div>
                                <div class="metric-card-body">
                                    <h3>₱<?= number_format($fineStats['outstanding_fines'] ?? 0, 2) ?></h3>
                                    <p class="metric-card-label">Outstanding Fines</p>
                                    <span class="metric-trend"></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Quick Actions -->
                    <section class="dashboard-section quick-actions-section">
                        <div class="section-heading">
                            <div>
                                <h2>Quick Actions</h2>
                                <p class="section-description">Jump to the tools you use most often.</p>
                            </div>
                        </div>
                        <div class="action-grid">
                            <a href="../library_checkin.php" class="action-card">
                                <div class="action-icon-block action-icon-qr">
                                    <i class="fa-solid fa-qrcode"></i>
                                </div>
                                <div class="action-content">
                                    <h3>QR Check-In</h3>
                                    <p>Process visitor entries and exits quickly.</p>
                                </div>
                                <div class="action-arrow">→</div>
                            </a>
                            <a href="../library_catalog.php" class="action-card">
                                <div class="action-icon-block action-icon-library">
                                    <i class="fa-solid fa-book-open"></i>
                                </div>
                                <div class="action-content">
                                    <h3>Digital Catalog</h3>
                                    <p>Manage e-resources and publications.</p>
                                </div>
                                <div class="action-arrow">→</div>
                            </a>
                            <a href="../library_inventory.php" class="action-card">
                                <div class="action-icon-block action-icon-box">
                                    <i class="fa-solid fa-boxes-stacked"></i>
                                </div>
                                <div class="action-content">
                                    <h3>Book Inventory</h3>
                                    <p>Update stock and availability across the library.</p>
                                </div>
                                <div class="action-arrow">→</div>
                            </a>
                            <a href="../library_reports.php" class="action-card">
                                <div class="action-icon-block action-icon-report">
                                    <i class="fa-solid fa-chart-line"></i>
                                </div>
                                <div class="action-content">
                                    <h3>Analytics & Reports</h3>
                                    <p>Inspect circulation and usage trends in detail.</p>
                                </div>
                                <div class="action-arrow">→</div>
                            </a>
                            <a href="../library_qr.php" class="action-card">
                                <div class="action-icon-block action-icon-link">
                                    <i class="fa-solid fa-link"></i>
                                </div>
                                <div class="action-content">
                                    <h3>Generate QR Code</h3>
                                    <p>Create QR codes for library access and tracking.</p>
                                </div>
                                <div class="action-arrow">→</div>
                            </a>
                            <a href="../library_settings.php" class="action-card">
                                <div class="action-icon-block action-icon-cog">
                                    <i class="fa-solid fa-gear"></i>
                                </div>
                                <div class="action-content">
                                    <h3>System Settings</h3>
                                    <p>Customize library workflows and permission settings.</p>
                                </div>
                                <div class="action-arrow">→</div>
                            </a>
                        </div>
                    </section>

                    <!-- Charts and Analytics -->
                    <!-- Weekly Activity Chart -->
                    <section class="dashboard-section dashboard-section--fullwidth">
                        <h2>Weekly Check-in Trends</h2>
                        <div class="chart-container">
                            <canvas id="weeklyActivityChart" width="400" height="250"></canvas>
                        </div>
                    </section>

                    <!-- Activity Feeds -->
                    <div class="dashboard-grid">
                        <!-- Top Books This Month -->
                        <?php if (!empty($topBooks)): ?>
                        <section class="dashboard-section activity-card">
                            <div class="activity-header">
                                <div>
                                    <h2>Top Books This Month</h2>
                                    <p class="activity-subtitle">Most borrowed titles</p>
                                </div>
                                <div class="trending-icon">
                                    <i class="fa-solid fa-fire"></i>
                                </div>
                            </div>
                            <div class="top-books-list">
                                <?php
                                $maxBorrows = max(array_column($topBooks, 'borrow_count'));
                                foreach (array_slice($topBooks, 0, 5) as $index => $book):
                                ?>
                                    <div class="top-book-item">
                                        <div class="book-rank">
                                            #<?= $index + 1 ?>
                                        </div>
                                        <div class="book-content">
                                            <h4 class="book-title-serif"><?= htmlspecialchars($book['title']) ?></h4>
                                            <p class="book-author"><?= htmlspecialchars($book['author'] ?? 'Unknown Author') ?></p>
                                        </div>
                                        <div class="book-metrics">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $maxBorrows > 0 ? ($book['borrow_count'] / $maxBorrows) * 100 : 0 ?>%"></div>
                                            </div>
                                            <span class="borrow-count"><?= htmlspecialchars($book['borrow_count']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endif; ?>

                        <!-- Recent Check-ins -->
                        <section class="dashboard-section activity-card">
                            <div class="activity-header">
                                <div>
                                    <h2>Recent Check-ins</h2>
                                    <p class="activity-subtitle">Latest library entries</p>
                                </div>
                                <a href="#" class="view-all-link" onclick="openCheckinsModal()">View all <i class="fa-solid fa-arrow-right"></i></a>
                            </div>
                            <div class="activity-list">
                                <?php if (empty($recentEntries)): ?>
                                    <div class="activity-item empty">
                                        <p>No recent check-ins</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentEntries as $entry): ?>
                                        <div class="activity-item">
                                            <div class="user-avatar">
                                                <?php
                                                $nameParts = explode(' ', $entry['name']);
                                                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                                                ?>
                                                <span class="avatar-initials"><?= htmlspecialchars($initials) ?></span>
                                            </div>
                                            <div class="activity-content">
                                                <h4 class="user-name"><?= htmlspecialchars($entry['name']) ?></h4>
                                                <p class="user-details">
                                                    ID: <?= htmlspecialchars($entry['student_number'] ?? 'N/A') ?> · <?= htmlspecialchars($entry['course_department'] ?? 'N/A') ?>
                                                </p>
                                            </div>
                                            <div class="activity-time">
                                                <?= htmlspecialchars(date('H:i A', strtotime($entry['time_in']))) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>

                    <!-- Recent Borrowing Activity - Full Width -->
                    <section class="dashboard-section activity-card dashboard-section--fullwidth">
                        <div class="activity-header">
                            <div>
                                <h2>Recent Borrowing Activity</h2>
                                <p class="activity-subtitle">Books moving through the library</p>
                            </div>
                            <a href="#" class="view-all-link" onclick="openBorrowsModal()">View all <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                        <div class="activity-list">
                            <?php if (empty($recentBorrows)): ?>
                                <div class="activity-item empty">
                                    <p>No recent borrowing activity</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentBorrows as $borrow): ?>
                                    <div class="activity-item">
                                        <div class="book-icon">
                                            <i class="fa-solid fa-book"></i>
                                        </div>
                                        <div class="activity-content">
                                            <h4 class="book-title"><?= htmlspecialchars($borrow['title']) ?></h4>
                                            <p class="book-details">
                                                <?= htmlspecialchars($borrow['borrower']) ?> · Due: <?= htmlspecialchars(date('M j, Y', strtotime($borrow['due_date']))) ?>
                                            </p>
                                        </div>
                                        <div class="status-badge status-<?= strtolower($borrow['status']) ?>">
                                            <?= htmlspecialchars($borrow['status']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php if ($pendingApprovals > 0 || $overdueItems > 0 || count($pendingVisits) > 0): ?>
                    <section class="dashboard-section">
                        <h2>⚠️ Attention Required</h2>
                        <div class="alerts-grid">
                            <?php if ($pendingApprovals > 0): ?>
                            <div class="alert-card warning">
                                <div class="alert-icon">📋</div>
                                <div class="alert-content">
                                    <h4>Pending Resource Approvals</h4>
                                    <p><?= htmlspecialchars($pendingApprovals) ?> e-resources waiting for approval</p>
                                    <a href="../library_catalog.php" class="alert-action">Review Now →</a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($overdueItems > 0): ?>
                            <div class="alert-card error">
                                <div class="alert-icon">⏰</div>
                                <div class="alert-content">
                                    <h4>Overdue Items</h4>
                                    <p><?= htmlspecialchars($overdueItems) ?> items are past due date</p>
                                    <a href="../library_inventory.php" class="alert-action">Check Status →</a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (count($pendingVisits) > 0): ?>
                            <div class="alert-card info">
                                <div class="alert-icon">👤</div>
                                <div class="alert-content">
                                    <h4>Pending Visit Approvals</h4>
                                    <p><?= htmlspecialchars(count($pendingVisits)) ?> visitors waiting for approval</p>
                                    <a href="../library_checkin.php" class="alert-action">Process Now →</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Library QR Code -->
                    <section class="dashboard-section">
                        <div class="section-header">
                            <h2>Library Entry QR Code</h2>
                            <p class="section-subtitle">Entry Point Access</p>
                        </div>
                        <div class="qr-card">
                            <div class="qr-display">
                                <div class="qr-container">
                                    <img src="https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=<?= urlencode($qrLink) ?>&choe=UTF-8"
                                         alt="Library entry QR code"
                                         class="qr-image" />
                                </div>
                            </div>
                            <div class="qr-metadata">
                                <p class="qr-description">Scan this QR code at the library entrance to access the check-in system. Print or save this code for visitor use.</p>
                                <div class="metadata-grid">
                                    <div class="metadata-row">
                                        <span class="metadata-label">Target URL</span>
                                        <span class="metadata-value">
                                            <a href="<?= htmlspecialchars($qrLink) ?>" target="_blank">Library Entry Form</a>
                                        </span>
                                    </div>
                                    <div class="metadata-row">
                                        <span class="metadata-label">Generated</span>
                                        <span class="metadata-value"><?= date('F j, Y \a\t H:i') ?></span>
                                    </div>
                                </div>
                                <div class="qr-actions">
                                    <button onclick="window.print()" class="btn-primary">
                                        <i class="fa-solid fa-print"></i> Print
                                    </button>
                                    <button onclick="downloadQR()" class="btn-secondary">
                                        <i class="fa-solid fa-download"></i> Download
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>


                    <!-- Library Policies Summary -->
                    <section class="dashboard-section">
                        <h2>📋 Library Policies</h2>
                        <div class="policies-grid">
                            <div class="policy-card">
                                <div class="policy-icon">
                                    <i class="fa-solid fa-mobile-screen-button"></i>
                                </div>
                                <h4>Check-in Policy</h4>
                                <p>QR scan required before accessing library resources</p>
                            </div>
                            <div class="policy-card">
                                <div class="policy-icon">
                                    <i class="fa-solid fa-calendar-days"></i>
                                </div>
                                <h4>Reservation Limit</h4>
                                <p>Maximum 2 active reservations per user</p>
                            </div>
                            <div class="policy-card">
                                <div class="policy-icon">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                                <h4>Due Dates</h4>
                                <p>3 valid school days, skipping weekends and holidays</p>
                            </div>
                            <div class="policy-card">
                                <div class="policy-icon">
                                    <i class="fa-solid fa-peso-sign"></i>
                                </div>
                                <h4>Fine Policy</h4>
                                <p>₱25 per valid school day overdue</p>
                            </div>
                        </div>
                    </section>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Mobile Menu Toggle (for small screens)
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sideNav = document.querySelector('.side-nav');
            const pageOverlay = document.getElementById('pageOverlay');

            if (mobileMenuToggle && sideNav && pageOverlay) {
                mobileMenuToggle.addEventListener('click', function() {
                    sideNav.classList.toggle('mobile-open');
                    pageOverlay.classList.toggle('active');
                });

                pageOverlay.addEventListener('click', function() {
                    sideNav.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });

                const navLinks = sideNav.querySelectorAll('a, .nav-toggle');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (!this.classList.contains('nav-toggle')) {
                            sideNav.classList.remove('mobile-open');
                            pageOverlay.classList.remove('active');
                        }
                    });
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sideNav.classList.contains('mobile-open')) {
                        sideNav.classList.remove('mobile-open');
                        pageOverlay.classList.remove('active');
                    }
                });
            }
        });
        document.addEventListener('DOMContentLoaded', function () {
            // Weekly Activity Chart
            const weeklyCtx = document.getElementById('weeklyActivityChart');
            if (weeklyCtx) {
                const weeklyData = <?= json_encode($weeklyStats) ?>;
                const labels = weeklyData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
                });
                const data = weeklyData.map(item => parseInt(item.checkins));

                new Chart(weeklyCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Daily Check-ins',
                            data: data,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#2563eb',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    precision: 0
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            }

            // Auto-refresh functionality (optional)
            setInterval(function() {
                // Could add auto-refresh logic here if needed
                console.log('Dashboard active -', new Date().toLocaleTimeString());
            }, 60000); // Check every minute
        });

        // QR Code download function
        function downloadQR() {
            const link = document.createElement('a');
            link.download = 'library-qr-code.png';
            link.href = document.querySelector('.qr-image').src;
            link.click();
        }

        // Add some interactive enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to metric cards
            const metricCards = document.querySelectorAll('.metric-card');
            metricCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
                });
            });

            // Add click tracking for action cards
            const actionCards = document.querySelectorAll('.action-card');
            actionCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Could add analytics tracking here
                    console.log('Action clicked:', this.querySelector('h3').textContent);
                });
            });
        });
    </script>

    <!-- Modals for View All -->
    <!-- Check-ins Modal -->
    <div id="checkinsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Recent Check-ins</h2>
                <span class="modal-close" onclick="closeCheckinsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="activity-list">
                    <?php if (empty($allEntries)): ?>
                        <div class="activity-item empty">
                            <p>No check-ins found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allEntries as $entry): ?>
                            <div class="activity-item">
                                <div class="user-avatar">
                                    <?php
                                    $nameParts = explode(' ', $entry['name']);
                                    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                                    ?>
                                    <span class="avatar-initials"><?= htmlspecialchars($initials) ?></span>
                                </div>
                                <div class="activity-content">
                                    <h4 class="user-name"><?= htmlspecialchars($entry['name']) ?></h4>
                                    <p class="user-details">
                                        ID: <?= htmlspecialchars($entry['student_number'] ?? 'N/A') ?> · <?= htmlspecialchars($entry['course_department'] ?? 'N/A') ?>
                                    </p>
                                </div>
                                <div class="activity-time">
                                    <?= htmlspecialchars(date('M j, H:i A', strtotime($entry['time_in']))) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Borrows Modal -->
    <div id="borrowsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Recent Borrowing Activity</h2>
                <span class="modal-close" onclick="closeBorrowsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="activity-list">
                    <?php if (empty($allBorrows)): ?>
                        <div class="activity-item empty">
                            <p>No borrowing activity found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allBorrows as $borrow): ?>
                            <div class="activity-item">
                                <div class="book-icon">
                                    <i class="fa-solid fa-book"></i>
                                </div>
                                <div class="activity-content">
                                    <h4 class="book-title"><?= htmlspecialchars($borrow['title']) ?></h4>
                                    <p class="book-details">
                                        <?= htmlspecialchars($borrow['borrower']) ?> · Due: <?= htmlspecialchars(date('M j, Y', strtotime($borrow['due_date']))) ?>
                                    </p>
                                </div>
                                <div class="status-badge status-<?= strtolower($borrow['status']) ?>">
                                    <?= htmlspecialchars($borrow['status']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openCheckinsModal() {
            document.getElementById('checkinsModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeCheckinsModal() {
            document.getElementById('checkinsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openBorrowsModal() {
            document.getElementById('borrowsModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeBorrowsModal() {
            document.getElementById('borrowsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const checkinsModal = document.getElementById('checkinsModal');
            const borrowsModal = document.getElementById('borrowsModal');
            if (event.target == checkinsModal) {
                closeCheckinsModal();
            }
            if (event.target == borrowsModal) {
                closeBorrowsModal();
            }
        }

        // PWA Installation Handler
        let deferredPrompt;
        const installAppBtn = document.getElementById('installAppBtn');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (installAppBtn) {
                installAppBtn.style.display = 'inline-flex';
            }
        });

        if (installAppBtn) {
            installAppBtn.addEventListener('click', async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    console.log(`User response to install app: ${outcome}`);
                    deferredPrompt = null;
                    installAppBtn.style.display = 'none';
                } else {
                    alert('App installation is not available on this device or browser.');
                }
            });
        }

        window.addEventListener('appinstalled', () => {
            console.log('PASS Support System installed as an app');
            if (installAppBtn) {
                installAppBtn.style.display = 'none';
            }
        });
    </script>

    <script src="../assets/js/app.js" defer></script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function isScreenMobile() {
                return window.innerWidth >= 320 && window.innerWidth <= 768;
            }
            let touchStartY = 0;
            const SWIPE_THRESHOLD = 100;
            const SWIPE_START_LIMIT = 100;
            document.addEventListener('touchstart', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.touches.length !== 1) return;
                touchStartY = e.touches[0].clientY;
            }, { passive: true });
            document.addEventListener('touchend', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.changedTouches.length !== 1) return;
                const touchEndY = e.changedTouches[0].clientY;
                const distance = touchEndY - touchStartY;
                if (touchStartY <= SWIPE_START_LIMIT && distance >= SWIPE_THRESHOLD) {
                    const spinner = document.getElementById('swipe-refresh-spinner');
                    if (spinner) {
                        spinner.style.display = 'flex';
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                }
            }, { passive: true });
        });
    </script>
</body>
</html>
