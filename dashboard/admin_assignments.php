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
    <title>Head Assignments | Admin | PASS Support System</title>
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

        .case-page-header {
            position: relative;
            min-height: 245px;
            display: flex;
            align-items: center;
            overflow: hidden;
            margin: 0 0 24px;
            padding: 42px 48px 30px;
            background: #f8f1e7;
            border: 0;
            border-radius: 0 0 24px 24px;
        }

        .case-page-header::before {
            content: '';
            position: absolute;
            z-index: 0;
            inset: 0 auto 0 0;
            width: 3px;
            background: #861b17;
        }

        .case-page-header > div:first-child {
            position: relative;
            z-index: 2;
            width: 65%;
        }

        .case-page-header .eyebrow {
            margin: 0;
            color: #861b17 !important;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 3px;
            line-height: 1;
            text-transform: uppercase;
        }

        .case-page-header h1 {
            margin: 14px 0 12px;
            color: #111 !important;
            font-family: Georgia, serif;
            font-size: clamp(36px, 4vw, 62px);
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.05;
        }

        .case-page-header .dashboard-subtitle {
            max-width: 760px;
            margin: 0;
            color: #34302d !important;
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
        }

        .case-header__art {
            position: absolute;
            z-index: 1;
            top: 0;
            right: 0;
            width: 43%;
            height: 100%;
            color: #d7a58d;
            opacity: .78;
        }

        .case-header__art::before {
            content: '';
            position: absolute;
            right: 13%;
            bottom: 2%;
            width: 82%;
            height: 30%;
            background: radial-gradient(ellipse at center, rgba(235, 205, 167, .48) 0 42%, transparent 43%);
            border-radius: 50%;
        }

        .case-header__art::after {
            content: '';
            position: absolute;
            top: 23%;
            left: 4%;
            width: 76%;
            height: 42%;
            border: 1px solid rgba(215, 165, 141, .45);
            border-left-color: transparent;
            border-radius: 50%;
            transform: rotate(-13deg);
        }

        .case-header__art i {
            position: absolute;
            z-index: 2;
            font-family: 'Font Awesome 6 Free';
            font-size: 28px;
            font-style: normal;
            font-weight: 900;
        }

        .case-header__art .art-chat {
            top: 57%;
            left: 2%;
            padding: 12px 14px;
            border: 2px solid rgba(215, 165, 141, .7);
            border-radius: 9px;
            font-size: 15px;
        }

        .case-header__art .art-chat::after {
            content: '';
            position: absolute;
            right: 12px;
            bottom: -9px;
            width: 14px;
            height: 14px;
            border-right: 2px solid rgba(215, 165, 141, .7);
            border-bottom: 2px solid rgba(215, 165, 141, .7);
            background: #f8f1e7;
            transform: rotate(45deg);
        }

        .case-header__art .art-file {
            top: 17%;
            left: 39%;
            padding: 16px 19px;
            border: 2px solid rgba(215, 165, 141, .58);
            border-radius: 9px;
            box-shadow: 20px 10px 0 -1px #f8f1e7, 20px 10px 0 1px rgba(215, 165, 141, .48), 36px 20px 0 -1px #f8f1e7, 36px 20px 0 1px rgba(215, 165, 141, .42);
            font-size: 35px;
        }

        .case-header__art .art-card {
            top: 35%;
            left: 47%;
            padding: 13px 16px;
            border: 2px solid rgba(215, 165, 141, .65);
            border-radius: 8px;
            background: rgba(248, 241, 231, .7);
            font-size: 28px;
        }

        .case-header__art .art-check {
            top: 22%;
            right: 7%;
            padding: 12px;
            border: 2px solid #e3b968;
            border-radius: 50%;
            color: #e3b968;
            font-size: 21px;
        }

        .case-header__art .art-pin {
            bottom: 10%;
            left: 42%;
            width: 31px;
            height: 31px;
            color: transparent;
            background: #cf9589;
            border-radius: 50% 50% 50% 0;
            font-size: 0;
            transform: rotate(-45deg);
        }

        .case-header__art .art-pin::after {
            content: '';
            position: absolute;
            top: 9px;
            left: 9px;
            width: 13px;
            height: 13px;
            background: #f8f1e7;
            border-radius: 50%;
        }

        .case-header__art .art-dots {
            right: 25%;
            bottom: 12%;
            color: #d7a58d;
            font-size: 28px;
        }

        .admin-hero-art {
            opacity: 1;
        }

        .admin-hero-art .art-check {
            border-color: #d4af37;
            color: #d4af37;
            background: rgba(244, 234, 210, 0.8);
        }

        .admin-hero-art .art-file,
        .admin-hero-art .art-card,
        .admin-hero-art .art-chat {
            color: #a95b45;
            border-color: rgba(169, 91, 69, 0.6);
        }

        .admin-hero-art .art-pin {
            background: #d9a88d;
        }

        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                overscroll-behavior-y: none;
            }

            .case-page-header {
                min-height: 245px;
                padding: 32px 24px 110px;
            }

            .case-page-header::before {
                width: 3px;
            }

            .case-page-header > div:first-child {
                width: 100%;
            }

            .case-page-header h1 {
                font-size: 36px;
            }

            .case-header__art {
                width: 55%;
                opacity: .35;
            }
        }

        @keyframes spin-refresh {
            to {
                transform: rotate(360deg);
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
                <a href="admin_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-user-shield"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="admin_assignments.php" class="active" data-tooltip="Head Assignments">
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
                    <section class="dashboard-intro case-page-header">
                        <div>
                            <p class="eyebrow">Head Assignment Management</p>
                            <h1>Service Head Assignments</h1>
                            <p class="dashboard-subtitle">Control which teachers are assigned as service heads and keep admin oversight organized across all campus services.</p>
                        </div>
                        <div class="case-header__art admin-hero-art" aria-hidden="true">
                            <i class="fa-solid fa-file-lines art-file"></i>
                            <i class="fa-solid fa-user-tie art-card"></i>
                            <i class="fa-solid fa-clipboard-check art-chat"></i>
                            <i class="fa-solid fa-check art-check"></i>
                            <i class="fa-solid fa-location-dot art-pin"></i>
                            <i class="fa-solid fa-leaf art-dots"></i>
                        </div>
                    </section>

                    <div class="dashboard-summary-grid">
                        <div class="metric-card">
                            <h3>Teachers</h3>
                            <div class="value"><?= htmlspecialchars(count($teachers)) ?></div>
                        </div>
                        <div class="metric-card">
                            <h3>Assigned Heads</h3>
                            <div class="value"><?= htmlspecialchars(array_sum(array_map('count', $assignments))) ?></div>
                        </div>
                        <div class="metric-card">
                            <h3>Services</h3>
                            <div class="value"><?= htmlspecialchars(count($services)) ?></div>
                        </div>
                    </div>
                    <section class="content-panel card-panel assignment-panel">
                        <div class="panel-header">
                            <div>
                                <p class="eyebrow">Active Assignment Panel</p>
                                <h2>Assign Service Heads</h2>
                                <p>Use this page to assign teachers to service head roles. Each teacher can only head one service at a time.</p>
                            </div>
                            <div class="panel-actions">
                                <span class="status-chip">Admin-only</span>
                                <span class="status-chip">One service per teacher</span>
                            </div>
                        </div>
                        <?php if (!empty($success)): ?>
                            <div class="notification success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($errors)): ?>
                            <div class="notification error">
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="admin_assignments.php">
                            <div class="current-assignments">
                                <h3>Current assignments</h3>
                                <ul>
                                    <?php foreach ($services as $serviceKey => $serviceLabel): ?>
                                        <li>
                                            <strong><?= htmlspecialchars($serviceLabel) ?>:</strong>
                                            <?php
                                                $assignedNames = [];
                                                foreach ($assignments[$serviceKey] as $assignedId) {
                                                    if (isset($teachersById[$assignedId])) {
                                                        $assignedNames[] = htmlspecialchars($teachersById[$assignedId]['full_name']);
                                                    }
                                                }
                                            ?>
                                            <?php if (empty($assignedNames)): ?>
                                                <span class="assignment-pill">None assigned</span>
                                            <?php else: ?>
                                                <?php foreach ($assignedNames as $name): ?>
                                                    <span class="assignment-pill"><?= $name ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="assignment-grid">
                                <?php foreach ($services as $serviceKey => $serviceLabel): ?>
                                    <fieldset class="assignment-fieldset">
                                        <legend><?= htmlspecialchars($serviceLabel) ?></legend>
                                        <p class="assignment-note">Choose one or more teachers for <?= htmlspecialchars($serviceLabel) ?> head access.</p>
                                        <?php if (empty($teachers)): ?>
                                            <p class="muted">No teacher accounts are available.</p>
                                        <?php else: ?>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <label class="checkbox-row">
                                                    <input type="checkbox" name="service_assignments[<?= htmlspecialchars($serviceKey) ?>][]" value="<?= $teacher['id'] ?>"
                                                        <?= in_array($teacher['id'], $assignments[$serviceKey], true) ? 'checked' : '' ?> />
                                                    <span class="checkbox-label"><?= htmlspecialchars($teacher['full_name']) ?></span>
                                                    <?php if ($teacher['head_service'] !== 'none'): ?>
                                                        <span class="teacher-badge"><?= htmlspecialchars(str_replace('_', ' ', ucwords($teacher['head_service']))) ?></span>
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </fieldset>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" name="update_heads" class="service-button">Save Head Assignments</button>
                        </form>
                    </section>
                </div>
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
    <script>(function(){ const sd=document.getElementById('sidebarToggle'); const side=document.querySelector('.side-nav'); if(sd){ sd.addEventListener('click', ()=>{ setTimeout(()=>window.dispatchEvent(new Event('resize')),60); }); } if(side){ side.addEventListener('mouseenter', ()=>window.dispatchEvent(new Event('resize'))); side.addEventListener('mouseleave', ()=>window.dispatchEvent(new Event('resize'))); } })();</script>
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
