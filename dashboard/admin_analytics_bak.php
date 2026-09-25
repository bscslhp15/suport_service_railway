<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}

$pdo = get_db();
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$roleCounts = [];
foreach (['student', 'teacher', 'admin'] as $roleName) {
    $roleStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :role");
    $roleStmt->execute([':role' => $roleName]);
    $roleCounts[$roleName] = (int) $roleStmt->fetchColumn();
}
$roleCounts['other'] = max(0, $totalUsers - array_sum($roleCounts));
$services = [
    'library' => 'Library',
    'clinic' => 'Clinic',
    'ssc_scholarship' => 'SSC / Scholarship',
    'guidance' => 'Guidance',
];
$headCounts = [];
foreach ($services as $serviceKey => $serviceLabel) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND head_service = :service");
    $countStmt->execute([':service' => $serviceKey]);
    $headCounts[$serviceKey] = (int) $countStmt->fetchColumn();
}
$pendingApprovals = (int) $pdo->query("SELECT COUNT(*) FROM pending_student_registrations WHERE status = 'pending_admin'")->fetchColumn();
$newUserCount30 = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$newPendingCount30 = (int) $pdo->query("SELECT COUNT(*) FROM pending_student_registrations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = in_array($currentPage, ['student_promotion.php', 'graduation_events.php', 'alumni_management.php', 'reports_analytics.php'], true);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | Admin | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
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
        /* Smooth scrolling for anchor links */
        html {
            scroll-behavior: smooth;
        }

    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="admin_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-user-shield"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="admin_assignments.php" data-tooltip="Head Assignments">
                    <span class="nav-icon"><i class="fa-solid fa-user-tie"></i></span>
                    <span class="nav-text">Head Assignments</span>
                </a>
                <a href="admin_users.php" data-tooltip="Users">
                    <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                    <span class="nav-text">Users</span>
                </a>
                <a href="admin_analytics.php" class="active" data-tooltip="Analytics">
                    <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                    <span class="nav-text">Analytics</span>
                </a>
                <a href="admin_calendar_manager.php" data-tooltip="School Calendar">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                    <span class="nav-text">School Calendar</span>
                </a>
                <a href="admin_settings.php" data-tooltip="Settings">
                    <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                    <span class="nav-text">Settings</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $ssaaOpen ? 'true' : 'false' ?>" data-tooltip="SSAA Management">
                        <span class="nav-icon"><i class="fa-solid fa-building-columns"></i></span>
                        <span class="nav-text">SSAA Management</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $ssaaOpen ? ' open' : '' ?>" aria-hidden="<?= $ssaaOpen ? 'false' : 'true' ?>">
                        <a href="student_promotion.php" data-tooltip="Student Promotion">
                            <span class="nav-icon"><i class="fa-solid fa-arrow-up"></i></span>
                            <span class="nav-text">Student Promotion</span>
                        </a>
                        <a href="alumni_management.php" data-tooltip="Alumni Management">
                            <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                            <span class="nav-text">Alumni Management</span>
                        </a>
                        <a href="reports_analytics.php" data-tooltip="Reports & Analytics">
                            <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                            <span class="nav-text">Reports & Analytics</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open mobile menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Admin Portal</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Admin</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <!-- Profile button removed per request -->
                    <!-- support icon removed per UI update -->

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest system alerts</span>
                        </div>
                        <div class="menu-empty">No new notifications.</div>
                        <a class="menu-link menu-footer-link" href="admin_analytics.php">View system analytics</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="admin_assignments.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">Manage admin assignments</span>
                        </div>
                        <a href="admin_users.php" class="menu-action">View users</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="admin_settings.php">System settings</a>
                            <span class="menu-subtext">Configure system policies and access.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="../about.php">Help center</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="page-overlay" id="pageOverlay"></div>
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow" style="color: #000000;">Admin Analytics</p>
                            <h1 style="color: #000000;">Service Health & User Metrics</h1>
                            <p style="color: #000000;">Monitor user registrations, pending approvals, and service head coverage from one central admin screen.</p>
                        </div>
                        <div class="dashboard-summary">
                            <div>
                                <span>Total Users</span>
                                <strong><?= htmlspecialchars($totalUsers) ?></strong>
                            </div>
                            <div>
                                <span>Pending Approvals</span>
                                <strong><?= htmlspecialchars($pendingApprovals) ?></strong>
                            </div>
                            <div>
                                <span>New Users (30d)</span>
                                <strong><?= htmlspecialchars($newUserCount30) ?></strong>
                            </div>
                            <div>
                                <span>New Pending Apps (30d)</span>
                                <strong><?= htmlspecialchars($newPendingCount30) ?></strong>
                            </div>
                        </div>
                    </section>
                    <section class="analytics-grid">
                        <div class="analytics-card">
                            <h3>Role Distribution</h3>
                            <div class="analytics-key">
                                <span><strong><?= htmlspecialchars($roleCounts['student']) ?></strong> Students</span>
                                <span><strong><?= htmlspecialchars($roleCounts['teacher']) ?></strong> Teachers</span>
                                <span><strong><?= htmlspecialchars($roleCounts['admin']) ?></strong> Admins</span>
                            </div>
                            <div class="role-bars">
                                <?php foreach ($roleCounts as $role => $count): ?>
                                    <?php $width = $totalUsers ? round($count / $totalUsers * 100) : 0; ?>
                                    <div class="role-row">
                                        <span class="role-label"><?= htmlspecialchars(ucfirst($role)) ?></span>
                                        <div class="role-track">
                                            <div class="role-fill role-fill-<?= htmlspecialchars($role) ?>" style="width: <?= $width ?>%;"></div>
                                        </div>
                                        <span class="role-value"><?= htmlspecialchars($count) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <h3>Head Coverage</h3>
                            <div class="head-coverage-note">How many assigned heads exist for each service.</div>
                            <?php foreach ($headCounts as $serviceKey => $count): ?>
                                <div class="coverage-row">
                                    <strong><?= htmlspecialchars($services[$serviceKey]) ?></strong>
                                    <span><?= htmlspecialchars($count) ?> assigned</span>
                                </div>
                                <div class="coverage-track">
                                    <div class="coverage-fill" style="width: <?= min(100, $count * 24) ?>%;"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const pageOverlay = document.getElementById('pageOverlay');
        const sideNav = document.querySelector('.side-nav');
        if (!mobileToggle || !pageOverlay || !sideNav) return;
        function openMobileNav(){ sideNav.classList.add('mobile-open'); pageOverlay.classList.add('active'); }
        function closeMobileNav(){ sideNav.classList.remove('mobile-open'); pageOverlay.classList.remove('active'); }
        mobileToggle.addEventListener('click', function(){ sideNav.classList.contains('mobile-open') ? closeMobileNav() : openMobileNav(); });
        pageOverlay.addEventListener('click', closeMobileNav);
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMobileNav(); });
    });
    </script>
    <script src="../assets/js/app.js" defer></script>
    <script>window.addEventListener('DOMContentLoaded', function(){ window.dispatchEvent(new Event('resize')); });</script>
    <script>
        // Expandable navigation functionality
    (function(){ const sd=document.getElementById('sidebarToggle'); const side=document.querySelector('.side-nav'); if(sd){ sd.addEventListener('click', ()=>{ setTimeout(()=>window.dispatchEvent(new Event('resize')),60); }); } if(side){ side.addEventListener('mouseenter', ()=>window.dispatchEvent(new Event('resize'))); side.addEventListener('mouseleave', ()=>window.dispatchEvent(new Event('resize'))); } })();
        const navToggle = document.querySelector('.nav-toggle');
        const submenu = document.querySelector('.submenu');
        const toggleArrow = document.querySelector('.toggle-arrow');

        if (navToggle && submenu && toggleArrow) {
            // Initial state is set by PHP
            const initialExpanded = navToggle.getAttribute('aria-expanded') === 'true';
            if (initialExpanded) {
                submenu.classList.add('open');
                submenu.style.display = 'grid';
                toggleArrow.style.transform = 'rotate(180deg)';
            } else {
                submenu.classList.remove('open');
                submenu.style.display = 'none';
                toggleArrow.style.transform = 'rotate(0deg)';
            }

            navToggle.addEventListener('click', function() {
                const isExpanded = navToggle.getAttribute('aria-expanded') === 'true';

                navToggle.setAttribute('aria-expanded', !isExpanded);
                submenu.setAttribute('aria-hidden', isExpanded);

                if (isExpanded) {
                    submenu.classList.remove('open');
                    submenu.style.display = 'none';
                    toggleArrow.style.transform = 'rotate(0deg)';
                } else {
                    submenu.classList.add('open');
                    submenu.style.display = 'grid';
                    toggleArrow.style.transform = 'rotate(180deg)';
                }
            });
        }

        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sideNav = document.querySelector('.side-nav');

        if (sidebarToggle && sideNav) {
            sidebarToggle.addEventListener('click', function() {
                sideNav.classList.toggle('collapsed');
            });
        }
    </script>
</body>
</html>
