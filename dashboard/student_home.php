<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
process_graduating_students();
$user = current_user();
if ($user['role'] !== 'student') {
    header('Location: ../auth/student_login.php');
    exit;
}

$pdo = get_db();
$studentProgress = null;
$studentStatus = 'Active Student';
if ($user['role'] === 'student') {
    $stmt = $pdo->prepare('SELECT * FROM ssaa_student_progression WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$user['id']]);
    $studentProgress = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($studentProgress) {
        if ($studentProgress['enrollment_status'] === 'graduating') {
            $studentStatus = 'Graduating';
        } elseif ($studentProgress['enrollment_status'] === 'graduated') {
            $studentStatus = 'Graduated';
        }
    }
}

$displayCourse = $user['course'] ?? $user['course_year'] ?? 'Course';
$courseYear = trim($user['course_year'] ?? '');
$displayYearLabel = $user['year_level'] ?? null;
$nextYearLabel = null;
if (empty($displayYearLabel) && $courseYear !== '') {
    $courseParts = explode(' ', $courseYear);
    $lastPart = strtolower(end($courseParts));
    $ordinals = ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];

    if (isset($ordinals[$lastPart])) {
        $displayYearLabel = $ordinals[$lastPart];
        array_pop($courseParts);
        $displayCourse = implode(' ', $courseParts) ?: $displayCourse;
    } elseif ($lastPart === 'graduate') {
        $displayYearLabel = 'Graduate';
        $displayCourse = implode(' ', array_slice($courseParts, 0, -1)) ?: $displayCourse;
    }
}
if ($studentProgress && $studentProgress['enrollment_status'] === 'graduated') {
    $displayYearLabel = null;
    $nextYearLabel = null;

    $courseParts = explode(' ', trim($courseYear));
    if (!empty($courseParts)) {
        $lastPart = strtolower(end($courseParts));
        if (is_numeric($lastPart) || $lastPart === 'graduate') {
            array_pop($courseParts);
        }
        $displayCourse = implode(' ', $courseParts) ?: $displayCourse;
    }

    if (!str_ends_with(strtolower($displayCourse), '(alumni)')) {
        $displayCourse .= ' (alumni)';
    }
}
$displayStatus = $studentStatus;
$topbarInfo = build_user_dashboard_header_info($user);
$calendarBlocks = get_upcoming_library_calendar_blocks(10);

// Get announcements
$studentNotifications = [];
if (function_exists('get_active_clinic_announcements')) {
    $studentNotifications = get_active_clinic_announcements('students');
}

// Get clearance request notifications (approved/rejected)
$clearanceNotifications = [];
$stmt = $pdo->prepare('SELECT id, status, reviewed_at FROM clinic_clearance_requests WHERE user_id = ? AND status IN ("approved", "rejected") ORDER BY reviewed_at DESC LIMIT 5');
$stmt->execute([$user['id']]);
$clearanceNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get fines count
$finesCount = 0;
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM library_fines WHERE user_id = ? AND paid = 0');
$stmt->execute([$user['id']]);
$finesResult = $stmt->fetch(PDO::FETCH_ASSOC);
$finesCount = $finesResult['count'] ?? 0;

