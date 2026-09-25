<?php
require_once __DIR__ . '/../../includes/session.php';
require_login();
$user = current_user();

$service = strtolower(trim($_GET['service'] ?? ''));
// Treat teacher head for SSC/Scholarship as combined mode when no explicit service provided
$isHeadSscScholarship = $user['role'] === 'teacher' && (($user['head_service'] ?? '') === 'ssc_scholarship');
if ($service === '' && $isHeadSscScholarship) { $service = 'ssc/scholarship'; }
$isScholarshipService = $service === 'scholarship';
$isGuidanceService = $service === 'guidance';
$isLibraryService = $service === 'library';
$isClinicService = $service === 'clinic';
// Combined SSC/Scholarship support
$isSscScholarshipService = $service === 'ssc/scholarship' || ($service === 'ssc' && $isHeadSscScholarship);
$sscScholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'ssc';
$scholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'scholarship';

if (!in_array($user['role'], ['student', 'teacher', 'admin'], true)) {
    header('Location: /auth/student_login.php');
    exit;
}
if ($user['role'] === 'teacher' && $user['head_service'] !== 'none' && !$isScholarshipService && !$isGuidanceService && !$isLibraryService && !$isClinicService && !$isSscScholarshipService) {
    header('Location: ../ssc_head_home.php');
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
    <title>Scholarship Dashboard | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="../../assets/css/responsive.css">
    <meta name="theme-color" content="#800000">
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
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar" <?= $isScholarshipService ? 'onclick="toggleGuidanceSidebar()"' : '' ?>>
                    <i class="fa-solid fa-bars"></i>
                </button>
                <!-- search button removed per request -->
            </div>
            <div class="nav-section">
                <?php if ($isGuidanceService): ?>
                    <a href="../guidance_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="../school_announcements.php?service=guidance" data-tooltip="Announcements">
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
                            <a href="../guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="../library_dashboard.php?service=guidance" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="../clinic_dashboard.php?service=guidance" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="../ssc/ssc_dashboard.php?service=guidance" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=guidance" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="../ssaa_student_home.php?service=guidance" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
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
                    <a href="../profile.php?service=guidance" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="../about.php?service=guidance" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isSscScholarshipService): ?>
                    <a href="../ssc_head_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="../school_announcements.php?service=ssc/scholarship" data-tooltip="Announcements">
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
                            <a href="scholarship_dashboard.php?service=ssc/scholarship" data-tooltip="Scholarship">
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
                            <a href="../ssc/ssc_manage_events.php" data-tooltip="SSC Events">
                                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                                <span class="nav-text">SSC Events</span>
                            </a>
                            <a href="../ssc/ssc_manage_candidates.php" data-tooltip="Candidates">
                                <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                                <span class="nav-text">Candidates</span>
                            </a>
                            <a href="../ssc/ssc_reports.php" data-tooltip="Reports & Analytics">
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
                            <a href="scholarship_create_announcement.php" data-tooltip="Scholarship Announcements">
                                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                <span class="nav-text">Scholarship Announcements</span>
                            </a>
                            <a href="scholarship_reports.php" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="../profile.php?service=ssc/scholarship" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="../about.php?service=ssc/scholarship" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isScholarshipService): ?>
                    <a href="../ssc_head_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="../school_announcements.php?service=scholarship" data-tooltip="Announcements">
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
                            <a href="../guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="../library_dashboard.php?service=library" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="../clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="../ssc_head_home.php?service=ssc" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=scholarship" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="../ssaa_student_home.php?service=alumni" data-tooltip="Alumni">
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
                            <a href="scholarship_create_announcement.php" data-tooltip="Scholarship Announcements">
                                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                <span class="nav-text">Scholarship Announcements</span>
                            </a>
                            <a href="scholarship_reports.php" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="../profile.php?service=scholarship" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="../about.php?service=scholarship" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isLibraryService): ?>
                    <a href="../librarian_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="../school_announcements.php?service=library" data-tooltip="Announcements">
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
                            <a href="../guidance_dashboard.php?service=library" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="../library_dashboard.php?service=library" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="../clinic_dashboard.php?service=library" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="../ssc/ssc_dashboard.php?service=library" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=library" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="../ssaa_student_home.php?service=library" data-tooltip="Alumni">
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
                                <span class="nav-icon"><i class="fa-solid fa-code"></i></span>
                                <span class="nav-text">Library QR</span>
                            </a>
                        </div>
                    </div>
                    <a href="../profile.php?service=library" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="../about.php?service=library" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isClinicService): ?>
                    <a href="../nurse_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="../school_announcements.php?service=clinic" data-tooltip="Announcements">
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
                            <a href="../guidance_dashboard.php?service=clinic" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="../library_dashboard.php?service=clinic" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="../clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="../ssc/ssc_dashboard.php?service=clinic" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=clinic" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="../ssaa_student_home.php?service=clinic" data-tooltip="Alumni">
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
                <?php else: ?>
                    <a href="<?= htmlspecialchars($dashboardLink) ?>" data-tooltip="Dashboard">
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
                            <a href="../guidance_dashboard.php" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="../library_dashboard.php" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="../clinic_dashboard.php" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="../ssc/ssc_dashboard.php" data-tooltip="Supreme Student Council">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">Supreme Student Council</span>
                            </a>
                            <a href="scholarship_dashboard.php" class="active" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="../ssaa_student_home.php" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <a href="../profile.php" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="../about.php" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="nav-footer">
                <a href="../../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <div id="pageOverlay" class="page-overlay"></div>
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
                            <p>Scholarship Services</p>
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
                    <!-- support button removed per request -->

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

                    <!-- support menu removed per request -->
                </div>
            </header>
            <div class="main-scroll">
                    <div class="service-page-header">
                        <div class="service-page-header-main">
                            <div class="service-page-header-icon"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i></div>
                            <div>
                                <p class="service-page-header-eyebrow">Campus Portal</p>
                                <h1>Scholarship Services</h1>
                                <p class="service-page-header-welcome">Welcome back, <strong><?= htmlspecialchars($user['full_name']) ?></strong></p>
                            </div>
                        </div>
                        <div class="service-page-header-center">
                            <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                            <div><strong>Support your goals</strong><span>Discover opportunities and keep track of your scholarship updates.</span></div>
                        </div>
                        <div class="service-page-header-profile">
                            <strong><?= htmlspecialchars($roleLabel) ?></strong>
                            <div><?= htmlspecialchars($topbarInfo['displayMeta']) ?></div>
                        </div>
                    </div>

                    <!-- Tabbed Interface -->
                    <div class="scholarship-tabs">
                        <div class="tab-buttons">
                            <button class="tab-button active" data-tab="view-announcements">View Announcements</button>
                            <?php if ($user['role'] === 'admin' || ($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship')): ?>
                            <button class="tab-button" data-tab="create-announcement">Create Announcement</button>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'student'): ?>
                            <button class="tab-button" data-tab="document-validation">Document Validation</button>
                            <button class="tab-button" data-tab="my-applications">My Applications</button>
                            <?php else: ?>
                            <button class="tab-button" data-tab="application-tracker">Application Tracker</button>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'admin' || ($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship')): ?>
                            <button class="tab-button" data-tab="post-updates">Post Updates</button>
                            <button class="tab-button" data-tab="reports">Reports & Analytics</button>
                            <?php endif; ?>
                        </div>

                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- View Announcements Tab -->
                            <div id="view-announcements-tab" class="tab-pane active">
                                <div class="tab-header">
                                    <h2 class="section-caption">View Announcements</h2>
                                    <p>Current scholarship notices, deadlines, and requirements.</p>
                                </div>
                                <div id="announcements-container">
                                    <!-- Announcements will be loaded here -->
                                </div>
                            </div>

                            <!-- Create Announcement Tab (Admin/SSC Head Only) -->
                            <?php if ($user['role'] === 'admin' || ($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship')): ?>
                            <div id="create-announcement-tab" class="tab-pane">
                                <div class="tab-header">
                                    <h2 class="section-caption">Create Announcement</h2>
                                    <p>Publish a new scholarship announcement with requirements, images, and deadlines.</p>
                                </div>
                                <div class="management-grid">
                                    <div class="management-card" onclick="showAnnouncementModal()">
                                        <div class="management-card-icon">
                                            <i class="fa-solid fa-bullhorn"></i>
                                        </div>
                                        <h3>Open Announcement Form</h3>
                                        <p>Use the form to create a scholarship announcement.</p>
                                        <button class="management-button">Open Form</button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Document Validation Tab (Students Only) -->
                            <div id="document-validation-tab" class="tab-pane">
                                <div class="tab-header">
                                    <h2 class="section-caption">Document Validation</h2>
                                    <p>View the status of your submitted documents for scholarship applications.</p>
                                </div>
                                <div id="validation-container">
                                    <!-- Document validations will be loaded here -->
                                </div>
                            </div>

                            <!-- My Applications Tab (Students Only) -->
                            <div id="my-applications-tab" class="tab-pane">
                                <div class="tab-header">
                                    <h2 class="section-caption">My Applications</h2>
                                    <p>Track your scholarship applications and their current status.</p>
                                </div>
                                <div id="applications-container">
                                    <!-- Applications will be loaded here -->
                                </div>
                            </div>

                            <!-- Application Tracker Tab (Admin/SSC Head Only) -->
                            <?php if ($user['role'] === 'admin' || ($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship')): ?>
                            <div id="application-tracker-tab" class="tab-pane">
                                <div class="tab-header">
                                    <h2 class="section-caption">Application Tracker</h2>
                                    <p>Monitor scholarship submission status for all students.</p>
                                </div>
                                <div id="tracker-container">
                                    <!-- Applications will be loaded here -->
                                </div>
                            </div>

                            <!-- Post Updates Tab (Admin/SSC Head Only) -->
                            <div id="post-updates-tab" class="tab-pane">
                                <div class="tab-header">
                                    <h2 class="section-caption">Post Updates</h2>
                                    <p>Share scholarship deadlines, program details, and assistance announcements.</p>
                                </div>
                                <div class="form-grid">
                                    <form id="inlineUpdateForm">
                                        <div class="form-group">
                                            <label for="inlineUpdateTitle">Title *</label>
                                            <input type="text" id="inlineUpdateTitle" name="title" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="inlineUpdateContent">Content *</label>
                                            <textarea id="inlineUpdateContent" name="content" required></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="inlineUpdateType">Update Type</label>
                                            <select id="inlineUpdateType" name="update_type">
                                                <option value="deadline">Deadline Update</option>
                                                <option value="program_info">Program Information</option>
                                                <option value="financial_assistance">Financial Assistance</option>
                                                <option value="general">General Update</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn-primary">Post Update</button>
                                    </form>
                                </div>
                                <div class="scholarship-updates" style="margin-top:24px;" id="updates-container">
                                    <!-- Updates will be loaded here -->
                                </div>
                            </div>

                            <!-- Reports Tab (Admin/SSC Head Only) -->
                            <div id="reports-tab" class="tab-pane">
                                <div class="tab-header">
                                    <h2 class="section-caption">Reports & Analytics</h2>
                                    <p>View key scholarship metrics and application summaries.</p>
                                </div>
                                <div class="stats-grid" id="reports-summary">
                                    <div class="stat-card">
                                        <div class="stat-icon"><i class="fa-solid fa-bullhorn"></i></div>
                                        <div class="stat-content">
                                            <h4>Announcements</h4>
                                            <p class="stat-number" id="report-announcements">0</p>
                                            <p class="stat-label">Total published</p>
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon"><i class="fa-solid fa-file-circle-check"></i></div>
                                        <div class="stat-content">
                                            <h4>Applications</h4>
                                            <p class="stat-number" id="report-applications">0</p>
                                            <p class="stat-label">Total submitted</p>
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
                                        <div class="stat-content">
                                            <h4>Updates</h4>
                                            <p class="stat-number" id="report-updates">0</p>
                                            <p class="stat-label">Total posted</p>
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                                        <div class="stat-content">
                                            <h4>Ready Status</h4>
                                            <p class="stat-number" id="report-ready-count">0</p>
                                            <p class="stat-label">Ready to submit</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
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

    <!-- Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="announcementModalTitle">Create Announcement</h3>
                <button class="modal-close" onclick="closeModal('announcementModal')">&times;</button>
            </div>
            <form id="announcementForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="announcementId" name="id">
                    <div class="form-group">
                        <label for="announcementTitle">Title *</label>
                        <input type="text" id="announcementTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="announcementContent">Content *</label>
                        <textarea id="announcementContent" name="content" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="programType">Program Type</label>
                        <select id="programType" name="program_type">
                            <option value="">Select Program</option>
                            <option value="TDP">TDP (Tulong Dunong Program)</option>
                            <option value="TES">TES (Tulong Edukasyon sa SHS)</option>
                            <option value="financial_aid">Financial Aid</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="deadline">Deadline (Optional)</label>
                        <input type="date" id="deadline" name="deadline">
                    </div>
                    <div class="form-group">
                        <label for="announcementImages">Images</label>
                        <input type="file" id="announcementImages" name="images[]" multiple accept="image/png,image/jpeg,image/webp">
                        <small>Upload document checklists or requirement images</small>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('announcementModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Post Update</h3>
                <button class="modal-close" onclick="closeModal('updateModal')">&times;</button>
            </div>
            <form id="updateForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="updateTitle">Title *</label>
                        <input type="text" id="updateTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="updateContent">Content *</label>
                        <textarea id="updateContent" name="content" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="updateType">Update Type</label>
                        <select id="updateType" name="update_type">
                            <option value="deadline">Deadline Update</option>
                            <option value="program_info">Program Information</option>
                            <option value="financial_assistance">Financial Assistance</option>
                            <option value="general">General Update</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('updateModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Post Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification System -->
    <!-- Validation Feedback Modal -->
    <div id="validation-feedback-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; border-radius: 8px; padding: 0; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="padding: 20px; border-bottom: 1px solid #ddd;">
                <h2 style="margin: 0;">Validation Feedback</h2>
            </div>
            <div id="feedback-modal-body" style="padding: 20px;">
                <!-- Feedback content will be loaded here -->
            </div>
            <div style="padding: 15px; border-top: 1px solid #ddd; text-align: right;">
                <button onclick="document.getElementById('validation-feedback-modal').style.display='none';" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Close</button>
            </div>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div id="imageModal" class="modal image-modal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="modal-content image-modal-content">
            <div class="image-modal-header">
                <div class="image-modal-header-text">
                    <span class="image-modal-badge">Scholarship</span>
                    <h2 class="image-modal-title" id="modalImageTitle">Scholarship Image</h2>
                    <p class="image-modal-description" id="modalImageDescription">Open the image to view the scholarship details, share feedback, and open comments.</p>
                </div>
                <button class="image-modal-close" type="button" onclick="closeImageModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="image-modal-list" id="modalImageList">
                <div class="image-modal-slide">
                    <img id="modalImage" src="" alt="Full size image">
                </div>
            </div>
            <div class="image-modal-actions">
                <button class="modal-like-button" type="button"><i class="fa-regular fa-heart"></i> Like</button>
                <button class="modal-comment-button" type="button" onclick="openCommentDrawer(currentModalContext?.title || 'Comments', currentModalContext?.type || '', currentModalContext?.id || '', true)"><i class="fa-regular fa-comment"></i> Comment</button>
            </div>
        </div>
    </div>

    <div class="comment-drawer-overlay" id="commentDrawerOverlay"></div>
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

    <script src="../../assets/js/app.js" defer></script>
    <script>
        let announcementsData = [];
        let applicationsData = [];
        let updatesData = [];

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

            // Scholarship dashboard tab and data initialization
            bindTabs();
            loadAnnouncements();
            loadUpdates();
            <?php if ($user['role'] === 'student'): ?>
            loadMyApplications();
            <?php elseif ($user['role'] === 'admin' || ($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship')): ?>
            loadAllApplications();
            <?php endif; ?>
            applyInitialTabFromHash();

            // Form Submissions - moved inside DOMContentLoaded to ensure DOM is ready
            document.getElementById('announcementForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                try {
                    const response = await fetch('../scholarship_api.php?action=save_announcement', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        showNotification('Announcement saved successfully!', 'success');
                        closeModal('announcementModal');
                        loadAnnouncements();
                    } else {
                        showNotification(result.message || 'Error saving announcement', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Error saving announcement', 'error');
                }
            });

            document.getElementById('updateForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const data = Object.fromEntries(formData);

                try {
                    const response = await fetch('../scholarship_api.php?action=save_update', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();
                    if (result.success) {
                        showNotification('Update posted successfully!', 'success');
                        closeModal('updateModal');
                        loadUpdates();
                    } else {
                        showNotification(result.message || 'Error posting update', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Error posting update', 'error');
                }
            });

            const inlineUpdateForm = document.getElementById('inlineUpdateForm');
            if (inlineUpdateForm) {
                inlineUpdateForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const data = Object.fromEntries(formData);

                    try {
                        const response = await fetch('../scholarship_api.php?action=save_update', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(data)
                        });
                        const result = await response.json();
                        if (result.success) {
                            showNotification('Update posted successfully!', 'success');
                            this.reset();
                            loadUpdates();
                        } else {
                            showNotification(result.message || 'Error posting update', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showNotification('Error posting update', 'error');
                    }
                });
            }

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

        });

        function bindTabs() {
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabName = this.dataset.tab;
                    showTab(tabName);
                });
            });

            window.addEventListener('hashchange', () => {
                const hash = window.location.hash.replace('#', '');
                if (hash) {
                    showTab(hash);
                }
            });
        }

        function applyInitialTabFromHash() {
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                showTab(hash);
            }
        }

        // Tab switching functionality
        function showTab(tabName) {
            const tabPanes = document.querySelectorAll('.tab-pane');
            tabPanes.forEach(pane => pane.classList.remove('active'));

            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));

            const selectedTab = document.getElementById(`${tabName}-tab`);
            const selectedButton = document.querySelector(`.tab-button[data-tab="${tabName}"]`);

            if (selectedTab) {
                selectedTab.classList.add('active');
            }
            if (selectedButton) {
                selectedButton.classList.add('active');
            }

            if (window.location.hash.replace('#', '') !== tabName) {
                window.location.hash = tabName;
            }

            if (tabName === 'view-announcements') {
                renderAnnouncements();
                initializeSavedInteractions(); // Initialize interactions after rendering
            }
            if (tabName === 'my-applications') {
                loadMyApplications();
            }
            if (tabName === 'document-validation') {
                loadDocumentValidations();
            }
            if (tabName === 'application-tracker') {
                loadAllApplications();
            }
            if (tabName === 'post-updates') {
                renderUpdates();
            }
            if (tabName === 'reports') {
                renderReports();
            }
        }

        // Announcements
        async function loadAnnouncements() {
            try {
                const response = await fetch('../scholarship_api.php?action=get_announcements');
                const data = await response.json();
                announcementsData = data.announcements || [];
                renderAnnouncements();
                initializeSavedInteractions(); // Initialize interactions after rendering
                renderReports();
            } catch (error) {
                console.error('Error loading announcements:', error);
            }
        }

        function renderAnnouncements() {
            const container = document.getElementById('announcements-container');
            if (!container) return;
            if (!announcementsData.length) {
                container.innerHTML = '<div class="scholarship-announcement"><div class="announcement-content"><p>No announcements available at this time.</p></div></div>';
                return;
            }

            container.innerHTML = announcementsData.map((announcement, index) => {
                const content = announcement.content; // Keep HTML content as is
                const safeTitle = announcement.title ? announcement.title.replace(/\"/g, '&quot;') : 'Announcement';
                const safeDescription = String(announcement.content || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 160);

                return `
                    <div class="announcement-card scholarship" data-type="scholarship" data-id="${announcement.id}">
                        <div class="announcement-identity">
                            <div class="announcement-avatar"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                            <div class="announcement-author-block">
                                <div class="announcement-author">Scholarship Office</div>
                                <div class="announcement-source-meta">
                                    <span><i class="fa-solid fa-clock"></i> ${announcement.created_at ? new Date(announcement.created_at).toLocaleDateString() : ''}</span>
                                    <span><i class="fa-solid fa-globe"></i> Public</span>
                                </div>
                            </div>
                        </div>
                        <div class="announcement-header">
                            <h3 class="announcement-title">${announcement.title}</h3>
                            <span class="announcement-badge scholarship">Scholarship</span>
                        </div>
                        <div class="announcement-meta">
                            <span class="announcement-meta-item"><i class="fa-solid fa-tag"></i> ${announcement.program_type || 'General'}</span>
                            ${announcement.deadline ? `<span class="announcement-meta-item"><i class="fa-solid fa-hourglass-end"></i> Deadline: ${new Date(announcement.deadline).toLocaleDateString()}</span>` : ''}
                        </div>
                        <div class="announcement-description${announcement.images && announcement.images.length ? ' has-image collapsed' : ''}">
                            <div class="announcement-text-content" id="content-${announcement.id}">
                                ${content}
                            </div>
                            <button class="see-more-btn" id="see-more-${announcement.id}" style="display: none;" onclick="toggleAnnouncementContent(${announcement.id})">See More</button>
                            ${announcement.images && announcement.images.length ? `
                                <div class="image-carousel-container">
                                    <div class="image-carousel" id="carousel-${index}">
                                        ${announcement.images.map((img, imgIndex) => `
                                            <div class="carousel-slide" data-slide="${imgIndex}">
                                                <img src="${img.startsWith('../') ? '../' + img : img}" 
                                                     alt="Announcement image ${imgIndex + 1}" 
                                                     onclick="openImageModal('${(img.startsWith('../') ? '../' + img : img).replace(/'/g, "\\'")}', 'scholarship', '${announcement.id}', '${safeTitle.replace(/'/g, "\\'")}', '${safeDescription.replace(/'/g, "\\'")}')"
                                                     class="carousel-image"
                                                     style="cursor: pointer;">
                                            </div>
                                        `).join('')}
                                    </div>
                                    ${announcement.images.length > 1 ? `
                                        <button class="carousel-btn carousel-prev" onclick="previousSlide(${index})">
                                            <i class="fa-solid fa-chevron-left"></i>
                                        </button>
                                        <button class="carousel-btn carousel-next" onclick="nextSlide(${index})">
                                            <i class="fa-solid fa-chevron-right"></i>
                                        </button>
                                        <div class="carousel-dots" id="dots-${index}">
                                            ${announcement.images.map((_, imgIndex) => `
                                                <span class="dot ${imgIndex === 0 ? 'active' : ''}" onclick="goToSlide(${index}, ${imgIndex})"></span>
                                            `).join('')}
                                        </div>
                                    ` : ''}
                                </div>
                            ` : ''}
                            <div class="announcement-actions">
                                <button type="button" class="like-button"><i class="fa-regular fa-heart"></i> Like <span class="heart-count">0</span></button>
                                <button type="button" class="comment-button"><i class="fa-regular fa-comment"></i> Comment</button>
                            </div>
                            <button type="button" class="view-all-comments" data-comments="[]" data-count="0" data-title="${safeTitle}">View all 0 comments</button>
                            <div class="comment-preview-list">
                                <div class="comment-preview no-comments">No comments yet. Be the first to reply.</div>
                            </div>
                            <?php if ($user['role'] === 'student'): ?>
                            <button class="ready-submit-btn" onclick="markReadyToSubmit(${announcement.id})" id="ready-btn-${announcement.id}">
                                <i class="fa-solid fa-hand-holding-dollar"></i> Mark as Ready to Submit
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                `;
            }).join('');

            // After rendering, check which announcements need "see more"
            setTimeout(() => {
                document.querySelectorAll('.announcement-text-content').forEach(el => {
                    const computedStyle = getComputedStyle(el);
                    const lineHeight = parseFloat(computedStyle.lineHeight);
                    const maxHeightPx = 3 * lineHeight;
                    if (el.scrollHeight > maxHeightPx) {
                        el.style.maxHeight = maxHeightPx + 'px';
                        el.style.overflow = 'hidden';
                        el.nextElementSibling.style.display = 'block';
                    }
                });
            }, 0);

            setupAnnouncementInteractions();
            initializeSavedInteractions();
            refreshCommentTimestamps();
        }

        async function loadUpdates() {
            try {
                const response = await fetch('../scholarship_api.php?action=get_updates');
                const data = await response.json();
                updatesData = data.updates || [];
                renderUpdates();
                renderReports();
            } catch (error) {
                console.error('Error loading updates:', error);
            }
        }

        function renderUpdates() {
            const container = document.getElementById('updates-container');
            if (!container) return;
            if (!updatesData.length) {
                container.innerHTML = '<div class="scholarship-updates"><p>No updates available.</p></div>';
                return;
            }

            container.innerHTML = `
                <div class="scholarship-updates">
                    ${updatesData.map(update => `
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h4 class="update-title">${update.title}</h4>
                                <p>${update.content}</p>
                                <div class="update-meta">
                                    <span><i class="fa-solid fa-tag"></i> ${update.update_type}</span>
                                    <span><i class="fa-solid fa-clock"></i> ${new Date(update.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        function renderReports() {
            const announcementCount = announcementsData.length;
            const applicationCount = applicationsData.length;
            const updateCount = updatesData.length;
            const readyCount = applicationsData.filter(app => app.status === 'ready').length;

            const announcementsLabel = document.getElementById('report-announcements');
            const applicationsLabel = document.getElementById('report-applications');
            const updatesLabel = document.getElementById('report-updates');
            const readyLabel = document.getElementById('report-ready-count');

            if (announcementsLabel) announcementsLabel.textContent = announcementCount;
            if (applicationsLabel) applicationsLabel.textContent = applicationCount;
            if (updatesLabel) updatesLabel.textContent = updateCount;
            if (readyLabel) readyLabel.textContent = readyCount;
        }

        // Applications
        async function loadMyApplications() {
            try {
                const response = await fetch('../scholarship_api.php?action=get_applications');
                const data = await response.json();
                applicationsData = data.applications || [];
                displayMyApplications(applicationsData);
                renderReports();
            } catch (error) {
                console.error('Error loading applications:', error);
            }
        }

        async function loadAllApplications() {
            try {
                const response = await fetch('../scholarship_api.php?action=get_applications');
                const data = await response.json();
                applicationsData = data.applications || [];
                displayAllApplications(applicationsData);
                renderReports();
            } catch (error) {
                console.error('Error loading applications:', error);
            }
        }

        // Document Validations
        async function loadDocumentValidations() {
            try {
                const response = await fetch('../scholarship_api.php?action=get_validations', {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                displayDocumentValidations(data.validations || []);
            } catch (error) {
                console.error('Error loading document validations:', error);
            }
        }

        function showValidationFeedback(validationId) {
            const modal = document.getElementById('validation-feedback-modal');
            if (!modal) {
                console.error('Feedback modal not found');
                return;
            }
            
            const feedbackBody = document.getElementById('feedback-modal-body');
            feedbackBody.innerHTML = '<div style="text-align: center; padding: 30px;">Loading feedback...</div>';
            modal.style.display = 'flex';
            
            // Fetch and display feedback for this validation
            fetch('../../includes/api_scholarship_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get',
                    validation_id: validationId,
                    student_id: '<?= $_SESSION['user_id'] ?? 0 ?>'
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    // Show existing feedback
                    const fb = data.data;
                    feedbackBody.innerHTML = `
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Process Rating</label>
                                <div style="font-size: 24px; color: #3b82f6;">${fb.process_rating}/5 ${'★'.repeat(fb.process_rating)}</div>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Support Rating</label>
                                <div style="font-size: 24px; color: #10b981;">${fb.support_rating}/5 ${'★'.repeat(fb.support_rating)}</div>
                            </div>
                            ${fb.review_text ? `<div style="margin-bottom: 15px;"><label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Your Review</label><p style="background: white; padding: 10px; border-radius: 4px;">${fb.review_text}</p></div>` : ''}
                            ${fb.feedback_text ? `<div><label style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Additional Feedback from Staff</label><p style="background: white; padding: 10px; border-radius: 4px; color: #666;">${fb.feedback_text}</p></div>` : ''}
                            <div style="margin-top: 15px; font-size: 12px; color: #999;">Submitted: ${new Date(fb.created_at).toLocaleDateString()}</div>
                        </div>
                    `;
                } else {
                    // Show form to submit feedback
                    feedbackBody.innerHTML = `
                        <div>
                            <p style="color: #666; margin-bottom: 20px;">Rate your experience and provide feedback:</p>
                            <form id="feedback-form" style="display: flex; flex-direction: column; gap: 15px;">
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: bold;">How would you rate the process? (1-5 stars)</label>
                                    <div style="display: flex; gap: 8px; font-size: 28px;">
                                        <span class="rating-star" data-rating="1" data-for="process_rating" style="cursor: pointer; color: #ddd;">★</span>
                                        <span class="rating-star" data-rating="2" data-for="process_rating" style="cursor: pointer; color: #ddd;">★</span>
                                        <span class="rating-star" data-rating="3" data-for="process_rating" style="cursor: pointer; color: #ddd;">★</span>
                                        <span class="rating-star" data-rating="4" data-for="process_rating" style="cursor: pointer; color: #ddd;">★</span>
                                        <span class="rating-star" data-rating="5" data-for="process_rating" style="cursor: pointer; color: #ddd;">★</span>
                                    </div>
                                    <input type="hidden" id="process_rating" name="process_rating" value="0">
                                    <span id="process_rating_text" style="font-size: 12px; color: #999;">Click to rate</span>
                                </div>

                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: bold;">How would you rate the support received? (1-5 stars)</label>
                                    <div style="display: flex; gap: 8px; font-size: 28px;">
                                        <span class="rating-star" data-rating="1" data-for="support_rating" style="cursor: pointer; color: #ddd;">★</span>
                                        <span class="rating-star" data-rating="2" data-for="support_rating" style="cursor: pointer; color: #ddd;">★</span>
                                        <span class="rating-star" data-rating="3" data-for="support_rating" style="cursor: pointer; color: #ddd;">★</span>
                                        <span class="rating-star" data-rating="4" data-for="support_rating" style="cursor: pointer; color: #ddd;">★</span>
                                        <span class="rating-star" data-rating="5" data-for="support_rating" style="cursor: pointer; color: #ddd;">★</span>
                                    </div>
                                    <input type="hidden" id="support_rating" name="support_rating" value="0">
                                    <span id="support_rating_text" style="font-size: 12px; color: #999;">Click to rate</span>
                                </div>

                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: bold;">Additional Comments (Optional)</label>
                                    <textarea id="review_text" name="review_text" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;" rows="4" placeholder="Share your feedback..."></textarea>
                                </div>

                                <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-weight: bold;">Submit Feedback</button>
                            </form>
                        </div>
                    `;

                    // Setup rating interaction
                    document.querySelectorAll('.rating-star').forEach(star => {
                        star.addEventListener('click', function() {
                            const rating = this.getAttribute('data-rating');
                            const field = this.getAttribute('data-for');
                            document.getElementById(field).value = rating;
                            
                            // Update visual state
                            document.querySelectorAll(`.rating-star[data-for="${field}"]`).forEach((s, idx) => {
                                s.style.color = idx < rating ? '#3b82f6' : '#ddd';
                            });
                            document.getElementById(field + '_text').textContent = rating + ' / 5';
                        });
                    });

                    // Handle form submission
                    document.getElementById('feedback-form').addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const processRating = document.getElementById('process_rating').value;
                        const supportRating = document.getElementById('support_rating').value;
                        const reviewText = document.getElementById('review_text').value;

                        if (!processRating || !supportRating) {
                            alert('Please rate both criteria');
                            return;
                        }

                        // Submit feedback
                        fetch('../../includes/api_scholarship_feedback.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'save',
                                validation_id: validationId,
                                student_id: '<?= $_SESSION['user_id'] ?? 0 ?>',
                                process_rating: parseInt(processRating),
                                support_rating: parseInt(supportRating),
                                review_text: reviewText
                            }),
                            credentials: 'same-origin'
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                alert('Thank you! Your feedback has been submitted.');
                                document.getElementById('validation-feedback-modal').style.display = 'none';
                                // Refresh the applications list
                                if (typeof loadApplications === 'function') loadApplications();
                            } else {
                                alert('Error: ' + (data.message || 'Could not save feedback'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error saving feedback');
                        });
                    });
                }
            })
            .catch(error => {
                console.error('Error loading feedback:', error);
                feedbackBody.innerHTML = '<div style="padding: 20px; text-align: center; color: red;">Error loading feedback</div>';
            });
        }

        function displayDocumentValidations(validations) {
            const container = document.getElementById('validation-container');
            if (!validations.length) {
                container.innerHTML = '<div class="validation-status"><p>No document validations recorded yet.</p></div>';
                return;
            }

            container.innerHTML = validations.map(validation => `
                <div class="validation-status">
                    <h4>Validation for ${validation.announcement_title}</h4>
                    <p><strong>Validated by:</strong> ${validation.validator_name}</p>
                    <p><strong>Date:</strong> ${new Date(validation.validated_at).toLocaleDateString()}</p>
                    ${validation.notes ? `<p><strong>Notes:</strong> ${validation.notes}</p>` : ''}
                    <button class="feedback-button" onclick="showValidationFeedback(${validation.id})">
                        <i class="fa-solid fa-star"></i> Rate & Review
                    </button>
                </div>
            `).join('');
        }

        function displayMyApplications(applications) {
            const container = document.getElementById('applications-container');
            if (!container) {
                console.warn('Applications container not found');
                return;
            }
            if (!applications.length) {
                container.innerHTML = '<div class="application-status"><p>You have no applications yet. Mark announcements as "Ready to Submit" to start the process.</p></div>';
                return;
            }

            container.innerHTML = applications.map(app => `
                <div class="application-status">
                    <h4>Application for ${app.announcement_title || 'Scholarship'}</h4>
                    <p><strong>Status:</strong> <span class="status-badge status-${app.status}">${app.status}</span></p>
                    ${app.comments ? `<p><strong>Comments:</strong> ${app.comments}</p>` : ''}
                    <p><strong>Submitted:</strong> ${new Date(app.created_at).toLocaleDateString()}</p>
                    ${app.ready_timestamp ? `<p><strong>Ready to Submit:</strong> ${new Date(app.ready_timestamp).toLocaleDateString()}</p>` : ''}
                </div>
            `).join('');
        }

        function displayAllApplications(applications) {
            const container = document.getElementById('tracker-container');
            if (!container) {
                console.warn('Application tracker container is missing. Skipping displayAllApplications.');
                return;
            }
            if (!applications.length) {
                container.innerHTML = '<div class="application-status"><p>No applications submitted yet.</p></div>';
                return;
            }

            container.innerHTML = applications.map(app => `
                <div class="application-status">
                    <h4>${app.full_name} (${app.student_number})</h4>
                    <p><strong>Application:</strong> ${app.announcement_title || 'Scholarship'}</p>
                    <p><strong>Status:</strong>
                        <select onchange="updateApplicationStatus(${app.id}, this.value)">
                            <option value="pending" ${app.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="approved" ${app.status === 'approved' ? 'selected' : ''}>Approved</option>
                            <option value="rejected" ${app.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                            <option value="ready" ${app.status === 'ready' ? 'selected' : ''}>Ready to Submit</option>
                        </select>
                    </p>
                    <div class="form-group">
                        <label>Comments:</label>
                        <textarea onchange="updateApplicationComments(${app.id}, this.value)" placeholder="Add comments or feedback">${app.comments || ''}</textarea>
                    </div>
                    <p><strong>Submitted:</strong> ${new Date(app.created_at).toLocaleDateString()}</p>
                    ${app.ready_timestamp ? `<p><strong>Ready to Submit:</strong> ${new Date(app.ready_timestamp).toLocaleDateString()}</p>` : ''}
                </div>
            `).join('');
        }

        // Updates
        async function loadUpdates() {
            try {
                const response = await fetch('../scholarship_api.php?action=get_updates');
                const data = await response.json();
                updatesData = data.updates || [];
                renderUpdates();
                renderReports();
            } catch (error) {
                console.error('Error loading updates:', error);
            }
        }

        function displayUpdates(updates) {
            const container = document.getElementById('updates-container');
            if (!updates.length) {
                container.innerHTML = '<div class="scholarship-updates"><p>No updates available.</p></div>';
                return;
            }

            container.innerHTML = `
                <div class="scholarship-updates">
                    ${updates.map(update => `
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h4 class="update-title">${update.title}</h4>
                                <p>${update.content}</p>
                                <div class="update-meta">
                                    <span><i class="fa-solid fa-tag"></i> ${update.update_type}</span>
                                    <span><i class="fa-solid fa-clock"></i> ${new Date(update.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        // Modal Functions
        function showAnnouncementModal(announcementId = null) {
            const modal = document.getElementById('announcementModal');
            const form = document.getElementById('announcementForm');
            const title = document.getElementById('announcementModalTitle');

            if (announcementId) {
                title.textContent = 'Edit Announcement';
                // Load announcement data (to be implemented)
            } else {
                title.textContent = 'Create Announcement';
                form.reset();
            }

            modal.classList.add('show');
        }

        function showUpdateModal() {
            const modal = document.getElementById('updateModal');
            const form = document.getElementById('updateForm');
            form.reset();
            modal.classList.add('show');
        }

        function showBulkUpdateModal() {
            // Placeholder for bulk actions modal
            showNotification('Bulk actions feature coming soon!', 'info');
        }

        function showReportsModal() {
            // Placeholder for reports modal
            showNotification('Reports feature coming soon!', 'info');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
        }

        // Application Functions
        async function markReadyToSubmit(announcementId) {
            try {
                const response = await fetch('../scholarship_api.php?action=mark_ready_to_submit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ announcement_id: announcementId })
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Marked as ready to submit!', 'success');
                    const btn = document.getElementById(`ready-btn-${announcementId}`);
                    if (btn) {
                        btn.textContent = 'Ready to Submit ✓';
                        btn.classList.add('ready');
                        btn.disabled = true;
                    }
                    loadMyApplications();
                } else {
                    showNotification(result.message || 'Error marking as ready', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error marking as ready', 'error');
            }
        }

        async function updateApplicationStatus(applicationId, status) {
            try {
                const response = await fetch('../scholarship_api.php?action=save_application_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: applicationId, status: status })
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Status updated successfully!', 'success');
                } else {
                    showNotification(result.message || 'Error updating status', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error updating status', 'error');
            }
        }

        async function updateApplicationComments(applicationId, comments) {
            try {
                const response = await fetch('../scholarship_api.php?action=save_application_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: applicationId, comments: comments })
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Comments updated successfully!', 'success');
                } else {
                    showNotification(result.message || 'Error updating comments', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error updating comments', 'error');
            }
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatRelativeTime(timestamp) {
            const parsedTimestamp = Number(String(timestamp).trim());
            if (Number.isNaN(parsedTimestamp) || parsedTimestamp <= 0) {
                return 'Just now';
            }
            const diffMs = Date.now() - parsedTimestamp;
            const diffMin = Math.floor(diffMs / 60000);
            if (diffMin < 1) {
                return 'Just now';
            }
            if (diffMin < 60) {
                return `${diffMin}m ago`;
            }
            const diffHour = Math.floor(diffMin / 60);
            if (diffHour < 24) {
                return diffHour === 1 ? '1 hour ago' : `${diffHour} hours ago`;
            }
            const diffDay = Math.floor(diffHour / 24);
            return diffDay === 1 ? '1 day ago' : `${diffDay} days ago`;
        }

        function updateRelativeTimes() {
            document.querySelectorAll('.comment-time[data-timestamp]').forEach(span => {
                span.textContent = formatRelativeTime(span.dataset.timestamp);
            });
        }

        function refreshCommentTimestamps() {
            updateRelativeTimes();
            requestAnimationFrame(updateRelativeTimes);
        }

        setInterval(updateRelativeTimes, 5000);

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
            return { hasLiked: !!data.hasLiked, likeCount: Number(data.likeCount || 0) };
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
                heartIcon?.classList.remove('fa-regular');
                heartIcon?.classList.add('fa-solid');
            } else {
                button.classList.remove('liked');
                heartIcon?.classList.remove('fa-solid');
                heartIcon?.classList.add('fa-regular');
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
            previewButton.dataset.count = String(count);
            previewButton.textContent = `View all ${count} comments`;
            previewButton.dataset.title = card.querySelector('.announcement-title')?.textContent || '';

            if (count === 0) {
                previewList.innerHTML = '<div class="comment-preview no-comments">No comments yet. Be the first to reply.</div>';
                return;
            }

            previewList.innerHTML = comments.slice(0, 2).map(comment => {
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
                renderLikeButton(likeButton, status.hasLiked, status.likeCount);
                renderCommentPreview(card, comments);
            } catch (error) {
                console.error('Unable to load announcement interactions:', error);
            }
        }

        function initializeSavedInteractions() {
            document.querySelectorAll('.announcement-card').forEach(card => {
                const type = card.dataset.type;
                const id = card.dataset.id;
                if (!type || !id) return;

                loadCardInteractionState(card);

                const likeButton = card.querySelector('.like-button');
                const title = card.querySelector('.announcement-title')?.textContent || 'Comments';
                const commentButton = card.querySelector('.comment-button');
                const viewAllBtn = card.querySelector('.view-all-comments');

                if (likeButton) {
                    likeButton.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleLike(type, id, likeButton);
                    });
                }

                if (commentButton) {
                    commentButton.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openCommentDrawer(title, type, id);
                    });
                }
                if (viewAllBtn) {
                    viewAllBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openCommentDrawer(title, type, id);
                    });
                }
            });
        }

        function setupAnnouncementInteractions() {
            initializeSavedInteractions();
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

        function renderCommentThread(comments) {
            const threadContainer = document.getElementById('commentThread');
            if (!threadContainer) return;
            if (!comments.length) {
                threadContainer.innerHTML = '<div class="comment-item no-comments">No comments yet. Start the conversation.</div>';
                return;
            }

            threadContainer.innerHTML = comments.map(comment => {
                const deleteButton = comment.canDelete ? `<button type="button" class="comment-delete-button" aria-label="Delete comment"><i class="fa-solid fa-trash"></i></button>` : '';
                return `
                    <div class="comment-item" data-comment-id="${escapeHtml(String(comment.id))}">
                        <div class="comment-meta">
                            <span class="comment-author">${escapeHtml(buildCommentAuthor(comment))}</span>
                            <span class="comment-meta-actions">
                                <span class="comment-time">${escapeHtml(formatRelativeTime(comment.timestamp))}</span>
                                ${deleteButton}
                            </span>
                        </div>
                        <p class="comment-text">${escapeHtml(comment.text)}</p>
                    </div>
                `;
            }).join('');
        }

        function updateCommentDrawerTitle(count) {
            const drawerSubtitle = document.getElementById('commentDrawerSubtitle');
            if (drawerSubtitle) {
                drawerSubtitle.textContent = `Showing ${count} replies`;
            }
        }

        async function openCommentDrawer(title, type, id, isModal = false) {
            if (!type || !id) return;
            currentCommentContext = { type, id };
            const overlay = document.getElementById('commentDrawerOverlay');
            const drawer = document.getElementById('commentDrawer');
            const drawerTitle = document.getElementById('commentDrawerTitle');
            const commentInput = document.getElementById('commentDrawerInput');
            const pinnedContainer = document.getElementById('drawerPinnedComments');
            const modalContent = document.querySelector('.image-modal-content');

            drawerTitle.textContent = title;
            drawer.dataset.type = type;
            drawer.dataset.id = id;

            try {
                const comments = await fetchAnnouncementComments(type, id);
                renderCommentThread(comments);
                updateCommentDrawerTitle(comments.length);
                const card = document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`);
                renderCommentPreview(card, comments);
            } catch (error) {
                console.error('Unable to load comments:', error);
                renderCommentThread([]);
                updateCommentDrawerTitle(0);
            }

            if (pinnedContainer) {
                pinnedContainer.innerHTML = '';
            }

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
            if (commentInput) {
                commentInput.value = '';
                commentInput.focus();
            }
            updateRelativeTimes();
        }

        function closeCommentDrawer() {
            const overlay = document.getElementById('commentDrawerOverlay');
            const drawer = document.getElementById('commentDrawer');
            const modalContent = document.querySelector('.image-modal-content');
            if (drawer && drawer.classList.contains('modal-embedded') && modalContent && modalContent.contains(drawer)) {
                document.body.appendChild(drawer);
                modalContent.classList.remove('with-drawer');
            }
            overlay.classList.remove('open');
            if (drawer) {
                drawer.classList.remove('open', 'modal-embedded');
                drawer.setAttribute('aria-hidden', 'true');
            }
            overlay.style.display = '';
        }

        function createHeartBurst(button) {
            const bubble = document.createElement('span');
            bubble.className = 'heart-bubble';
            bubble.textContent = '+1';
            button.appendChild(bubble);
            setTimeout(() => {
                if (bubble.parentNode === button) {
                    button.removeChild(bubble);
                }
            }, 450);
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
                const card = document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`);
                renderCommentPreview(card, comments);
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
                const card = document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`);
                renderCommentPreview(card, comments);
            } catch (error) {
                console.error('Unable to delete comment:', error);
            }
        }

        document.addEventListener('click', function (event) {
            const deleteButton = event.target.closest('.comment-delete-button');
            if (deleteButton) {
                event.preventDefault();
                const commentItem = deleteButton.closest('.comment-item');
                const commentId = commentItem?.dataset.commentId;
                if (commentId) {
                    deleteComment(commentId);
                }
                return;
            }

            const replyButton = event.target.closest('.reply-button');
            if (replyButton) {
                event.preventDefault();
                const commentItem = replyButton.closest('.comment-item');
                const nameSpan = commentItem.querySelector('.comment-author');
                if (!nameSpan) return;
                const name = nameSpan.textContent.trim();
                const input = document.getElementById('commentDrawerInput');
                if (input) {
                    input.value = `@${name} `;
                    input.focus();
                    input.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });

        const sendButton = document.getElementById('commentDrawerSend');
        const commentInput = document.getElementById('commentDrawerInput');
        if (sendButton && commentInput && !sendButton.__scholarshipSendHandler) {
            sendButton.__scholarshipSendHandler = function () {
                const text = commentInput.value.trim();
                if (!text) {
                    return;
                }
                this.classList.add('loading');
                this.innerHTML = '<i class="fa-solid fa-spinner"></i>';
                submitComment().finally(() => {
                    this.classList.remove('loading');
                    this.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
                });
            };
            sendButton.addEventListener('click', sendButton.__scholarshipSendHandler);
        }

        const commentDrawerClose = document.getElementById('commentDrawerClose');
        const commentDrawerOverlay = document.getElementById('commentDrawerOverlay');
        if (commentDrawerClose) {
            commentDrawerClose.addEventListener('click', closeCommentDrawer);
        }
        if (commentDrawerOverlay) {
            commentDrawerOverlay.addEventListener('click', closeCommentDrawer);
        }

        // Notification System
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            container.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Carousel Functions
        function nextSlide(carouselIndex) {
            const carousel = document.getElementById(`carousel-${carouselIndex}`);
            const slides = carousel.querySelectorAll('.carousel-slide');
            const dots = document.getElementById(`dots-${carouselIndex}`);
            const dotElements = dots ? dots.querySelectorAll('.dot') : [];
            
            let currentSlide = 0;
            slides.forEach((slide, index) => {
                if (slide.classList.contains('active')) {
                    currentSlide = index;
                }
            });

            slides[currentSlide].classList.remove('active');
            dotElements[currentSlide].classList.remove('active');
            
            currentSlide = (currentSlide + 1) % slides.length;
            
            slides[currentSlide].classList.add('active');
            dotElements[currentSlide].classList.add('active');
            
            carousel.scrollLeft = slides[currentSlide].offsetLeft - carousel.offsetWidth / 2 + slides[currentSlide].offsetWidth / 2;
        }

        function previousSlide(carouselIndex) {
            const carousel = document.getElementById(`carousel-${carouselIndex}`);
            const slides = carousel.querySelectorAll('.carousel-slide');
            const dots = document.getElementById(`dots-${carouselIndex}`);
            const dotElements = dots ? dots.querySelectorAll('.dot') : [];
            
            let currentSlide = 0;
            slides.forEach((slide, index) => {
                if (slide.classList.contains('active')) {
                    currentSlide = index;
                }
            });

            slides[currentSlide].classList.remove('active');
            dotElements[currentSlide].classList.remove('active');
            
            currentSlide = (currentSlide - 1 + slides.length) % slides.length;
            
            slides[currentSlide].classList.add('active');
            dotElements[currentSlide].classList.add('active');
            
            carousel.scrollLeft = slides[currentSlide].offsetLeft - carousel.offsetWidth / 2 + slides[currentSlide].offsetWidth / 2;
        }

        function goToSlide(carouselIndex, slideIndex) {
            const carousel = document.getElementById(`carousel-${carouselIndex}`);
            const slides = carousel.querySelectorAll('.carousel-slide');
            const dots = document.getElementById(`dots-${carouselIndex}`);
            const dotElements = dots ? dots.querySelectorAll('.dot') : [];
            
            slides.forEach(slide => slide.classList.remove('active'));
            dotElements.forEach(dot => dot.classList.remove('active'));
            
            slides[slideIndex].classList.add('active');
            dotElements[slideIndex].classList.add('active');
            
            carousel.scrollLeft = slides[slideIndex].offsetLeft - carousel.offsetWidth / 2 + slides[slideIndex].offsetWidth / 2;
        }

        // Content toggle function for see more/less (Facebook-style)
        function toggleAnnouncementContent(announcementId) {
            const contentDiv = document.getElementById(`content-${announcementId}`);
            const seeMoreBtn = document.getElementById(`see-more-${announcementId}`);

            if (contentDiv.classList.contains('expanded')) {
                // Collapse content
                contentDiv.classList.remove('expanded');
                const computedStyle = getComputedStyle(contentDiv);
                const lineHeight = parseFloat(computedStyle.lineHeight);
                contentDiv.style.maxHeight = (3 * lineHeight) + 'px';
                contentDiv.style.overflow = 'hidden';
                seeMoreBtn.textContent = 'See More';
            } else {
                // Expand content
                contentDiv.classList.add('expanded');
                contentDiv.style.maxHeight = 'none';
                contentDiv.style.overflow = 'visible';
                seeMoreBtn.textContent = 'See Less';
            }
        }

        // Image Modal Functions
        let currentModalImages = [];
        let currentModalImageIndex = 0;
        let currentModalContext = null;
        let modalTouchStartX = 0;

        function openImageModal(imageSrc, type = '', id = '', title = '', description = '') {
            const modal = document.getElementById('imageModal');
            const titleEl = document.getElementById('modalImageTitle');
            const descriptionEl = document.getElementById('modalImageDescription');

            currentModalContext = { type, id, title, description };

            const announcement = announcementsData.find(a => String(a.id) === String(id) && type === 'scholarship');
            if (announcement && Array.isArray(announcement.images) && announcement.images.length) {
                currentModalImages = announcement.images.map(img => img.startsWith('../') ? '../' + img : img);
                const normalizedSrc = imageSrc.startsWith('../') ? imageSrc : imageSrc;
                currentModalImageIndex = currentModalImages.indexOf(normalizedSrc);
                if (currentModalImageIndex === -1) {
                    currentModalImageIndex = 0;
                }
            } else {
                currentModalImages = [imageSrc];
                currentModalImageIndex = 0;
            }

            if (titleEl) {
                titleEl.textContent = title || 'Scholarship Image';
            }
            if (descriptionEl) {
                descriptionEl.textContent = description || 'Open the image to view the scholarship details, share feedback, and open comments.';
            }

            renderModalImage();
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = 'auto';
        }

        function setupModalSwipe() {
            const modal = document.getElementById('imageModal');
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

        function renderModalImage() {
            const list = document.getElementById('modalImageList');
            if (!list) return;
            list.innerHTML = '';

            currentModalImages.forEach((src, index) => {
                const slide = document.createElement('div');
                slide.className = 'image-modal-slide';
                slide.style.display = index === currentModalImageIndex ? 'block' : 'none';

                const image = document.createElement('img');
                image.src = src;
                image.alt = 'Scholarship image';
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

                list.appendChild(slide);
            });
        }

        function showModalImage(index) {
            if (index < 0 || index >= currentModalImages.length) return;
            currentModalImageIndex = index;
            renderModalImage();
        }

        document.addEventListener('DOMContentLoaded', function () {
            setupModalSwipe();
            const imageModal = document.getElementById('imageModal');
            if (imageModal) {
                imageModal.addEventListener('click', function (event) {
                    if (event.target === this) {
                        closeImageModal();
                    }
                });
            }
        });

        // Close modal when clicking outside the image
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('imageModal');
            const modalContent = document.querySelector('.image-modal-content');
            if (event.target === modal) {
                closeImageModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
            }
        });

        // Handle search button click
        const searchBtn = document.querySelector('.nav-search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                alert('Search functionality coming soon!');
            });
        }
    </script>
    <?php include '../../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
