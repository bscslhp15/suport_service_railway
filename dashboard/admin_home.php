<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}

$services = [
    'library' => 'Library',
    'clinic' => 'Clinic',
    'ssc_scholarship' => 'SSC / Scholarship',
    'guidance' => 'Guidance',
];

$pdo = get_db();
$teachersStmt = $pdo->prepare("SELECT id, full_name, email, head_service FROM users WHERE role = 'teacher' ORDER BY full_name ASC");
$teachersStmt->execute();
$teachers = $teachersStmt->fetchAll();
$teachersById = [];
foreach ($teachers as $teacher) {
    $teachersById[$teacher['id']] = $teacher;
}
$assignments = array_fill_keys(array_keys($services), []);
foreach ($teachers as $teacher) {
    if ($teacher['head_service'] !== 'none' && isset($assignments[$teacher['head_service']])) {
        $assignments[$teacher['head_service']][] = $teacher['id'];
    }
}

$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalTeachers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalHeads = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_head = 1")->fetchColumn();
$roleCounts = [];
foreach (['student', 'teacher', 'admin'] as $roleName) {
    $roleStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :role");
    $roleStmt->execute([':role' => $roleName]);
    $roleCounts[$roleName] = (int) $roleStmt->fetchColumn();
}
$roleCounts['other'] = max(0, $totalUsers - array_sum($roleCounts));
$headCounts = [];
foreach ($services as $serviceKey => $serviceLabel) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND head_service = :service");
    $countStmt->execute([':service' => $serviceKey]);
    $headCounts[$serviceKey] = (int) $countStmt->fetchColumn();
}

$errors = [];
$success = '';

$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = in_array($currentPage, ['student_promotion.php', 'graduation_events.php', 'alumni_management.php', 'reports_analytics.php'], true);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_heads'])) {
    $newAssignments = [];
    $duplicateTeachers = [];

    foreach ($services as $serviceKey => $serviceLabel) {
        $serviceTeachers = $_POST['service_assignments'][$serviceKey] ?? [];
        if (!is_array($serviceTeachers)) {
            continue;
        }

        foreach ($serviceTeachers as $teacherIdRaw) {
            $teacherId = (int) $teacherIdRaw;
            if ($teacherId <= 0) {
                continue;
            }
            if (!isset($teachersById[$teacherId])) {
                $errors[] = "Invalid teacher selected for {$serviceLabel}.";
                continue;
            }
            if (isset($newAssignments[$teacherId]) && $newAssignments[$teacherId] !== $serviceKey) {
                $duplicateTeachers[] = $teachersById[$teacherId]['full_name'];
            }
            $newAssignments[$teacherId] = $serviceKey;
        }
    }

    if (!empty($duplicateTeachers)) {
        $errors[] = 'A teacher may only be assigned to one service at a time: ' . implode(', ', array_unique($duplicateTeachers)) . '.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $resetStmt = $pdo->prepare("UPDATE users SET is_head = 0, head_service = 'none' WHERE role = 'teacher' AND is_head = 1");
            $resetStmt->execute();

            if (!empty($newAssignments)) {
                $updateStmt = $pdo->prepare("UPDATE users SET is_head = 1, head_service = :service WHERE id = :id AND role = 'teacher'");
                foreach ($newAssignments as $teacherId => $serviceKey) {
                    $updateStmt->execute([':service' => $serviceKey, ':id' => $teacherId]);
                }
            }

            $pdo->commit();
            $success = 'Head assignments have been saved successfully.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Unable to save head assignments. Please try again.';
        }

        $teachersStmt->execute();
        $teachers = $teachersStmt->fetchAll();
        $teachersById = [];
        $assignments = array_fill_keys(array_keys($services), []);
        foreach ($teachers as $teacher) {
            $teachersById[$teacher['id']] = $teacher;
            if ($teacher['head_service'] !== 'none' && isset($assignments[$teacher['head_service']])) {
                $assignments[$teacher['head_service']][] = $teacher['id'];
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="manifest" href="../PWA/manifest-admin.json">
    <meta name="theme-color" content="#1a1a1a">
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

        #swipe-refresh-spinner {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.92);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        #swipe-refresh-spinner .spinner-ring {
            width: 60px;
            height: 60px;
            border: 4px solid #e2e8f0;
            border-top-color: #800000;
            border-radius: 50%;
            animation: spin-refresh 1s linear infinite;
            margin: 0 auto 16px;
        }

        #swipe-refresh-spinner .spinner-label {
            color: #666;
            font-size: 14px;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            text-align: center;
        }

        @keyframes spin-refresh {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                overscroll-behavior-y: none;
            }
        }
    </style>
