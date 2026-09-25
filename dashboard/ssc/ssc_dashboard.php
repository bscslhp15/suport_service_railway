<?php
require_once __DIR__ . '/../../includes/session.php';
require_login();
$user = current_user();
if (!in_array($user['role'], ['student', 'teacher', 'admin'], true)) {
    header('Location: /auth/student_login.php');
    exit;
}

$service = strtolower(trim($_GET['service'] ?? ''));
// Treat teacher head for SSC/Scholarship as combined mode when no explicit service provided
$isHeadSscScholarship = $user['role'] === 'teacher' && (($user['head_service'] ?? '') === 'ssc_scholarship');
if ($service === '' && $isHeadSscScholarship) { $service = 'ssc/scholarship'; }
$isGuidanceService = $service === 'guidance';
$isLibraryService = $service === 'library';
$isClinicService = $service === 'clinic';
// Combined SSC/Scholarship support
$isSscScholarshipService = $service === 'ssc/scholarship' || ($service === 'ssc' && $isHeadSscScholarship);
$sscScholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'ssc';
$scholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'scholarship';
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
$topbarInfo = build_user_dashboard_header_info($user);
$roleLabel = $user['role'] === 'teacher' ? 'Teacher' : ($user['role'] === 'student' ? 'Student' : 'Admin');
$dashboardLink = $user['role'] === 'student' ? '../student_home.php' : ($user['role'] === 'teacher' ? '../teacher_home.php' : '../admin_home.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('../../assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSC Dashboard | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../../assets/css/responsive.css">
    <style>
        /* Officer and Advicer hover colors */
        .candidate-card.officer:hover { border-left-color: #D4AF37 !important; box-shadow: 0 12px 28px rgba(212,175,55,0.06); }
        .candidate-card.advicer { border-left-color: #e9e6e2; }
        .candidate-card.advicer:hover { border-left-color: #800000 !important; box-shadow: 0 12px 28px rgba(128,0,0,0.06); }

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
            body.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }
    </style>
</head>
<body>
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
            </div>
            <div class="nav-section">
                <a href="<?= $isGuidanceService ? '../guidance_home.php' : ($isLibraryService ? '../librarian_home.php' : ($isClinicService ? '../nurse_home.php' : htmlspecialchars($dashboardLink))) ?>" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="<?= $isGuidanceService ? '../school_announcements.php?service=guidance' : ($isLibraryService ? '../school_announcements.php?service=library' : ($isClinicService ? '../school_announcements.php?service=clinic' : '../school_announcements.php')) ?>" data-tooltip="Announcements">
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
                        <a href="<?= $isGuidanceService ? '../guidance_dashboard.php?service=guidance' : ($isLibraryService ? '../guidance_dashboard.php?service=library' : ($isClinicService ? '../guidance_dashboard.php?service=clinic' : '../guidance_dashboard.php')) ?>" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="<?= $isGuidanceService ? '../library_dashboard.php?service=guidance' : ($isLibraryService ? '../library_dashboard.php?service=library' : ($isClinicService ? '../library_dashboard.php?service=clinic' : '../library_dashboard.php')) ?>" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="<?= $isGuidanceService ? '../clinic_dashboard.php?service=guidance' : ($isLibraryService ? '../clinic_dashboard.php?service=library' : ($isClinicService ? '../clinic_dashboard.php?service=clinic' : '../clinic_dashboard.php')) ?>" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="<?= $isGuidanceService ? 'ssc_dashboard.php?service=guidance' : ($isLibraryService ? 'ssc_dashboard.php?service=library' : ($isClinicService ? 'ssc_dashboard.php?service=clinic' : 'ssc_dashboard.php')) ?>" class="active" data-tooltip="Supreme Student Council">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">Supreme Student Council</span>
                        </a>
                        <a href="<?= $isGuidanceService ? '../scholarship/scholarship_dashboard.php?service=guidance' : ($isLibraryService ? '../scholarship/scholarship_dashboard.php?service=library' : ($isClinicService ? '../scholarship/scholarship_dashboard.php?service=clinic' : '../scholarship/scholarship_dashboard.php')) ?>" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="<?= $isGuidanceService ? '../ssaa_student_home.php?service=guidance' : ($isLibraryService ? '../ssaa_student_home.php?service=library' : ($isClinicService ? '../ssaa_student_home.php?service=clinic' : '../ssaa_student_home.php')) ?>" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
                        </a>
                    </div>
                </div>
                <?php if ($isGuidanceService): ?>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Guidance">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage Guidance</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="../case_management.php" data-tooltip="Case Management">
                            <span class="nav-icon"><i class="fa-solid fa-file-circle-plus"></i></span>
                            <span class="nav-text">Case Management</span>
                        </a>
                        <a href="../good_moral.php" data-tooltip="Good Moral">
                            <span class="nav-icon"><i class="fa-solid fa-certificate"></i></span>
                            <span class="nav-text">Good Moral</span>
                        </a>
                        <a href="../reports.php" data-tooltip="Reports">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span class="nav-text">Reports</span>
                        </a>
                    </div>
                </div>
                <?php elseif ($isSscScholarshipService): ?>
                    <a href="../ssc_head_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="../school_announcements.php" data-tooltip="Announcements">
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
                            <a href="../guidance_dashboard.php?service=ssc/scholarship" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="../library_dashboard.php?service=ssc/scholarship" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="../clinic_dashboard.php?service=ssc/scholarship" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="../ssc_head_home.php?service=ssc/scholarship" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="../scholarship/scholarship_dashboard.php?service=ssc/scholarship" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="../ssaa_student_home.php?service=ssc/scholarship" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage SSC">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage SSC</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="ssc_manage_events.php?service=ssc/scholarship" data-tooltip="SSC Events">
                                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                                <span class="nav-text">SSC Events</span>
                            </a>
                            <a href="ssc_manage_candidates.php?service=ssc/scholarship" data-tooltip="Candidates">
                                <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                                <span class="nav-text">Candidates</span>
                            </a>
                            <a href="ssc_reports.php?service=ssc/scholarship" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Scholarship</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="../scholarship/scholarship_create_announcement.php?service=ssc/scholarship" data-tooltip="Scholarship Announcements">
                                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                <span class="nav-text">Scholarship Announcements</span>
                            </a>
                            <a href="../scholarship/scholarship_reports.php?service=ssc/scholarship" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="<?= $isGuidanceService ? '../profile.php?service=guidance' : ($isLibraryService ? '../profile.php?service=library' : ($isClinicService ? '../profile.php?service=clinic' : '../profile.php')) ?>" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="<?= $isGuidanceService ? '../about.php?service=guidance' : ($isLibraryService ? '../about.php?service=library' : ($isClinicService ? '../about.php?service=clinic' : '../about.php')) ?>" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isLibraryService): ?>
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
                            <span class="nav-icon"><i class="fa-solid fa-code"></i></span>
                            <span class="nav-text">Library QR</span>
                        </a>
                    </div>
                </div>
                <?php elseif ($isClinicService): ?>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="../clinic_status.php" data-tooltip="Clinic Status">
                            <span class="nav-icon"><i class="fa-solid fa-clock"></i></span>
                            <span class="nav-text">Clinic Status</span>
                        </a>
                        <a href="../clinic_health_records.php" data-tooltip="Health Records">
                            <span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>
                            <span class="nav-text">Health Records</span>
                        </a>
                        <a href="../clinic_medicine_inventory.php" data-tooltip="Medicine Inventory">
                            <span class="nav-icon"><i class="fa-solid fa-pills"></i></span>
                            <span class="nav-text">Medicine Inventory</span>
                        </a>
                        <a href="../clinic_visit_logs.php" data-tooltip="Visit Logs">
                            <span class="nav-icon"><i class="fa-solid fa-notes-medical"></i></span>
                            <span class="nav-text">Visit Logs</span>
                        </a>
                        <a href="../clinic_analytics.php" data-tooltip="Analytics">
                            <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                            <span class="nav-text">Analytics</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                    <a href="../profile.php" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="../about.php" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
            </div>
            <div class="nav-footer">
                <a href="../../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle mobile menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>SSC Portal</p>
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

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <div class="menu-empty">No new announcements.</div>
                        <a class="menu-link menu-footer-link" href="../about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="../profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="../profile.php" class="menu-action">View profile</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="../about.php">Help center</a>
                            <span class="menu-subtext">View support resources and FAQs.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="mailto:support@passcollege.edu">Email support</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                    <div class="service-page-header">
                        <div class="service-page-header-main">
                            <div class="service-page-header-icon"><i class="fa-solid fa-award" aria-hidden="true"></i></div>
                            <div>
                                <p class="service-page-header-eyebrow">Campus Portal</p>
                                <h1>SSC Services</h1>
                                <p class="service-page-header-welcome">Welcome back, <strong><?= htmlspecialchars($user['full_name']) ?></strong></p>
                            </div>
                        </div>
                        <div class="service-page-header-center">
                            <i class="fa-solid fa-people-group" aria-hidden="true"></i>
                            <div><strong>Student community</strong><span>Stay informed about events, initiatives, and student leadership.</span></div>
                        </div>
                        <div class="service-page-header-profile">
                            <strong><?= htmlspecialchars($roleLabel) ?></strong>
                            <div><?= htmlspecialchars($topbarInfo['displayMeta']) ?></div>
                        </div>
                    </div>
                    <section class="library-tab-bar" role="tablist" aria-label="SSC navigation">
                        <button type="button" class="library-tab-button active" data-tab="events">Event Calendar</button>
                        <button type="button" class="library-tab-button" data-tab="candidates">Candidate Board</button>
                        <button type="button" class="library-tab-button" data-tab="initiatives">Active Initiatives</button>
                    </section>

                    <section class="library-panel active" id="events">
                        <div class="dashboard-summary-grid">
                            <div class="summary-card">
                                <span>Upcoming Events</span>
                                <strong id="upcoming-count">0</strong>
                            </div>
                            <div class="summary-card">
                                <span>Total Events This Year</span>
                                <strong id="total-count">0</strong>
                            </div>
                            <div class="summary-card">
                                <span>Next Event</span>
                                <strong id="next-event-date">Not scheduled</strong>
                            </div>
                        </div>

                        <div style="margin-top: 40px;">
                            <h2>Upcoming SSC Events</h2>
                            <div id="events-container">
                                <!-- Events will be loaded here -->
                            </div>
                        </div>
                    </section>

                    <section class="library-panel" id="candidates">
                        <div class="dashboard-summary-grid">
                            <div class="summary-card">
                                <span>Current Officers</span>
                                <strong id="officers-count">0</strong>
                            </div>
                            <div class="summary-card">
                                <span>Active Candidates</span>
                                <strong id="candidates-count">0</strong>
                            </div>
                            <div class="summary-card">
                                <span>Election Date</span>
                                <strong id="election-date-display">Not scheduled</strong>
                            </div>
                        </div>

                        <!-- Sub-tabs for candidates section -->
                        <div class="clinic-tabs" style="margin-top: 30px;">
                            <button type="button" class="clinic-tab active" onclick="switchTab('officers-content')">
                                <i class="fa-solid fa-crown"></i> Current SSC Officers
                            </button>
                            <button type="button" class="clinic-tab" onclick="switchTab('running-content')">
                                <i class="fa-solid fa-user-group"></i> Running For Positions
                            </button>
                        </div>

                        <div style="margin-top: 40px;">
                            <div id="officers-content" class="clinic-content active">
                                <h2>Current SSC Officers</h2>
                                <div id="officers-container">
                                    <!-- Officers will be loaded here -->
                                </div>
                            </div>
                            <div id="running-content" class="clinic-content">
                                <h2>Running For Positions</h2>
                                <div id="running-container">
                                    <!-- Candidates will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="library-panel" id="initiatives">
                        <div class="dashboard-summary-grid">
                            <div class="summary-card">
                                <span>Active Projects</span>
                                <strong>0 ongoing initiatives</strong>
                            </div>
                            <div class="summary-card">
                                <span>Approved Events</span>
                                <strong>Managed by SSC Head</strong>
                            </div>
                            <div class="summary-card">
                                <span>Student Participation</span>
                                <strong>To be updated</strong>
                            </div>
                        </div>

                        <div style="margin-top: 40px;">
                            <h2>Current SSC Initiatives</h2>
                            <div id="initiatives-container">
                                <!-- Initiatives will be loaded here -->
                            </div>
                        </div>
                    </section>

                    <!-- Rate Our Services Button Section -->
                    <div style="background: white; border-radius: 12px; padding: 40px; margin-top: 40px; padding-top: 30px; border-top: 2px solid #e2e8f0; text-align: center;">
                        <div>
                            <h2 style="margin-top: 0;"><i class="fa-solid fa-star"></i> Share Your Experience</h2>
                        </div>
                        <p style="color: #666; margin-bottom: 20px;">Your feedback helps us improve our SSC services</p>
                        <div style="display: flex; gap: 16px; justify-content: center; align-items: center; flex-wrap: wrap; margin-bottom: 30px;">
                            <div style="text-align: center;">
                                <div style="font-size: 2.5rem; color: #fbbf24; font-weight: 600;" id="avg-rating-display">0</div>
                                <div style="font-size: 0.9rem; color: #666;">Average Rating</div>
                            </div>
                            <div style="width: 2px; height: 50px; background: #e2e8f0;"></div>
                            <div style="text-align: center;">
                                <div style="font-size: 2.5rem; color: #3498db; font-weight: 600;" id="total-feedback-display">0</div>
                                <div style="font-size: 0.9rem; color: #666;">Total Feedback</div>
                            </div>
                        </div>
                        <button id="rate-ssc-service-btn" style="font-size: 16px; padding: 12px 40px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.3s;">
                            <i class="fa-solid fa-star"></i> Rate Our Services
                        </button>
                    </div>

                    <!-- Recent Feedback Section -->
                    <section class="library-panel" id="feedback-section" style="margin-top: 40px;">
                        <div style="margin-top: 30px; overflow-x: auto;">
                            <h3><i class="fa-solid fa-comments"></i> Recent Feedback from Students</h3>
                            <table id="feedback-table" style="width: 100%; border-collapse: collapse; background: white;">
                                <thead>
                                    <tr style="background: #f8f9fa; border-bottom: 2px solid #e2e8f0;">
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Student Name</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Email</th>
                                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151;">Rating</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Feedback</th>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Date Submitted</th>
                                    </tr>
                                </thead>
                                <tbody id="feedback-tbody">
                                    <tr>
                                        <td colspan="5" style="padding: 20px; text-align: center; color: #6b7280;">Loading feedback...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="feedback-empty" style="display: none; text-align: center; padding: 40px; color: #6b7280;">
                            <i class="fa-solid fa-inbox" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px; display: block;"></i>
                            <p style="font-size: 16px;">No feedback received yet. Be the first to share your thoughts!</p>
                        </div>
                    </section>
            </div>
        </main>

        <!-- SSC Feedback Modal -->
        <div id="sscFeedbackModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div class="modal-content" style="background: white; border-radius: 12px; padding: 30px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                <span class="modal-close" onclick="closeSscFeedbackModal()" style="position: absolute; top: 15px; right: 20px; font-size: 28px; cursor: pointer; color: #999;">&times;</span>
                
                <h2 style="margin-top: 0; color: #111827; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-star" style="color: #fbbf24;"></i> Rate Our SSC Services
                </h2>
                <p style="color: #666; margin-bottom: 24px;">Your feedback helps us improve our SSC events and services</p>

                <form id="sscFeedbackForm" style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Star Rating -->
                    <div>
                        <label style="display: block; margin-bottom: 12px; font-weight: 600; color: #111827;">Overall Rating *</label>
                        <div class="rating-stars" style="display: flex; gap: 8px; font-size: 2.5rem;">
                            <input type="radio" name="rating" value="1" style="display: none;">
                            <span class="star" data-rating="1" style="cursor: pointer; color: #ddd; transition: all 0.2s;">★</span>
                            <input type="radio" name="rating" value="2" style="display: none;">
                            <span class="star" data-rating="2" style="cursor: pointer; color: #ddd; transition: all 0.2s;">★</span>
                            <input type="radio" name="rating" value="3" style="display: none;">
                            <span class="star" data-rating="3" style="cursor: pointer; color: #ddd; transition: all 0.2s;">★</span>
                            <input type="radio" name="rating" value="4" style="display: none;">
                            <span class="star" data-rating="4" style="cursor: pointer; color: #ddd; transition: all 0.2s;">★</span>
                            <input type="radio" name="rating" value="5" style="display: none;">
                            <span class="star" data-rating="5" style="cursor: pointer; color: #ddd; transition: all 0.2s;">★</span>
                        </div>
                        <p id="ssc-rating-text" style="color: #666; font-size: 0.9rem; margin-top: 8px;">Please select a rating</p>
                    </div>

                    <!-- Comments -->
                    <div>
                        <label for="ssc-feedback-comments" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827;">Your Feedback *</label>
                        <textarea id="ssc-feedback-comments" name="comments" rows="5" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; font-family: inherit;" placeholder="Tell us what you think about our SSC services..."></textarea>
                    </div>

                    <!-- Message -->
                    <div id="ssc-feedback-message" style="display: none; padding: 12px; border-radius: 6px; margin: 8px 0;"></div>

                    <!-- Buttons -->
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 16px;">
                        <button type="button" class="btn-secondary" onclick="closeSscFeedbackModal()" style="padding: 10px 24px; border: 1px solid #ddd; background: white; color: #374151; border-radius: 6px; cursor: pointer; font-weight: 600;">Cancel</button>
                        <button type="submit" class="btn-clinic" style="padding: 10px 24px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.3s;">
                            <i class="fa-solid fa-paper-plane"></i> Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="footer-card">
                    <h3>About</h3>
                    <p>PASS College is dedicated to supporting student success through integrated academic, guidance, and health services.</p>
                </div>
                <div class="footer-card">
                    <h3>Address</h3>
                    <?php
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
            <div class="footer-copyright">© 2026 PASS College. All rights reserved.</div>
        </footer>
    </div>
    <div class="page-overlay" id="pageOverlay"></div>
    <script src="../../assets/js/app.js" defer></script>
    <script>
        // SSC Dashboard Tab Functionality
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

            // Tab functionality
            const tabs = document.querySelectorAll('.library-tab-button');
            const panels = document.querySelectorAll('.library-panel');

            const activateTab = (targetId) => {
                if (!targetId) return;
                tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === targetId));
                panels.forEach(panel => panel.classList.toggle('active', panel.id === targetId));
            };

            tabs.forEach(button => {
                button.addEventListener('click', function () {
                    const target = this.dataset.tab;
                    activateTab(target);
                });
            });

            // Check for hash in URL to activate specific tab
            const hash = window.location.hash.substring(1);
            if (hash) {
                activateTab(hash);
            }

            // Load SSC data
            loadEvents();
            loadCandidates();
            loadInitiatives();

            // Initialize clinic sub-tabs
            initializeClinicTabs();

            // Initialize comment drawer event listeners
            const commentDrawerClose = document.getElementById('commentDrawerClose');
            const commentDrawerOverlay = document.getElementById('commentDrawerOverlay');
            const commentDrawerSend = document.getElementById('commentDrawerSend');
            const commentDrawerInput = document.getElementById('commentDrawerInput');

            if (commentDrawerClose) {
                commentDrawerClose.addEventListener('click', closeCommentDrawer);
            }
            if (commentDrawerOverlay) {
                commentDrawerOverlay.addEventListener('click', closeCommentDrawer);
            }
            if (commentDrawerSend) {
                commentDrawerSend.addEventListener('click', submitComment);
            }
            if (commentDrawerInput) {
                commentDrawerInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        submitComment();
                    }
                });
            }
        });

        function initializeClinicTabs() {
            // Clinic tabs are handled by the switchTab function
            // No additional initialization needed as onclick handlers are inline
        }

        function switchTab(tabId) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.clinic-content');
            contents.forEach(content => content.classList.remove('active'));

            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.clinic-tab');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Show selected tab content
            document.getElementById(tabId).classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Load data functions
        async function loadEvents() {
            try {
                const response = await fetch('../ssc_api.php?action=get_events');
                const data = await response.json();
                displayEvents(data.events || []);
            } catch (error) {
                console.error('Error loading events:', error);
                displayEvents([]);
            }
        }

        async function loadCandidates() {
            try {
                const response = await fetch('../ssc_api.php?action=get_candidates');
                const data = await response.json();
                displayCandidates(data.candidates || []);
                
                // Load election date
                const electionResponse = await fetch('../ssc_api.php?action=get_election_date');
                const electionData = await electionResponse.json();
                if (electionData.election_date) {
                    document.getElementById('election-date-display').textContent = formatDate(electionData.election_date);
                }
            } catch (error) {
                console.error('Error loading candidates:', error);
                displayCandidates([]);
            }
        }

        async function loadInitiatives() {
            try {
                const response = await fetch('../ssc_api.php?action=get_initiatives');
                const data = await response.json();
                displayInitiatives(data.initiatives || []);
            } catch (error) {
                console.error('Error loading initiatives:', error);
                displayInitiatives([]);
            }
        }

        // Position order for officers (used to sort display)
        const positionOrder = [
            'President',
            'Vice President for Academic Affairs',
            'Vice President for Non-Academic Affairs',
            'Vice President for Finance',
            'Deputy for Finance',
            'Vice President for Audit',
            'Deputy Vice Auditor',
            'Vice President for Public Relations',
            'Vice President for Marketing & Operations',
            'Media and Publications Officers',
            'Executive Secretary',
            'Deputy Secretary',
            'College Representatives'
        ];

        // Display functions
        function displayEvents(events) {
            const container = document.getElementById('events-container');

            // Filter for upcoming and ongoing events
            const upcomingEvents = events.filter(event =>
                event.status === 'upcoming' || event.status === 'ongoing'
            ).slice(0, 6);

            // Update summary cards
            document.getElementById('upcoming-count').textContent = upcomingEvents.length;
            document.getElementById('total-count').textContent = events.length;
            
            // Find next event date
            if (upcomingEvents.length > 0) {
                const nextEvent = upcomingEvents[0];
                document.getElementById('next-event-date').textContent = formatDate(nextEvent.event_date);
            } else if (events.length > 0) {
                const firstEvent = events[0];
                document.getElementById('next-event-date').textContent = formatDate(firstEvent.event_date);
            }

            if (upcomingEvents.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <h3>No Events Scheduled</h3>
                        <p>SSC events will be posted here by the SSC Head. Check back later for updates on intramurals, examinations, and other school activities.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="announcement-grid">
                    ${upcomingEvents.map(event => {
                        const photos = (event.photos && event.photos.length > 0) ? event.photos : (event.photo_url ? [event.photo_url] : []);
                        const images = photos.map(photo => resolveEventImageUrl(photo)).filter(img => img);
                        const hasImage = images.length > 0;
                        const safeTitle = event.title ? event.title.replace(/\"/g, '&quot;') : 'SSC Event';
                        
                        // Build image grid HTML matching school_announcements.php structure
                        let imageGridHtml = '';
                        if (hasImage) {
                            const count = images.length;
                            const imagesJson = JSON.stringify(images).replace(/"/g, '&quot;');
                            imageGridHtml = '<div class="announcement-images-grid" data-images="' + imagesJson + '">';
                            
                            if (count === 1) {
                                imageGridHtml += '<div class="announcement-image-row single-row">';
                                imageGridHtml += '<button type="button" class="announcement-image-wrapper" data-image-index="0" onclick="openAnnouncementImageModal(event)"><img src="' + images[0].replace(/"/g, '&quot;') + '" alt="Announcement image"></button>';
                                imageGridHtml += '</div>';
                            } else {
                                const topImages = images.slice(0, Math.min(2, count));
                                const bottomImages = images.slice(2, Math.min(5, count));
                                
                                imageGridHtml += '<div class="announcement-image-row top-row">';
                                topImages.forEach((img, idx) => {
                                    imageGridHtml += '<button type="button" class="announcement-image-wrapper" data-image-index="' + idx + '"><img src="' + img.replace(/"/g, '&quot;') + '" alt="Announcement image"></button>';
                                });
                                imageGridHtml += '</div>';
                                
                                if (bottomImages.length > 0) {
                                    const bottomClass = bottomImages.length === 1 ? 'one-item' : bottomImages.length === 2 ? 'two-items' : 'three-items';
                                    imageGridHtml += '<div class="announcement-image-row bottom-row ' + bottomClass + '">';
                                    bottomImages.forEach((img, idx) => {
                                        const imageIndex = idx + topImages.length;
                                        imageGridHtml += '<button type="button" class="announcement-image-wrapper" data-image-index="' + imageIndex + '">';
                                        imageGridHtml += '<img src="' + img.replace(/"/g, '&quot;') + '" alt="Announcement image">';
                                        if (count > 5 && idx === 2) {
                                            imageGridHtml += '<div class="announcement-image-overlay">+' + (count - 5) + '</div>';
                                        }
                                        imageGridHtml += '</button>';
                                    });
                                    imageGridHtml += '</div>';
                                }
                            }
                            imageGridHtml += '</div>';
                        }

                        return `
                            <div class="announcement-card ssc" data-type="ssc" data-id="${event.id || ''}">
                                <div class="announcement-identity">
                                    <div class="announcement-avatar"><i class="fa-solid fa-award"></i></div>
                                    <div class="announcement-author-block">
                                        <div class="announcement-author">SSC Events</div>
                                        <div class="announcement-source-meta">
                                            <span><i class="fa-solid fa-clock"></i> ${formatTimeAgo(event.event_date)}</span>
                                            <span><i class="fa-solid fa-globe"></i> Public</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="announcement-header">
                                    <h3 class="announcement-title">${event.title}</h3>
                                    <span class="announcement-badge ssc">SSC Event</span>
                                </div>
                                <div class="announcement-meta">
                                    <span class="announcement-meta-item">
                                        <i class="fa-solid fa-calendar-days"></i>
                                        ${formatDate(event.event_date)}
                                    </span>
                                    ${event.location ? `
                                        <span class="announcement-meta-item">
                                            <i class="fa-solid fa-map-pin"></i>
                                            ${event.location.replace(/"/g, '&quot;')}
                                        </span>
                                    ` : ''}
                                </div>
                                <div class="announcement-description${hasImage ? ' has-image collapsed' : ''}">
                                    <p class="announcement-text">${event.description ? event.description.replace(/\n/g, '<br>') : ''}</p>
                                    ${hasImage ? '<button type="button" class="toggle-description">See more</button>' : ''}
                                </div>
                                ${imageGridHtml}
                                <div class="announcement-actions">
                                    <button type="button" class="like-button"><i class="fa-regular fa-heart"></i> Like <span class="heart-count">0</span></button>
                                    <button type="button" class="comment-button"><i class="fa-regular fa-comment"></i> Comment</button>
                                </div>
                                <button type="button" class="view-all-comments" data-comments="[]" data-count="0" data-title="${safeTitle}">View all 0 comments</button>
                                <div class="comment-preview-list">
                                    <div class="comment-preview no-comments">No comments yet. Be the first to reply.</div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;

            // Initialize interactions after rendering
            initializeSavedInteractions();
        }

        function resolveEventImageUrl(photoUrl) {
            if (!photoUrl) {
                return '';
            }
            if (photoUrl.startsWith('http://') || photoUrl.startsWith('https://') || photoUrl.startsWith('/')) {
                return photoUrl;
            }
            return '../../' + photoUrl;
        }

        // Comment and Like functionality
        const announcementApiUrl = '../api_announcement_interactions.php';
        let currentCommentContext = null;

        function buildCommentAuthor(comment) {
            if (!comment.course) {
                return comment.name || 'Anonymous';
            }
            return `${comment.name} • ${comment.course}`;
        }

        async function apiPost(action, body) {
            const response = await fetch(`${announcementApiUrl}?action=${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(body)
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Announcement API error');
            }

            return data;
        }

        async function fetchAnnouncementLikeStatus(type, id) {
            const response = await fetch(`${announcementApiUrl}?action=get-like-status&type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Unable to load like status');
            }
            return {
                hasLiked: !!data.hasLiked,
                likeCount: Number(data.likeCount || 0)
            };
        }

        async function fetchAnnouncementComments(type, id) {
            const response = await fetch(`${announcementApiUrl}?action=get-comments&type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Unable to load comments');
            }
            return Array.isArray(data.comments) ? data.comments : [];
        }

        function renderLikeButton(button, hasLiked, likeCount) {
            if (!button) return;
            const heartIcon = button.querySelector('i');
            const heartCount = button.querySelector('.heart-count');
            if (hasLiked) {
                button.classList.add('liked');
                heartIcon.classList.remove('fa-regular');
                heartIcon.classList.add('fa-solid');
            } else {
                button.classList.remove('liked');
                heartIcon.classList.remove('fa-solid');
                heartIcon.classList.add('fa-regular');
            }
            if (heartCount) {
                heartCount.textContent = String(likeCount);
            }
        }

        function renderCommentPreview(card, comments) {
            if (!card) return;
            const previewList = card.querySelector('.comment-preview-list');
            const previewButton = card.querySelector('.view-all-comments');
            if (!previewList || !previewButton) return;

            const count = comments.length;
            previewButton.dataset.count = count;
            previewButton.textContent = `View all ${count} comments`;
            previewButton.dataset.title = card.querySelector('.announcement-title')?.textContent || ''; 

            if (count === 0) {
                previewList.innerHTML = '<div class="comment-preview no-comments">No comments yet. Be the first to reply.</div>';
                return;
            }

            const previewComments = comments.slice(0, 2);
            previewList.innerHTML = previewComments.map(comment => {
                const author = buildCommentAuthor(comment);
                const time = comment.timestamp ? formatRelativeTime(comment.timestamp) : 'Just now';
                return `
                    <div class="comment-preview">
                        <div class="comment-meta">
                            <span class="comment-author">${escapeHtml(author)}</span>
                            <span class="comment-time">${escapeHtml(time)}</span>
                        </div>
                        <p class="comment-text">${escapeHtml(comment.text)}</p>
                    </div>
                `;
            }).join('');
        }

        async function loadCardInteractionState(card) {
            const type = card.dataset.type;
            const id = card.dataset.id;
            if (!type || !id) return;

            try {
                const [status, comments] = await Promise.all([
                    fetchAnnouncementLikeStatus(type, id),
                    fetchAnnouncementComments(type, id)
                ]);
                const likeButton = card.querySelector('.like-button');
                if (likeButton) {
                    renderLikeButton(likeButton, status.hasLiked, status.likeCount);
                }
                renderCommentPreview(card, comments);
            } catch (error) {
                console.error('Announcement interaction load failed:', error);
            }
        }

        async function updateCardLikeState(type, id) {
            const card = document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`);
            if (!card) return;
            try {
                const status = await fetchAnnouncementLikeStatus(type, id);
                renderLikeButton(card.querySelector('.like-button'), status.hasLiked, status.likeCount);
            } catch (error) {
                console.error('Unable to refresh card like state:', error);
            }
        }

        function initializeSavedInteractions() {
            document.querySelectorAll('.announcement-card').forEach(card => {
                const type = card.dataset.type;
                const id = card.dataset.id;
                if (!type || !id) return;

                loadCardInteractionState(card);

                const likeButton = card.querySelector('.like-button');
                if (likeButton) {
                    likeButton.addEventListener('click', () => toggleLike(type, id, likeButton));
                }

                const title = card.querySelector('.announcement-title')?.textContent || '';
                const commentButton = card.querySelector('.comment-button');
                const viewAllBtn = card.querySelector('.view-all-comments');
                if (commentButton) {
                    commentButton.addEventListener('click', () => openCommentDrawer(type, id, title));
                }
                if (viewAllBtn) {
                    viewAllBtn.addEventListener('click', () => openCommentDrawer(type, id, title));
                }
            });
        }

        async function toggleLike(type, id, button) {
            if (!type || !id || !button) return;
            try {
                const result = await apiPost('toggle-like', { type, id });
                if (result.action === 'added') {
                    createHeartBurst(button);
                }
                renderLikeButton(button, result.action === 'added', Number(result.likeCount || 0));
            } catch (error) {
                console.error('Unable to toggle like:', error);
            }
        }

        function createHeartBurst(button) {
            try {
                const bubble = document.createElement('span');
                bubble.className = 'heart-bubble';
                bubble.textContent = '+1';
                // ensure button is positioned relatively (if not already)
                const prevPos = window.getComputedStyle(button).position;
                if (prevPos === 'static') {
                    button.style.position = 'relative';
                }
                button.appendChild(bubble);
                setTimeout(() => {
                    if (bubble.parentNode === button) {
                        button.removeChild(bubble);
                    }
                }, 450);
            } catch (e) {
                // fail silently
            }
        }

        function renderCommentThread(comments) {
            const thread = document.getElementById('commentThread');
            if (!thread) return;
            thread.innerHTML = comments.length ? comments.map(comment => {
                const canDelete = comment.canDelete ? '<button type="button" class="comment-delete-button" aria-label="Delete comment"><i class="fa-solid fa-trash"></i></button>' : '';
                return `
                    <div class="comment-item" data-comment-id="${escapeHtml(String(comment.id))}">
                        <div class="comment-meta">
                            <span class="comment-author">${escapeHtml(buildCommentAuthor(comment))}</span>
                            <span class="comment-time">${escapeHtml(formatRelativeTime(comment.timestamp))}</span>
                            ${canDelete}
                        </div>
                        <div class="comment-text">${escapeHtml(comment.text)}</div>
                    </div>
                `;
            }).join('') : '<div class="comment-item no-comments">No comments yet. Be the first to reply.</div>';
        }

        function updateCommentDrawerTitle(count) {
            const subtitle = document.getElementById('commentDrawerSubtitle');
            if (subtitle) {
                subtitle.textContent = `Showing ${count} replies`;
            }
        }

        async function openCommentDrawer(type, id, title, isModal = false) {
            if (!type || !id) return;
            currentCommentContext = { type, id };
            const drawer = document.getElementById('commentDrawer');
            const overlay = document.getElementById('commentDrawerOverlay');
            const titleElement = document.getElementById('commentDrawerTitle');
            const commentInput = document.getElementById('commentDrawerInput');
            const pinnedComments = document.getElementById('drawerPinnedComments');
            const modalContent = document.querySelector('.image-modal-content');

            titleElement.textContent = title;
            drawer.dataset.type = type;
            drawer.dataset.id = id;

            try {
                const comments = await fetchAnnouncementComments(type, id);
                renderCommentThread(comments);
                updateCommentDrawerTitle(comments.length);
                renderCommentPreview(document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`), comments);
            } catch (error) {
                console.error('Unable to load comment drawer:', error);
                renderCommentThread([]);
                updateCommentDrawerTitle(0);
            }

            pinnedComments.innerHTML = '';
            if (isModal && modalContent) {
                drawer.classList.add('modal-embedded');
                modalContent.classList.add('with-drawer');
                modalContent.appendChild(drawer);
                overlay.style.display = 'none';
            } else {
                if (drawer.classList.contains('modal-embedded') && modalContent && modalContent.contains(drawer)) {
                    document.body.appendChild(drawer);
                }
                drawer.classList.remove('modal-embedded');
                if (modalContent) {
                    modalContent.classList.remove('with-drawer');
                }
                overlay.style.display = '';
                overlay.classList.add('open');
            }

            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
            setTimeout(() => commentInput?.focus(), 300);
        }

        function closeCommentDrawer() {
            const drawer = document.getElementById('commentDrawer');
            const overlay = document.getElementById('commentDrawerOverlay');
            const modalContent = document.querySelector('.image-modal-content');
            if (drawer && drawer.classList.contains('modal-embedded')) {
                if (modalContent && modalContent.contains(drawer)) {
                    document.body.appendChild(drawer);
                }
                drawer.classList.remove('modal-embedded');
            }
            if (modalContent) {
                modalContent.classList.remove('with-drawer');
            }
            if (drawer) {
                drawer.classList.remove('open');
                drawer.setAttribute('aria-hidden', 'true');
            }
            if (overlay) {
                overlay.classList.remove('open');
                overlay.style.display = '';
            }
        }

        async function submitComment() {
            const drawer = document.getElementById('commentDrawer');
            const type = drawer.dataset.type;
            const id = drawer.dataset.id;
            const commentInput = document.getElementById('commentDrawerInput');
            const text = commentInput.value.trim();

            if (!type || !id || !text) return;

            try {
                await apiPost('add-comment', { type, id, text });
                const comments = await fetchAnnouncementComments(type, id);
                renderCommentThread(comments);
                updateCommentDrawerTitle(comments.length);
                renderCommentPreview(document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`), comments);
                commentInput.value = '';
            } catch (error) {
                console.error('Unable to save comment:', error);
            }
        }

        async function deleteComment(commentId) {
            if (!currentCommentContext || !commentId) return;
            const { type, id } = currentCommentContext;
            try {
                await apiPost('delete-comment', { type, id, comment_id: commentId });
                const comments = await fetchAnnouncementComments(type, id);
                renderCommentThread(comments);
                updateCommentDrawerTitle(comments.length);
                renderCommentPreview(document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`), comments);
            } catch (error) {
                console.error('Unable to delete comment:', error);
            }
        }

        document.addEventListener('click', function (event) {
            const deleteButton = event.target.closest('.comment-delete-button');
            if (!deleteButton) {
                return;
            }
            event.preventDefault();
            const commentItem = deleteButton.closest('.comment-item');
            const commentId = commentItem?.dataset.commentId;
            if (commentId) {
                deleteComment(commentId);
            }
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatRelativeTime(timestamp) {
            const now = Date.now();
            const diff = now - timestamp;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);

            if (minutes < 1) return 'Just now';
            if (minutes < 60) return `${minutes}m ago`;
            if (hours < 24) return `${hours}h ago`;
            return `${days}d ago`;
        }

        // Image modal functions
        let currentModalImages = [];
        let currentModalImageIndex = 0;
        let currentModalContext = null;
        let modalTouchStartX = 0;

        function openAnnouncementImageModal(event) {
            event.preventDefault();
            event.stopPropagation();
            const wrapper = event.target.closest('.announcement-image-wrapper');
            if (!wrapper) return;

            const card = wrapper.closest('.announcement-card');
            const type = card?.dataset.type || '';
            const id = card?.dataset.id || '';
            const title = card?.querySelector('.announcement-title')?.textContent || '';
            const rawDescription = card?.querySelector('.announcement-text')?.textContent.trim() || '';
            const description = rawDescription ? rawDescription.replace(/\s+/g, ' ').slice(0, 180) + (rawDescription.length > 180 ? '...' : '') : '';
            currentModalContext = { type, id, title, description };

            const imageGrid = wrapper.closest('.announcement-images-grid');
            const images = imageGrid ? JSON.parse(imageGrid.dataset.images || '[]') : [];
            const index = parseInt(wrapper.dataset.imageIndex, 10) || 0;

            currentModalImages = Array.isArray(images) ? images.slice() : [];
            currentModalImageIndex = Math.max(0, Math.min(index, currentModalImages.length - 1));
            renderModalImage();

            const modal = document.getElementById('announcementImageModal');
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeAnnouncementModal() {
            const modal = document.getElementById('announcementImageModal');
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }

        function setupModalSwipe() {
            const modal = document.getElementById('announcementImageModal');
            if (!modal) return;

            modal.addEventListener('touchstart', function (event) {
                if (event.touches.length === 1) {
                    modalTouchStartX = event.touches[0].clientX;
                }
            }, { passive: true });

            modal.addEventListener('touchend', function (event) {
                if (event.changedTouches.length !== 1) return;
                const touchEndX = event.changedTouches[0].clientX;
                const deltaX = touchEndX - modalTouchStartX;
                if (Math.abs(deltaX) < 60) return;

                if (deltaX < 0) {
                    showModalImage(currentModalImageIndex + 1);
                } else {
                    showModalImage(currentModalImageIndex - 1);
                }
            }, { passive: true });
        }

        document.addEventListener('DOMContentLoaded', function () {
            setupModalSwipe();
        });

        async function renderModalImage() {
            const list = document.getElementById('modalImageList');
            const titleEl = document.getElementById('modalImageTitle');
            const descriptionEl = document.getElementById('modalImageDescription');

            if (titleEl) {
                titleEl.textContent = currentModalContext?.title || 'Announcement';
            }
            if (descriptionEl) {
                descriptionEl.textContent = currentModalContext?.description || 'Open the image to view full screen, interact with the announcement, and access likes/comments.';
            }

            list.innerHTML = '';
            if (!currentModalImages.length) {
                return;
            }

            const slide = document.createElement('div');
            slide.className = 'image-modal-slide';

            const image = document.createElement('img');
            image.src = currentModalImages[currentModalImageIndex];
            image.alt = 'Announcement image';
            slide.appendChild(image);

            if (currentModalImages.length > 1) {
                const prevButton = document.createElement('button');
                prevButton.type = 'button';
                prevButton.className = 'image-modal-nav prev';
                prevButton.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
                prevButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    showModalImage(currentModalImageIndex - 1);
                });

                const nextButton = document.createElement('button');
                nextButton.type = 'button';
                nextButton.className = 'image-modal-nav next';
                nextButton.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                nextButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    showModalImage(currentModalImageIndex + 1);
                });

                const counter = document.createElement('div');
                counter.className = 'image-modal-counter';
                counter.textContent = `${currentModalImageIndex + 1} of ${currentModalImages.length}`;

                slide.appendChild(prevButton);
                slide.appendChild(nextButton);
                slide.appendChild(counter);
            }

            if (currentModalContext && currentModalContext.type && currentModalContext.id) {
                const actions = document.createElement('div');
                actions.className = 'image-modal-actions';

                const likeButton = document.createElement('button');
                likeButton.type = 'button';
                likeButton.className = 'modal-like-button';
                likeButton.innerHTML = '<i class="fa-regular fa-heart"></i> Like';

                try {
                    const status = await fetchAnnouncementLikeStatus(currentModalContext.type, currentModalContext.id);
                    if (status.hasLiked) {
                        likeButton.classList.add('liked');
                        likeButton.innerHTML = '<i class="fa-solid fa-heart"></i> Like';
                    }
                } catch (error) {
                    console.error('Unable to load modal like state:', error);
                }

                likeButton.addEventListener('click', async function (event) {
                    event.stopPropagation();
                    await toggleLike(currentModalContext.type, currentModalContext.id, likeButton);
                    updateCardLikeState(currentModalContext.type, currentModalContext.id);
                });

                const commentButton = document.createElement('button');
                commentButton.type = 'button';
                commentButton.className = 'modal-comment-button';
                commentButton.innerHTML = '<i class="fa-regular fa-comment"></i> Comment';
                commentButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    openCommentDrawer(currentModalContext.type, currentModalContext.id, currentModalContext.title, true);
                });

                actions.appendChild(likeButton);
                actions.appendChild(commentButton);
                slide.appendChild(actions);
            }

            list.appendChild(slide);
        }

        function showModalImage(index) {
            if (index < 0 || index >= currentModalImages.length) {
                return;
            }
            currentModalImageIndex = index;
            renderModalImage();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const imageModal = document.getElementById('announcementImageModal');
            if (imageModal) {
                imageModal.addEventListener('click', function (event) {
                    if (event.target === this) {
                        closeAnnouncementModal();
                    }
                });
            }
        });

        document.addEventListener('keydown', function (event) {
            const modal = document.getElementById('announcementImageModal');
            if (!modal || !modal.classList.contains('open')) {
                return;
            }
            if (event.key === 'ArrowLeft') {
                showModalImage(currentModalImageIndex - 1);
            } else if (event.key === 'ArrowRight') {
                showModalImage(currentModalImageIndex + 1);
            } else if (event.key === 'Escape') {
                closeAnnouncementModal();
            }
        });

        // Add click event listeners to image wrappers
        document.addEventListener('click', function(e) {
            const imageWrapper = e.target.closest('.announcement-image-wrapper');
            if (imageWrapper) {
                openAnnouncementImageModal(e);
            }
        });

        // Toggle description
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('toggle-description')) {
                const description = e.target.closest('.announcement-description');
                description.classList.toggle('collapsed');
                e.target.textContent = description.classList.contains('collapsed') ? 'See more' : 'See less';
            }
        });

        function displayCandidates(candidates) {
            // Filter for current officers, advicers and active candidates
            const officers = candidates.filter(candidate => candidate.status === 'elected');
            const advicers = candidates.filter(candidate => candidate.status === 'advicer');
            const activeCandidates = candidates.filter(candidate => candidate.status === 'active');

            // Update summary cards
            document.getElementById('officers-count').textContent = officers.length;
            document.getElementById('candidates-count').textContent = activeCandidates.length;

            // Display officers (pass advicers to append at end)
            displayOfficers(officers, advicers);

            // Display running candidates
            displayRunningCandidates(activeCandidates);
        }

        function displayOfficers(officers, advicers = []) {
            const container = document.getElementById('officers-container');

            if (officers.length === 0 && advicers.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fa-solid fa-crown"></i>
                        </div>
                        <h3>No Current Officers</h3>
                        <p>SSC officers will be listed here after elections. Check back for updates on student council leadership.</p>
                    </div>
                `;
                return;
            }

            // Sort officers using normalized position order and fallback to name
            const normalizedOrder = positionOrder.map(p => p.toString().toLowerCase().replace(/\s+/g, ' ').trim());
            const normalize = s => (s || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();

            officers.sort((a, b) => {
                const aPos = normalizedOrder.indexOf(normalize(a.position));
                const bPos = normalizedOrder.indexOf(normalize(b.position));
                const aIdx = aPos === -1 ? normalizedOrder.length : aPos;
                const bIdx = bPos === -1 ? normalizedOrder.length : bPos;
                if (aIdx !== bIdx) return aIdx - bIdx;
                return (a.full_name || '').localeCompare(b.full_name || '');
            });

            let html = '<div class="resources-grid">';
            html += officers.map(candidate => `
                <div class="candidate-card officer" style="border-bottom: 4px solid #f39c12;">
                    ${candidate.photo_url ? `<img src="../../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : '<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>'}
                    <div class="candidate-info">
                        <h4>${candidate.full_name}</h4>
                        <p class="candidate-position">${candidate.position}</p>
                        ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                        <div style="margin-top: 8px;"><span style="display: inline-block; background: #f39c12; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">Officer</span></div>
                    </div>
                </div>
            `).join('');

            // Append advicers at the end
            if (advicers && advicers.length > 0) {
                html += advicers.map(candidate => `
                    <div class="candidate-card advicer" style="border-bottom: 4px solid #e9e6e2;">
                        ${candidate.photo_url ? `<img src="../../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : '<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>'}
                        <div class="candidate-info">
                            <h4>${candidate.full_name}</h4>
                            ${candidate.position ? `<p class="candidate-position">${candidate.position}</p>` : ''}
                            ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                            <div style="margin-top: 8px;"><span style="display: inline-block; background: #6b7280; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">Advisor</span></div>
                        </div>
                    </div>
                `).join('');
            }

            html += '</div>';
            container.innerHTML = html;
        }

        function displayRunningCandidates(candidates) {
            const container = document.getElementById('running-container');

            if (candidates.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                        <h3>No Candidates Running</h3>
                        <p>Candidates for SSC positions will be listed here during election periods. Stay tuned for updates on student council elections.</p>
                    </div>
                `;
                return;
            }

            // Group candidates by year, then by position
            const groupedByYear = {};
            candidates.forEach(candidate => {
                const year = candidate.officer_year || candidate.year_range || ((new Date().getFullYear() - 1) + '-' + new Date().getFullYear());
                if (!groupedByYear[year]) {
                    groupedByYear[year] = {};
                }
                const position = candidate.position || 'No Position';
                if (!groupedByYear[year][position]) {
                    groupedByYear[year][position] = [];
                }
                groupedByYear[year][position].push(candidate);
            });
            
            // Sort years in descending order
            const sortedYears = Object.keys(groupedByYear).sort((a, b) => {
                const yearA = parseInt(a.split('-')[0]);
                const yearB = parseInt(b.split('-')[0]);
                return yearB - yearA;
            });
            
            let html = '';
            sortedYears.forEach(year => {
                const positionsInYear = groupedByYear[year];
                const positions = Object.keys(positionsInYear);
                
                if (positions.length === 0) return;
                
                html += `<div style="margin-bottom: 60px;">
                    <h3 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 30px;">
                        <i class="fa-solid fa-users" style="color: #3498db; margin-right: 8px;"></i>Candidates ${year}
                    </h3>`;
                
                // Sort positions by position order
                const normalizedOrder = positionOrder.map(p => p.toString().toLowerCase().replace(/\s+/g, ' ').trim());
                const normalize = s => (s || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();
                
                const sortedPositions = positions.sort((a, b) => {
                    const posAraw = normalize(a);
                    const posBraw = normalize(b);
                    
                    const posAidx = normalizedOrder.indexOf(posAraw);
                    const posBidx = normalizedOrder.indexOf(posBraw);
                    
                    const aPos = posAidx === -1 ? normalizedOrder.length : posAidx;
                    const bPos = posBidx === -1 ? normalizedOrder.length : posBidx;
                    
                    return aPos - bPos;
                });
                
                sortedPositions.forEach(position => {
                    const candidatesInPosition = positionsInYear[position];
                    
                    html += `<div style="margin-bottom: 40px;">
                        <h4 style="color: #34495e; font-size: 1.1rem; margin-bottom: 15px; padding-left: 15px; border-left: 4px solid #3498db; font-weight: 600;">
                            ${position}
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">`;
                    
                    candidatesInPosition.forEach(candidate => {
                        html += `<div class="candidate-card">
                            ${candidate.photo_url ? `<img src="../../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : '<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>'}
                            <div class="candidate-info">
                                <h4>${candidate.full_name}</h4>
                                <p class="candidate-position">${candidate.position}</p>
                                ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                                ${candidate.platform ? `<p class="candidate-platform">${candidate.platform.substring(0, 100)}${candidate.platform.length > 100 ? '...' : ''}</p>` : ''}
                            </div>
                        </div>`;
                    });
                    
                    html += `</div></div>`;
                });
                
                html += `</div>`;
            });
            
            container.innerHTML = html;
        }

        function displayInitiatives(initiatives) {
            const container = document.getElementById('initiatives-container');

            // Filter for active initiatives
            const activeInitiatives = initiatives.filter(initiative =>
                initiative.status === 'active' || initiative.status === 'planning'
            ).slice(0, 6); // Limit to 6 initiatives

            if (activeInitiatives.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h3>No Active Initiatives</h3>
                        <p>SSC projects and approved events will be displayed here by the SSC Head. This section will show ongoing student engagement initiatives and approved activities.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="resources-grid">
                    ${activeInitiatives.map(initiative => `
                        <div class="resource-card">
                            <h4>${initiative.title}</h4>
                            <p>${initiative.description || 'No description available'}</p>
                            <div class="resource-card-footer">
                                <span class="resource-status">${initiative.status}</span>
                                ${initiative.participants_count ? `<span class="resource-participants">${initiative.participants_count} participants</span>` : ''}
                            </div>
                            ${initiative.category ? `<div class="resource-category"><i class="fa-solid fa-tag"></i> ${initiative.category}</div>` : ''}
                        </div>
                    `).join('')}
                </div>
            `;
        }

        // Utility function
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function formatTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffTime = Math.abs(now - date);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays === 1) {
                return '1 day ago';
            } else if (diffDays < 7) {
                return `${diffDays} days ago`;
            } else if (diffDays < 30) {
                const weeks = Math.floor(diffDays / 7);
                return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
            } else {
                const months = Math.floor(diffDays / 30);
                return `${months} month${months > 1 ? 's' : ''} ago`;
            }
        }

        function prevImage(button) {
            const carousel = button.parentElement;
            const images = carousel.querySelectorAll('.event-image');
            let activeIndex = Array.from(images).findIndex(img => img.classList.contains('active'));
            images[activeIndex].classList.remove('active');
            activeIndex = activeIndex > 0 ? activeIndex - 1 : images.length - 1;
            images[activeIndex].classList.add('active');
        }

        function nextImage(button) {
            const carousel = button.parentElement;
            const images = carousel.querySelectorAll('.event-image');
            let activeIndex = Array.from(images).findIndex(img => img.classList.contains('active'));
            images[activeIndex].classList.remove('active');
            activeIndex = activeIndex < images.length - 1 ? activeIndex + 1 : 0;
            images[activeIndex].classList.add('active');
        }

        // Handle search button click
        const searchBtn = document.querySelector('.nav-search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                alert('Search functionality coming soon!');
            });
        }

        // SSC Feedback Modal Functions
        const sscFeedbackModal = document.getElementById('sscFeedbackModal');
        const rateSscServiceBtn = document.getElementById('rate-ssc-service-btn');
        const sscFeedbackForm = document.getElementById('sscFeedbackForm');
        const sscFeedbackMessage = document.getElementById('ssc-feedback-message');
        const sscStars = document.querySelectorAll('#sscFeedbackModal .star');
        const sscRatingText = document.getElementById('ssc-rating-text');
        const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

        // Open modal button
        rateSscServiceBtn.addEventListener('click', function() {
            sscFeedbackModal.style.display = 'flex';
            sscFeedbackForm.reset();
            sscFeedbackMessage.style.display = 'none';
            document.querySelectorAll('input[name="rating"]').forEach(r => r.checked = false);
            sscRatingText.textContent = 'Please select a rating';
            updateSscStarColors(0);
        });

        function closeSscFeedbackModal() {
            sscFeedbackModal.style.display = 'none';
        }

        // Close modal when clicking outside
        sscFeedbackModal.addEventListener('click', function(e) {
            if (e.target === sscFeedbackModal) {
                closeSscFeedbackModal();
            }
        });

        // Star rating functionality
        sscStars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                document.querySelector('input[name="rating"][value="' + rating + '"]').checked = true;
                updateSscStarColors(rating);
                sscRatingText.textContent = `You rated: ${ratingLabels[rating]} (${rating}/5)`;
            });

            star.addEventListener('mouseover', function() {
                const rating = this.dataset.rating;
                sscStars.forEach((s, idx) => {
                    s.style.color = idx < rating ? '#fbbf24' : '#ddd';
                });
            });
        });

        document.querySelector('#sscFeedbackModal .rating-stars').addEventListener('mouseleave', function() {
            const checkedRating = document.querySelector('input[name="rating"]:checked');
            const rating = checkedRating ? checkedRating.value : 0;
            updateSscStarColors(rating);
        });

        function updateSscStarColors(rating) {
            sscStars.forEach((s, idx) => {
                s.style.color = idx < rating ? '#fbbf24' : '#ddd';
            });
        }

        // Form submission
        sscFeedbackForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const rating = document.querySelector('input[name="rating"]:checked').value;
            const comments = document.getElementById('ssc-feedback-comments').value;

            if (!rating) {
                showSscFeedbackMessage('Please select a rating', 'error');
                return;
            }

            if (!comments.trim()) {
                showSscFeedbackMessage('Please enter your feedback', 'error');
                return;
            }

            try {
                const response = await fetch('/THESIS/SUPPORTSERVICESYSTEM/includes/api_ssc_feedback.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'save',
                        rating: parseInt(rating),
                        comments: comments
                    }),
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success) {
                    showSscFeedbackMessage('Thank you for your feedback!', 'success');
                    setTimeout(() => {
                        closeSscFeedbackModal();
                        sscFeedbackForm.reset();
                        loadAllSscFeedback();
                        loadSscFeedbackStats();
                    }, 1500);
                } else {
                    showSscFeedbackMessage(result.message || 'Error submitting feedback', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showSscFeedbackMessage('An error occurred while submitting your feedback', 'error');
            }
        });

        function showSscFeedbackMessage(text, type) {
            sscFeedbackMessage.textContent = text;
            sscFeedbackMessage.style.display = 'block';
            sscFeedbackMessage.style.background = type === 'success' ? '#d4edda' : '#f8d7da';
            sscFeedbackMessage.style.color = type === 'success' ? '#155724' : '#721c24';
            sscFeedbackMessage.style.border = type === 'success' ? '1px solid #c3e6cb' : '1px solid #f5c6cb';
        }

        // Load all SSC feedback
        async function loadAllSscFeedback() {
            try {
                const response = await fetch('../ssc_api.php?action=get_all_feedback', {
                    method: 'GET',
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data && result.data.length > 0) {
                    const tbody = document.getElementById('feedback-tbody');
                    tbody.innerHTML = '';

                    result.data.forEach(feedback => {
                        const date = new Date(feedback.created_at).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        const stars = '★'.repeat(feedback.rating) + '☆'.repeat(5 - feedback.rating);
                        const ratingLabel = ratingLabels[feedback.rating] || '';

                        const row = `
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 12px; color: #111827;">${feedback.full_name}</td>
                                <td style="padding: 12px; color: #6b7280; font-size: 0.9em;">${feedback.email}</td>
                                <td style="padding: 12px; text-align: center; color: #fbbf24; font-size: 16px;" title="${ratingLabel}">${stars}</td>
                                <td style="padding: 12px; color: #374151; max-width: 300px; word-break: break-word;">${feedback.comments || '-'}</td>
                                <td style="padding: 12px; color: #6b7280; font-size: 0.9em;">${date}</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });

                    document.getElementById('feedback-empty').style.display = 'none';
                } else {
                    document.getElementById('feedback-tbody').innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #6b7280;">No feedback received yet.</td></tr>';
                    document.getElementById('feedback-empty').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading feedback:', error);
                document.getElementById('feedback-tbody').innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #991b1b;">Error loading feedback</td></tr>';
            }
        }

        // Load feedback stats
        async function loadSscFeedbackStats() {
            try {
                const response = await fetch('../ssc_api.php?action=get_feedback_stats', {
                    method: 'GET',
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('avg-rating-display').textContent = result.avg_rating.toFixed(1);
                    document.getElementById('total-feedback-display').textContent = result.total_feedback;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Load feedback on page load
        loadAllSscFeedback();
        loadSscFeedbackStats();

        // Mobile menu toggle
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const sidebar = document.querySelector('.side-nav');
        const pageOverlay = document.querySelector('.page-overlay');

        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('mobile-open');
                pageOverlay.classList.toggle('active');
            });
        }

        // Close sidebar when overlay is clicked
        if (pageOverlay) {
            pageOverlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            });
        }

        // Close sidebar when nav links are clicked
        const navLinks = document.querySelectorAll('.side-nav a');
        navLinks.forEach(link => {
            if (!link.classList.contains('nav-toggle')) {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });
            }
        });

        // Close sidebar on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const sidebar = document.querySelector('.side-nav');
                const pageOverlay = document.querySelector('.page-overlay');
                sidebar.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            }
        });
    </script>

    <!-- Comment Drawer Overlay -->
    <div class="comment-drawer-overlay" id="commentDrawerOverlay"></div>
    
    <!-- Comment Drawer -->
    <aside class="comment-drawer" id="commentDrawer" aria-hidden="true">
        <div class="comment-drawer-header">
            <div>
                <h3 id="commentDrawerTitle">Comments</h3>
                <p id="commentDrawerSubtitle">View latest replies and pinned answers</p>
            </div>
            <button type="button" class="comment-drawer-close" id="commentDrawerClose"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="comment-drawer-body" id="commentDrawerBody">
            <section class="comment-drawer-section">
                <h4>Pinned answers</h4>
                <div class="drawer-pinned-comments" id="drawerPinnedComments"></div>
            </section>
            <section class="comment-drawer-section">
                <h4>Conversation</h4>
                <div class="comment-thread" id="commentThread"></div>
            </section>
        </div>
        <div class="comment-drawer-footer">
            <input type="text" id="commentDrawerInput" placeholder="Write a reply..." aria-label="Write a reply" />
            <button type="button" id="commentDrawerSend"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </aside>

    <!-- Image Modal -->
    <div class="image-modal" id="announcementImageModal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="image-modal-content">
            <div class="image-modal-header">
                <div class="image-modal-header-text">
                    <span class="image-modal-badge">Announcement</span>
                    <h2 class="image-modal-title" id="modalImageTitle">Announcement</h2>
                    <p class="image-modal-description" id="modalImageDescription">Open the image to view full screen, interact with the announcement, and access likes/comments.</p>
                </div>
                <button class="image-modal-close" type="button" onclick="closeAnnouncementModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="image-modal-list" id="modalImageList"></div>
        </div>
    </div>
    <?php include '../../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