// Get counseling request notifications
$counselingNotifications = [];
$stmt = $pdo->prepare('SELECT id, status, created_at FROM guidance_cases WHERE reported_student_id = ? AND status IN ("in_progress", "resolved") ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$user['id']]);
$counselingNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get manual case reports against this student
$manualCaseNotifications = [];
$stmt = $pdo->prepare('SELECT id, report_type, created_at FROM guidance_cases WHERE reported_student_id = ? AND report_type IN ("teacher_report", "student_report") ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$user['id']]);
$manualCaseNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

record_user_activity('Visited student dashboard');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | PASS Support System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="manifest" href="../PWA/manifest.json">
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
            font-family: 'ElephantLocal', 'Playfair Display', serif !important;
            font-weight: 700;
        }

        .student-dashboard .section-caption {
            margin: 28px 0 18px;
            font-size: 30px;
            color: #8f0011;
        }

        .student-dashboard .services-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .student-dashboard .service-card {
            position: relative;
            min-height: 260px;
            padding: 28px;
            border: 1px solid #eadfd8;
            border-radius: 14px;
            background: #fffdfb;
            box-shadow: 0 8px 18px rgba(109, 38, 20, 0.08);
            overflow: hidden;
            display: grid;
            grid-template-columns: 84px minmax(0, 1fr);
            grid-template-rows: auto 1fr auto;
            column-gap: 14px;
            transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        }

        .student-dashboard .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 3px;
            background: #8f0011;
            transition: width 0.28s ease;
        }

        .student-dashboard .service-card:hover {
            transform: scale(1.025);
            border-color: #e3c9b8;
            box-shadow: 0 14px 28px rgba(109, 38, 20, 0.15);
        }

        .student-dashboard .service-card:hover::before {
            width: 100%;
        }

        .student-dashboard .service-card-header {
            display: flex;
            align-items: center;
            grid-column: 1;
            grid-row: 1 / 4;
            min-height: 0;
            width: 84px;
            margin: 0;
            padding: 0;
            background: transparent;
            overflow: visible;
        }

        .student-dashboard .service-card-icon {
            width: 68px;
            height: 68px;
            border-radius: 10px;
            background: linear-gradient(135deg, #8f0011, #b43a2b);
            color: #ffffff;
            font-size: 23px;
        }

        .student-dashboard .service-card-status {
            display: none;
        }

        .student-dashboard .service-card-body {
            grid-column: 2;
            grid-row: 1 / 3;
            gap: 6px;
            min-width: 0;
        }

        .student-dashboard .service-card-body h2 {
            display: block;
            margin: 0;
            padding-bottom: 7px;
            color: #8f0011;
            border-bottom: 2px solid #d5a55d;
            font-size: 24px;
            line-height: 1.2;
        }

        .student-dashboard .service-card-number {
            display: none;
        }

        .student-dashboard .service-card-body p {
            min-height: 0;
            margin: 14px 0 0;
            color: #756d69;
            font-size: 15px;
            line-height: 1.45;
        }

        .student-dashboard .service-card-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            grid-column: 2;
            grid-row: 3;
            margin-top: 9px;
            padding-top: 0;
            border-top: 0;
        }

        .student-dashboard .service-card-meta {
            color: #756d69;
            font-size: 10px;
        }

        .student-dashboard .service-button {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border: 1px solid #c9877b;
            border-radius: 999px;
            background: transparent;
            color: #8f0011;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .student-dashboard .service-button:hover {
            background: #8f0011;
            color: #ffffff;
            transform: none;
        }

        .student-dashboard .service-button i {
            display: none;
        }

        .student-dashboard .service-arrow {
            display: grid;
            place-items: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #f9ece3;
            color: #8f0011;
            font-size: 10px;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .student-dashboard .service-arrow:hover {
            background: #8f0011;
            color: #ffffff;
            transform: translateX(2px);
        }

        @media (max-width: 900px) {
            .student-dashboard .services-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .student-dashboard .services-grid {
                grid-template-columns: 1fr;
            }
        }

        @keyframes spin-refresh {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        /* Swipe-to-Refresh for Mobile (320px to 768px) */
        @media (max-width: 768px) {
            body.student-dashboard.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }
    </style>
</head>
<body class="student-dashboard">
    <!-- Swipe-to-Refresh Spinner (Mobile Only) -->
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;">
        <div style="text-align: center;">
            <div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div>
            <p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p>
        </div>
    </div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p><?= htmlspecialchars($topbarInfo['displayMeta']) ?></p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <!-- search button removed per request -->
            </div>
            <div class="nav-section">
                <a href="student_home.php" class="active" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="school_announcements.php" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_home.php" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="library_dashboard.php" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="clinic_dashboard.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc/ssc_dashboard.php" data-tooltip="Supreme Student Council">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">Supreme Student Council</span>
                        </a>
                        <a href="scholarship/scholarship_dashboard.php" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="ssaa_student_home.php" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
                        </a>
                    </div>
                </div>
                <a href="profile.php" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="about.php" data-tooltip="About">
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
                            <p>Student Portal</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($topbarInfo['displayMeta']) ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Download App" data-tooltip="Download App" id="installAppBtn"><i class="fa-solid fa-download"></i></button>

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest updates</span>
                        </div>
                        <div class="menu-items">
                            <!-- Announcements -->
                            <?php if (!empty($studentNotifications)): ?>
                                <?php foreach ($studentNotifications as $notification): ?>
                                    <div class="menu-item notification-item" role="menuitem">
                                        <p class="notification-title"><i class="fa-solid fa-bullhorn"></i> <?= htmlspecialchars($notification['title']) ?></p>
                                        <p class="notification-meta"><?= htmlspecialchars(date('M j, Y', strtotime($notification['created_at']))) ?> · Announcement</p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Clearance Request Status -->
                            <?php if (!empty($clearanceNotifications)): ?>
                                <?php foreach ($clearanceNotifications as $clearance): ?>
                                    <div class="menu-item notification-item" role="menuitem">
                                        <p class="notification-title">
                                            <i class="fa-solid fa-<?= $clearance['status'] === 'approved' ? 'check-circle' : 'times-circle' ?>"></i> 
                                            Clearance Request <?= ucfirst($clearance['status']) ?>
                                        </p>
                                        <p class="notification-meta"><?= htmlspecialchars(date('M j, Y', strtotime($clearance['reviewed_at']))) ?> · Clinic</p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Fines -->
                            <?php if ($finesCount > 0): ?>
                                <div class="menu-item notification-item" role="menuitem">
                                    <p class="notification-title"><i class="fa-solid fa-exclamation-triangle"></i> You have <?= $finesCount ?> pending fine(s)</p>
                                    <p class="notification-meta">Disciplinary · Check status</p>
                                </div>
                            <?php endif; ?>

                            <!-- Counseling Request Status -->
                            <?php if (!empty($counselingNotifications)): ?>
                                <?php foreach ($counselingNotifications as $counseling): ?>
                                    <div class="menu-item notification-item" role="menuitem">
                                        <p class="notification-title"><i class="fa-solid fa-heart"></i> Counseling session <?= ucfirst($counseling['status']) ?></p>
                                        <p class="notification-meta"><?= htmlspecialchars(date('M j, Y', strtotime($counseling['created_at']))) ?> · Guidance</p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Manual Case Reports -->
                            <?php if (!empty($manualCaseNotifications)): ?>
                                <?php foreach ($manualCaseNotifications as $caseReport): ?>
                                    <div class="menu-item notification-item" role="menuitem">
                                        <p class="notification-title"><i class="fa-solid fa-flag"></i> New case report filed</p>
                                        <p class="notification-meta"><?= htmlspecialchars(date('M j, Y', strtotime($caseReport['created_at']))) ?> · Manual Report</p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (empty($studentNotifications) && empty($clearanceNotifications) && $finesCount === 0 && empty($counselingNotifications) && empty($manualCaseNotifications)): ?>
                                <div class="menu-empty">No new notifications.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="profile.php" class="menu-action">Open profile</a>
                    </div>


                </div>
            </header>
            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                <section class="dashboard-intro">
                    <div class="dashboard-intro-left">
                        <p class="eyebrow"><i class="fa-solid fa-star sparkle-icon"></i> Welcome back</p>
                        <h1>HELLO, <span class="user-name-highlight"><?= htmlspecialchars($user['full_name']) ?></span></h1>
                        <p class="dashboard-subtitle">Available services for your account are listed below. Access counseling,</p>
                        <p class="dashboard-subtitle">library resources, health services, and more to support your academic journey.</p>
                        <a href="#available-services" class="view-services-btn">View Services →</a>
                    </div>
                    <aside class="dashboard-intro-right">
                        <div class="dashboard-detail-panel">
                            <div class="detail-row role-row">
                                <div class="detail-icon">
                                    <i class="fa-solid fa-user-graduate"></i>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-top">Role</div>
                                    <div class="detail-bottom">Student</div>
                                </div>
                            </div>
                            <div class="detail-row course-row">
                                <div class="detail-icon">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-top">Course</div>
                                    <div class="detail-bottom"><?= htmlspecialchars($displayCourse) ?></div>
                                </div>
                            </div>
                            <div class="detail-row year-row">
                                <div class="detail-icon">
                                    <i class="fa-solid fa-calendar-alt"></i>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-top">Year Level</div>
                                    <div class="detail-bottom"><?= htmlspecialchars($displayYearLabel ?? 'Not assigned') ?></div>
                                </div>
                            </div>
                            <?php if (!empty($nextYearLabel) && $displayStatus === 'Active Student'): ?>
                                <div class="detail-row upcoming-row">
                                    <div class="detail-icon">
                                        <i class="fa-solid fa-forward"></i>
                                    </div>
                                    <div class="detail-content">
                                        <div class="detail-top">Upcoming</div>
                                        <div class="detail-bottom"><?= htmlspecialchars($nextYearLabel) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (empty($displayYearLabel) && $displayStatus !== 'Active Student'): ?>
                                <div class="detail-row status-row">
                                    <div class="detail-icon">
                                        <i class="fa-solid fa-info-circle"></i>
                                    </div>
                                    <div class="detail-content">
                                        <div class="detail-top">Status</div>
                                        <div class="detail-bottom"><?= htmlspecialchars($displayStatus) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row id-row">
                                <div class="detail-icon">
                                    <i class="fa-solid fa-id-card"></i>
                                </div>
                                <div class="detail-content">
                                    <div class="detail-top">ID</div>
                                    <div class="detail-bottom"><?= htmlspecialchars($user['student_id'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </section>
            <h2 class="section-caption">Available services</h2>
            <section class="dashboard-grid services-grid">
                <div class="service-card">
                    <div class="service-card-header">
                        <div class="service-card-icon guidance"><i class="fa-solid fa-user-graduate"></i></div>
                        <span class="service-card-status">Open today</span>
                    </div>
                    <div class="service-card-body">
                        <h2><span class="service-card-number">01</span>Guidance</h2>
                        <p>Book counseling sessions and view your guidance status.</p>
                    </div>
                    <div class="service-card-footer">
                        <a class="service-button" href="guidance_home.php">View Service</a><a class="service-arrow" href="guidance_home.php" aria-label="Open Guidance"><i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-card-header">
                        <div class="service-card-icon library"><i class="fa-solid fa-book"></i></div>
                        <span class="service-card-status">24/7 online</span>
                    </div>
                    <div class="service-card-body">
                        <h2><span class="service-card-number">02</span>Library</h2>
                        <p>Access to library resources and materials.</p>
                    </div>
                    <div class="service-card-footer">
                        <a class="service-button" href="library_dashboard.php">View Service</a><a class="service-arrow" href="library_dashboard.php" aria-label="Open Library"><i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-card-header">
                        <div class="service-card-icon clinic"><i class="fa-solid fa-stethoscope"></i></div>
                        <span class="service-card-status">Open today</span>
                    </div>
                    <div class="service-card-body">
                        <h2><span class="service-card-number">03</span>Clinic</h2>
                        <p>Schedule health checks and access medical support services.</p>
                    </div>
                    <div class="service-card-footer">
                        <a class="service-button" href="clinic_dashboard.php">View Service</a><a class="service-arrow" href="clinic_dashboard.php" aria-label="Open Clinic"><i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-card-header">
                        <div class="service-card-icon scholarship"><i class="fa-solid fa-award"></i></div>
                        <span class="service-card-status">Elections soon</span>
                    </div>
                    <div class="service-card-body">
                        <h2><span class="service-card-number">04</span>Supreme Student Council</h2>
                        <p>Submit scholarship and student council requests.</p>
                    </div>
                    <div class="service-card-footer">
                        <a class="service-button" href="ssc/ssc_dashboard.php">View Service</a><a class="service-arrow" href="ssc/ssc_dashboard.php" aria-label="Open Supreme Student Council"><i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-card-header">
                        <div class="service-card-icon funding"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                        <span class="service-card-status">Applications open</span>
                    </div>
                    <div class="service-card-body">
                        <h2><span class="service-card-number">05</span>Scholarship</h2>
                        <p>Track scholarship status and eligibility updates.</p>
                    </div>
                    <div class="service-card-footer">
                        <a class="service-button" href="scholarship/scholarship_dashboard.php">View Service</a><a class="service-arrow" href="scholarship/scholarship_dashboard.php" aria-label="Open Scholarship"><i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-card-header">
                        <div class="service-card-icon ssaa"><i class="fa-solid fa-users"></i></div>
                        <span class="service-card-status">Network</span>
                    </div>
                    <div class="service-card-body">
                        <h2><span class="service-card-number">06</span>Alumni</h2>
                        <p>Manage alumni activities and student affairs information.</p>
                    </div>
                    <div class="service-card-footer">
                        <a class="service-button" href="ssaa_student_home.php">View Service</a><a class="service-arrow" href="ssaa_student_home.php" aria-label="Open Alumni"><i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
            </section>
            </div>
        </main>
        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="footer-card">
                    <h3>About</h3>
                    <p>PASS College is dedicated to supporting student success through integrated academic, guidance, and health services.</p>
                </div>
                <div class="footer-card">
                    <h3>Address</h3>
                    <?php
                    require_once __DIR__ . '/../includes/functions.php';
                    $contacts = json_decode(get_library_setting('footer_contacts', '[]'), true) ?: [];
                    $locations = json_decode(get_library_setting('footer_locations', '[]'), true) ?: [];
                    $social = json_decode(get_library_setting('footer_social', '[]'), true) ?: [];
                    ?>
                    <?php if (!empty($locations)): ?>
                        <?php foreach ($locations as $loc):
                            $lname = htmlspecialchars($loc['name'] ?? $loc[0] ?? 'Location');
                            $laddr = htmlspecialchars($loc['address'] ?? $loc[2] ?? '');
                            $llink = htmlspecialchars($loc['link'] ?? $loc[1] ?? '#');
                        ?>
                            <p style="margin-bottom:8px;"><strong><?= $lname ?></strong><br>
                            <?= $laddr ? $laddr . '<br>' : '' ?>
                            <?php if (!empty($llink) && $llink !== '#'): ?>
                                <a href="<?= $llink ?>" target="_blank" rel="noopener noreferrer">View on map</a>
                            <?php endif; ?>
                            </p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No address / location set.</p>
                    <?php endif; ?>
                </div>
                <div class="footer-card">
                    <h3>Connect</h3>
                    <?php if (!empty($contacts)): ?>
                        <ul style="list-style:none;padding:0;margin:0 0 8px 0;">
                        <?php foreach ($contacts as $c):
                            $label = htmlspecialchars($c['label'] ?? $c[0] ?? '');
                            $value = trim($c['value'] ?? $c[1] ?? '');
                            $escaped = htmlspecialchars($value);
                            $href = '';
                            if (preg_match('#^https?://#i', $value)) {
                                $href = $escaped;
                            } elseif (strpos($value, '@') !== false) {
                                $href = 'mailto:' . $escaped;
                            } elseif (preg_match('/^[+0-9() \-]+$/', $value)) {
                                $href = 'tel:' . preg_replace('/[^0-9+]/', '', $value);
                            }
                        ?>
                            <li style="margin-bottom:6px;"><strong><?= $label ?>:</strong>
                                <?php if ($href): ?>
                                    <a href="<?= $href ?>" target="_blank" rel="noopener noreferrer"><?= $escaped ?></a>
                                <?php else: ?>
                                    <?= $escaped ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No contacts set.</p>
                    <?php endif; ?>

                    <?php if (!empty($social)): ?>
                        <p>
                        <?php
                            $iconMap = [
                                'facebook' => 'fab fa-facebook',
                                'facebook-messenger' => 'fab fa-facebook-messenger',
                                'tiktok' => 'fab fa-tiktok',
                                'x' => 'fab fa-x',
                                'youtube' => 'fab fa-youtube',
                                'instagram' => 'fab fa-instagram',
                                'threads' => 'fab fa-internet-explorer',
                                'whatsapp' => 'fab fa-whatsapp',
                                'telegram' => 'fab fa-telegram',
                                'discord' => 'fab fa-discord',
                                'reddit' => 'fab fa-reddit',
                                'pinterest' => 'fab fa-pinterest',
                                'quora' => 'fab fa-quora',
                                'others' => 'fas fa-link'
                            ];
                            foreach ($social as $i => $s) {
                                $platRaw = strtolower(trim((string)($s['platform'] ?? $s[0] ?? '')));
                                $label = htmlspecialchars($s['platform'] ?? $s[0] ?? 'Link');
                                $link = htmlspecialchars($s['link'] ?? $s[1] ?? '#');
                                $iconClass = $iconMap[$platRaw] ?? 'fas fa-link';
                        ?>
                            <a href="<?= $link ?>" target="_blank" rel="noopener noreferrer"><i class="<?= $iconClass ?>" style="margin-right:6px"></i><?= $label ?></a><?= $i < count($social)-1 ? ' · ' : '' ?>
                        <?php } ?>
                        </p>
                    <?php else: ?>
                        <p>No social links set.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-divider"></div>
            <div class="footer-copyright">© <?= date('Y') ?> PASS College. All rights reserved.</div>
        </footer>
    </div>
    <script src="../PWA/pwa-registration.js" defer></script>
    <script src="../assets/js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Swipe-to-Refresh Functionality (Mobile Only: 320px - 768px)
            const swipeRefreshSpinner = document.getElementById('swipe-refresh-spinner');
            let touchStartY = 0;
            let touchEndY = 0;
            let isRefreshing = false;
            const SWIPE_THRESHOLD = 100; // minimum pixels to swipe
            const SWIPE_START_LIMIT = 100; // only detect swipe from top 100px

            function isScreenMobile() {
                const width = window.innerWidth;
                return width >= 320 && width <= 768;
            }

            function showRefreshSpinner() {
                if (isScreenMobile() && !isRefreshing && swipeRefreshSpinner) {
                    isRefreshing = true;
                    swipeRefreshSpinner.style.display = 'flex';
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                }
            }

            document.addEventListener('touchstart', function(e) {
                if (!isScreenMobile()) return;
                const touch = e.touches[0];
                touchStartY = touch.clientY;
            }, { passive: true });

            document.addEventListener('touchend', function(e) {
                if (!isScreenMobile()) return;
                const touch = e.changedTouches[0];
                touchEndY = touch.clientY;

                // Detect swipe down from top of page
                if (touchStartY <= SWIPE_START_LIMIT && touchEndY - touchStartY >= SWIPE_THRESHOLD) {
                    // Check if we're near the top of the page
                    if (window.scrollY <= 10) {
                        e.preventDefault();
                        showRefreshSpinner();
                    }
                }
            }, { passive: true });

            // Mobile Menu Toggle Functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sideNav = document.querySelector('.side-nav');
            const pageOverlay = document.getElementById('pageOverlay');

            if (mobileMenuToggle && sideNav && pageOverlay) {
                // Toggle menu when hamburger icon is clicked
                mobileMenuToggle.addEventListener('click', function() {
                    sideNav.classList.toggle('mobile-open');
                    pageOverlay.classList.toggle('active');
                });

                // Close menu when overlay is clicked
                pageOverlay.addEventListener('click', function() {
                    sideNav.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });

                // Close menu when any navigation link is clicked
                const navLinks = sideNav.querySelectorAll('a, .nav-toggle');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        // Don't close if clicking toggle buttons for submenus
                        if (!this.classList.contains('nav-toggle')) {
                            sideNav.classList.remove('mobile-open');
                            pageOverlay.classList.remove('active');
                        }
                    });
                });

                // Close menu on ESC key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sideNav.classList.contains('mobile-open')) {
                        sideNav.classList.remove('mobile-open');
                        pageOverlay.classList.remove('active');
                    }
                });
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
                        console.log(`User response to install prompt: ${outcome}`);
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
        });
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