</head>
<body class="swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none;">
        <div style="text-align: center;">
            <div class="spinner-ring"></div>
            <p class="spinner-label">Refreshing...</p>
        </div>
    </div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="admin_home.php" class="active" data-tooltip="Dashboard">
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
                <a href="admin_analytics.php" data-tooltip="Analytics">
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
                <a href="feedback_and_ratings.php" data-tooltip="Feedback">
                    <span class="nav-icon"><i class="fa-solid fa-star"></i></span>
                    <span class="nav-text">Feedback</span>
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
                    <button type="button" class="topbar-icon" aria-label="Download App" data-tooltip="Download App" id="installAppBtn"><i class="fa-solid fa-download"></i></button>

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
                <div class="content-panel">
                    <!-- Welcome Header -->
                    <section class="dashboard-intro dashboard-intro--compact">
                        <div class="dashboard-intro-left">
                            <p class="eyebrow"><i class="fa-solid fa-shield sparkle-icon"></i> Master Admin</p>
                            <p class="dashboard-date" style="color: white;"><strong style="color: white;">Today's Date</strong> <?= date('F j, Y') ?></p>
                            <h1>Welcome back, <span class="user-name-highlight">admin</span>!</h1>
                            <p class="dashboard-subtitle">Manage users, assign service heads, and oversee system-wide operations from your command center.</p>
                        </div>
                        <aside class="dashboard-intro-right">
                            <div class="dashboard-detail-panel">
                                <div class="detail-row role-row">
                                    <div class="detail-icon"><i class="fa-solid fa-user-shield"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">System</div>
                                        <div class="detail-bottom">Admin Portal</div>
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
                                    <div class="detail-icon"><i class="fa-solid fa-sliders"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Dashboard</div>
                                        <div class="detail-bottom">System Control Center</div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </section>
                    <section class="management-overview">
                        <div class="management-card">
                            <h3>System Manager</h3>
                            <p>As the admin, you control the entire PASS Support System. Use this dashboard to assign service heads, review user roles, and keep every service aligned.</p>
                        </div>
                        <div class="management-card">
                            <h3>Current Capacity</h3>
                            <p>System totals are updated live from the user database.</p>
                            <ul class="management-stats">
                                <li><strong><?= htmlspecialchars($totalUsers) ?></strong> total users</li>
                                <li><strong><?= htmlspecialchars($totalTeachers) ?></strong> teachers</li>
                                <li><strong><?= htmlspecialchars($totalHeads) ?></strong> assigned heads</li>
                            </ul>
                        </div>
                        <div class="management-card">
                            <h3>Next steps</h3>
                            <p>Open the Head Assignments section below to choose who is responsible for each service. Every selected teacher gains access to the matching head dashboard.</p>
                        </div>
                    </section>
                    <section class="dashboard-grid services-grid">
                        <a class="service-card service-link" href="admin_assignments.php">
                            <div class="service-card-top">
                                <div class="service-card-icon scholarship"><i class="fa-solid fa-user-tie"></i></div>
                                <h2>Head Assignments</h2>
                            </div>
                            <p>Assign, review, and adjust service head ownership across the system.</p>
                            <button class="service-button">Manage Heads</button>
                        </a>
                        <a class="service-card service-link" href="admin_users.php">
                            <div class="service-card-top">
                                <div class="service-card-icon ssaa"><i class="fa-solid fa-users"></i></div>
                                <h2>User Directory</h2>
                            </div>
                            <p>Inspect users by role, verify accounts, and confirm current system assignments.</p>
                            <button class="service-button">View Users</button>
                        </a>
                        <a class="service-card service-link" href="admin_analytics.php">
                            <div class="service-card-top">
                                <div class="service-card-icon clinic"><i class="fa-solid fa-chart-line"></i></div>
                                <h2>Analytics</h2>
                            </div>
                            <p>Monitor role distribution, service coverage, and admin oversight metrics.</p>
                            <button class="service-button">Open Analytics</button>
                        </a>
                        <a class="service-card service-link" href="admin_settings.php">
                            <div class="service-card-top">
                                <div class="service-card-icon guidance"><i class="fa-solid fa-gear"></i></div>
                                <h2>System Controls</h2>
                            </div>
                            <p>Centralize admin actions, settings, and policy controls for the PASS platform.</p>
                            <button class="service-button">Open Settings</button>
                        </a>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <div id="notification-container" class="notification-container"></div>

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

    <!-- PWA Installation Handler for Admin Portal -->
    <script>
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
                    console.log(`User response to install admin portal: ${outcome}`);
                    deferredPrompt = null;
                    installAppBtn.style.display = 'none';
                } else {
                    window.location.href = '../auth/admin_login.php';
                }
            });
        }

        window.addEventListener('appinstalled', () => {
            console.log('PASS Support System (Admin) installed as an app');
            if (installAppBtn) {
                installAppBtn.style.display = 'none';
            }
        });
    </script>
    <script src="../assets/js/app.js" defer></script>
    <script src="../PWA/pwa-registration.js" defer></script>
    <script>window.addEventListener('DOMContentLoaded', function(){ window.dispatchEvent(new Event('resize')); });</script>
    <script>(function(){ const sd=document.getElementById('sidebarToggle'); const side=document.querySelector('.side-nav'); if(sd){ sd.addEventListener('click', ()=>{ setTimeout(()=>window.dispatchEvent(new Event('resize')),60); }); } if(side){ side.addEventListener('mouseenter', ()=>window.dispatchEvent(new Event('resize'))); side.addEventListener('mouseleave', ()=>window.dispatchEvent(new Event('resize'))); } })();</script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const swipeRefreshSpinner = document.getElementById('swipe-refresh-spinner');
            if (!swipeRefreshSpinner || window.innerWidth > 768) {
                return;
            }

            let touchStartY = 0;
            let isPulling = false;

            document.addEventListener('touchstart', function (event) {
                if (window.scrollY > 0 || event.touches.length !== 1) {
                    return;
                }
                touchStartY = event.touches[0].clientY;
                isPulling = true;
            }, { passive: true });

            document.addEventListener('touchmove', function (event) {
                if (!isPulling) {
                    return;
                }

                const deltaY = event.touches[0].clientY - touchStartY;
                if (deltaY > 90) {
                    swipeRefreshSpinner.style.display = 'flex';
                    setTimeout(function () {
                        window.location.reload();
                    }, 450);
                    isPulling = false;
                }
            }, { passive: true });

            document.addEventListener('touchend', function () {
                isPulling = false;
            }, { passive: true });
        });
    </script>
</body>
</html>
