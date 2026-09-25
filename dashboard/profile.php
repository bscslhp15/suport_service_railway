<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();

$service = strtolower(trim($_GET['service'] ?? ''));
$isHeadSscScholarship = $user['role'] === 'teacher' && (($user['head_service'] ?? '') === 'ssc_scholarship');
if ($service === '' && $isHeadSscScholarship) {
    $service = 'ssc/scholarship';
}
$serviceMap = [
    'guidance' => 'guidance',
    'library' => 'library',
    'clinic' => 'clinic',
    'ssc' => 'ssc',
    'scholarship' => 'scholarship',
    'ssc/scholarship' => 'ssc/scholarship',
    'alumni' => 'alumni'
];
$currentService = $serviceMap[$service] ?? null;
$isGuidanceService = $service === 'guidance';
$isLibraryService = $service === 'library';
$isSscScholarshipService = $service === 'ssc/scholarship' || ($service === 'ssc' && $isHeadSscScholarship);
$sscScholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'ssc';
$scholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'scholarship';
$dashboardHome = $user['role'] === 'teacher' ? 'teacher_home.php' : 'student_home.php';
$portalLabel = $user['role'] === 'teacher' ? 'Teacher Portal' : 'Student Portal';
$dashboardClass = $user['role'] === 'teacher' ? 'teacher-dashboard' : 'student-dashboard';

if ($user['role'] !== 'student' && $user['role'] !== 'teacher') {
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
    $displayYear = 'Not assigned';
}
$topbarInfo = build_user_dashboard_header_info($user);

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (!$currentPassword || !$newPassword || !$confirmPassword) {
        $message = 'All password fields are required.';
        $messageType = 'error';
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
        $message = 'Current password is incorrect.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'New password must be at least 8 characters long.';
        $messageType = 'error';
    } else {
        $updated = update_user([
            'id' => $user['id'],
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
        if ($updated) {
            $message = 'Your password has been updated successfully.';
            $messageType = 'success';
            record_user_activity('Updated account password');
        } else {
            $message = 'Unable to update your password at this time. Please try again later.';
            $messageType = 'error';
        }
    }
}

$studentNotifications = [];
if (function_exists('get_active_clinic_announcements')) {
    $studentNotifications = get_active_clinic_announcements('students');
}
record_user_activity('Opened profile page');
$recentActivity = get_user_activity();
?><!DOCTYPE html>
<html lang="en">
<head>
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body class="<?= $dashboardClass ?> profile-page">
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
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar" <?= $currentService ? 'onclick="toggleGuidanceSidebar()"' : '' ?>>
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="nav-section">
                <?php if ($currentService === 'guidance'): ?>
                    <a href="guidance_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=guidance" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=guidance" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=guidance" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php?service=guidance" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=guidance" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=guidance" data-tooltip="Alumni">
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
                            <a href="case_management.php" data-tooltip="Case Management">
                                <span class="nav-icon"><i class="fa-solid fa-file-circle-plus"></i></span>
                                <span class="nav-text">Case Management</span>
                            </a>
                            <a href="good_moral.php" data-tooltip="Good Moral">
                                <span class="nav-icon"><i class="fa-solid fa-certificate"></i></span>
                                <span class="nav-text">Good Moral</span>
                            </a>
                            <a href="reports.php" data-tooltip="Reports">
                                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                                <span class="nav-text">Reports</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=guidance" class="active" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=guidance" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'library'): ?>
                    <a href="librarian_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=library" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
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
                                <span class="nav-icon"><i class="fa-solid fa-code"></i></span>
                                <span class="nav-text">Library QR</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=library" class="active" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=library" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'clinic'): ?>
                    <a href="nurse_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=clinic" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=clinic" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=clinic" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php?service=clinic" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=clinic" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=clinic" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Clinic</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="clinic_status.php" data-tooltip="Clinic Status">
                                <span class="nav-icon"><i class="fa-solid fa-clock"></i></span>
                                <span class="nav-text">Clinic Status</span>
                            </a>
                            <a href="clinic_health_records.php" data-tooltip="Health Records">
                                <span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>
                                <span class="nav-text">Health Records</span>
                            </a>
                            <a href="clinic_medicine_inventory.php" data-tooltip="Medicine Inventory">
                                <span class="nav-icon"><i class="fa-solid fa-pills"></i></span>
                                <span class="nav-text">Medicine Inventory</span>
                            </a>
                            <a href="clinic_visit_logs.php" data-tooltip="Visit Logs">
                                <span class="nav-icon"><i class="fa-solid fa-notes-medical"></i></span>
                                <span class="nav-text">Visit Logs</span>
                            </a>
                            <a href="clinic_announcements.php" data-tooltip="Announcements">
                                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                <span class="nav-text">Announcements</span>
                            </a>
                            <a href="clinic_analytics.php" data-tooltip="Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" class="active" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'ssc' || $isSscScholarshipService): ?>
                    <a href="ssc_head_home.php?service=ssc/scholarship" class="active" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=ssc/scholarship" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=ssc/scholarship" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=ssc/scholarship" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=ssc/scholarship" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc_head_home.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=<?= htmlspecialchars($scholarshipServiceParam) ?>" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=ssc/scholarship" data-tooltip="Alumni">
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
                            <a href="ssc_manage_events.php" data-tooltip="SSC Events">
                                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                                <span class="nav-text">SSC Events</span>
                            </a>
                            <a href="ssc_manage_candidates.php" data-tooltip="Candidates">
                                <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                                <span class="nav-text">Candidates</span>
                            </a>
                            <a href="ssc_reports.php" data-tooltip="Reports & Analytics">
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
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar\"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" class="active" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'scholarship'): ?>
                    <a href="ssc_head_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=scholarship" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=library" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc_head_home.php?service=ssc" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=scholarship" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=ssc/scholarship" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
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
                            <a href="scholarship/scholarship_create_announcement.php" data-tooltip="Scholarship Announcements">
                                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                <span class="nav-text">Scholarship Announcements</span>
                            </a>
                            <a href="scholarship/scholarship_reports.php" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar\"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=scholarship" class="active" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=scholarship" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'alumni'): ?>
                    <a href="ssaa_student_home.php?service=alumni" class="active" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=alumni" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=library" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc_head_home.php?service=ssc" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=scholarship" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=alumni" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Alumni</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="alumni_tracer_form.php" data-tooltip="Alumni Tracer">
                                <span class="nav-icon"><i class="fa-solid fa-file-alt"></i></span>
                                <span class="nav-text">Alumni Tracer</span>
                            </a>
                            <a href="alumni_management.php" data-tooltip="Alumni Management">
                                <span class="nav-icon"><i class="fa-solid fa-address-book"></i></span>
                                <span class="nav-text">Alumni Management</span>
                            </a>
                            <a href="alumni.php" data-tooltip="Alumni Records">
                                <span class="nav-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                                <span class="nav-text">Alumni Records</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=alumni" class="active" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=alumni" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php else: ?>
                    <a href="<?= $dashboardHome ?>" data-tooltip="Dashboard">
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
                                <span class="nav-text">SSC</span>
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
                    <a href="profile.php" class="active" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php endif; ?>
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
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle mobile menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p><?= htmlspecialchars($portalLabel) ?></p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?php if (!empty($displayYear) && $displayYear !== 'Not assigned'): ?><?= htmlspecialchars($displayCourse) ?> · <?= htmlspecialchars($displayYear) ?><?php else: ?><?= htmlspecialchars($displayCourse) ?><?php endif; ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <?php if (!empty($studentNotifications)): ?>
                            <div class="menu-items">
                                <?php foreach ($studentNotifications as $notification): ?>
                                    <div class="menu-item notification-item" role="menuitem">
                                        <p class="notification-title"><?= htmlspecialchars($notification['title']) ?></p>
                                        <p class="notification-meta"><?= htmlspecialchars(date('M j, Y', strtotime($notification['created_at']))) ?> · <?= htmlspecialchars(ucfirst($notification['category'] ?? 'Update')) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="menu-empty">No new announcements.</div>
                        <?php endif; ?>
                        <a class="menu-link menu-footer-link" href="clinic_dashboard.php#announcements">View all announcements</a>
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
            <div class="main-scroll">
                <div class="content-panel">
                    <section class="profile-intro">
                        <div class="profile-intro-left">
                            <p class="eyebrow">Account</p>
                            <h1>My Profile</h1>
                            <p class="dashboard-subtitle">Manage your personal details and update your password to keep your account secure.</p>
                        </div>
                        <aside class="profile-badges">
                            <span class="status-pill"><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                            <span class="status-pill"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($studentStatus) ?></span>
                            <span class="status-pill"><i class="fa-solid fa-calendar-days"></i> <?= htmlspecialchars((new DateTime($user['created_at'] ?? 'now'))->format('M d, Y')) ?></span>
                        </aside>
                    </section>
                    <?php if ($message): ?>
                        <div class="alert <?= $messageType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <section class="profile-grid">
                        <article class="profile-card profile-details-card">
                            <div class="card-topbar">
                                <div class="card-heading">
                                    <span class="icon-circle"><i class="fa-solid fa-id-badge"></i></span>
                                    <div>
                                        <p class="card-eyebrow">Personal</p>
                                        <h2>Profile Details</h2>
                                        <p class="card-subtitle">Personal profile details and security overview</p>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="profile-detail-row">
                                    <div class="field-label"><span class="field-icon"><i class="fa-solid fa-user"></i></span> Full name</div>
                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                </div>
                                <div class="profile-detail-row">
                                    <div class="field-label"><span class="field-icon"><i class="fa-solid fa-envelope"></i></span> Email address</div>
                                    <div class="email-detail-value">
                                        <strong id="profileEmailValue"><?= htmlspecialchars($user['email']) ?></strong>
                                        <button type="button" class="profile-inline-button" id="editEmailButton"><i class="fa-solid fa-pen"></i> Edit</button>
                                    </div>
                                </div>
                                <div class="email-edit-panel" id="emailEditPanel" hidden>
                                    <label for="newEmailInput">New email address</label>
                                    <div class="input-group">
                                        <span class="input-icon"><i class="fa-solid fa-envelope"></i></span>
                                        <input id="newEmailInput" type="email" placeholder="Enter your new email address" autocomplete="email">
                                    </div>
                                    <p class="email-change-feedback" id="emailChangeFeedback" role="status"></p>
                                    <div class="email-edit-actions">
                                        <button type="button" class="gradient-button" id="saveEmailButton">Save</button>
                                        <button type="button" class="profile-cancel-button" id="cancelEmailButton">Cancel</button>
                                    </div>
                                </div>
                                <div class="profile-detail-row">
                                    <div class="field-label"><span class="field-icon"><i class="fa-solid fa-id-card"></i></span> Student ID</div>
                                    <strong><?= htmlspecialchars($user['student_id'] ?? 'N/A') ?></strong>
                                </div>
                                <div class="profile-detail-row">
                                    <div class="field-label"><span class="field-icon"><i class="fa-solid fa-graduation-cap"></i></span> Course</div>
                                    <strong><?= htmlspecialchars($displayCourse) ?></strong>
                                </div>
                                <div class="profile-detail-row">
                                    <div class="field-label"><span class="field-icon"><i class="fa-solid fa-school"></i></span> Year level</div>
                                    <strong><?= htmlspecialchars($displayYear) ?></strong>
                                </div>
                                <div class="profile-detail-row">
                                    <div class="field-label"><span class="field-icon"><i class="fa-solid fa-user-tag"></i></span> Account type</div>
                                    <strong><?= htmlspecialchars(ucfirst($user['role'])) ?></strong>
                                </div>
                            </div>
                        </article>
                        <article class="profile-card password-card">
                            <div class="card-topbar">
                                <div class="card-heading">
                                    <span class="icon-circle"><i class="fa-solid fa-lock"></i></span>
                                    <div>
                                        <p class="card-eyebrow">Security</p>
                                        <h2>Change Password</h2>
                                        <p class="card-subtitle">Update your credentials with strong password controls</p>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="profile-panel-note">Update your login password anytime to keep your account secure.</p>
                                <form method="post" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="change_password" value="1">
                                    <label for="current_password">Current password</label>
                                    <div class="input-group">
                                        <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
                                        <input id="current_password" name="current_password" type="password" required>
                                    </div>
                                    <label for="new_password">New password</label>
                                    <div class="input-group">
                                        <span class="input-icon"><i class="fa-solid fa-key"></i></span>
                                        <input id="new_password" name="new_password" type="password" required>
                                    </div>
                                    <label for="confirm_password">Confirm new password</label>
                                    <div class="input-group">
                                        <span class="input-icon"><i class="fa-solid fa-check-circle"></i></span>
                                        <input id="confirm_password" name="confirm_password" type="password" required>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" class="gradient-button">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </article>
                    </section>
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
    <div class="page-overlay" id="pageOverlay"></div>
    <div class="email-otp-modal" id="emailOtpModal" hidden>
        <div class="email-otp-dialog" role="dialog" aria-modal="true" aria-labelledby="emailOtpTitle">
            <button type="button" class="email-otp-close" id="closeEmailOtpModal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            <span class="icon-circle"><i class="fa-solid fa-shield-halved"></i></span>
            <h2 id="emailOtpTitle">Verify your new email</h2>
            <p>Enter the 6-digit code sent to <strong id="pendingEmailLabel"></strong>.</p>
            <div class="email-otp-digits" id="emailOtpDigits" aria-label="6-digit verification code">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="Digit <?= $i + 1 ?>">
                <?php endfor; ?>
            </div>
            <p class="email-change-feedback" id="emailOtpFeedback" role="status"></p>
            <button type="button" class="gradient-button" id="verifyEmailButton">Verify and save</button>
        </div>
    </div>
    <script src="../assets/js/app.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emailChangeConfig = {
                serviceId: 'service_843t745',
                templateId: 'template_jb5tjxv',
                publicKey: 'rLeRK76s3p-IQn-X1'
            };
            const editEmailButton = document.getElementById('editEmailButton');
            const emailEditPanel = document.getElementById('emailEditPanel');
            const newEmailInput = document.getElementById('newEmailInput');
            const saveEmailButton = document.getElementById('saveEmailButton');
            const cancelEmailButton = document.getElementById('cancelEmailButton');
            const emailChangeFeedback = document.getElementById('emailChangeFeedback');
            const emailOtpModal = document.getElementById('emailOtpModal');
            const closeEmailOtpModal = document.getElementById('closeEmailOtpModal');
            const verifyEmailButton = document.getElementById('verifyEmailButton');
            const emailOtpFeedback = document.getElementById('emailOtpFeedback');
            const pendingEmailLabel = document.getElementById('pendingEmailLabel');
            const emailOtpDigits = Array.from(document.querySelectorAll('#emailOtpDigits input'));

            function setEmailFeedback(element, text, isError = true) {
                element.textContent = text;
                element.className = 'email-change-feedback ' + (isError ? 'is-error' : 'is-success');
            }

            function clearEmailEdit() {
                newEmailInput.value = '';
                emailEditPanel.hidden = true;
                editEmailButton.hidden = false;
                setEmailFeedback(emailChangeFeedback, '');
            }

            function openEmailOtpModal(email) {
                pendingEmailLabel.textContent = email;
                emailOtpDigits.forEach(input => input.value = '');
                setEmailFeedback(emailOtpFeedback, '');
                emailOtpModal.hidden = false;
                emailOtpDigits[0].focus();
            }

            editEmailButton.addEventListener('click', function() {
                emailEditPanel.hidden = false;
                editEmailButton.hidden = true;
                newEmailInput.focus();
            });

            cancelEmailButton.addEventListener('click', clearEmailEdit);

            emailOtpDigits.forEach((input, index) => {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
                    if (this.value && index < emailOtpDigits.length - 1) emailOtpDigits[index + 1].focus();
                });
                input.addEventListener('keydown', function(event) {
                    if (event.key === 'Backspace' && !this.value && index > 0) emailOtpDigits[index - 1].focus();
                });
            });

            closeEmailOtpModal.addEventListener('click', function() { emailOtpModal.hidden = true; });

            saveEmailButton.addEventListener('click', async function() {
                const email = newEmailInput.value.trim();
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    setEmailFeedback(emailChangeFeedback, 'Enter a valid email address.');
                    return;
                }
                saveEmailButton.disabled = true;
                setEmailFeedback(emailChangeFeedback, 'Sending verification code...', false);
                try {
                    const response = await fetch('../auth/email_change_send_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email })
                    });
                    const result = await response.json();
                    if (!result.success) {
                        setEmailFeedback(emailChangeFeedback, result.message || 'Unable to send verification code.');
                        return;
                    }
                    if (typeof emailjs === 'undefined') throw new Error('Email service unavailable');
                    emailjs.init(emailChangeConfig.publicKey);
                    await emailjs.send(emailChangeConfig.serviceId, emailChangeConfig.templateId, {
                        user_email: email,
                        to_email: email,
                        email: email,
                        name: email,
                        otp_code: result.code
                    });
                    clearEmailEdit();
                    openEmailOtpModal(email);
                } catch (error) {
                    setEmailFeedback(emailChangeFeedback, 'Failed to send code. Please try again.');
                } finally {
                    saveEmailButton.disabled = false;
                }
            });

            verifyEmailButton.addEventListener('click', async function() {
                const code = emailOtpDigits.map(input => input.value).join('');
                if (code.length !== 6) {
                    setEmailFeedback(emailOtpFeedback, 'Enter all 6 digits.');
                    return;
                }
                verifyEmailButton.disabled = true;
                setEmailFeedback(emailOtpFeedback, 'Verifying code...', false);
                try {
                    const response = await fetch('../auth/email_change_verify_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code })
                    });
                    const result = await response.json();
                    if (!result.success) {
                        setEmailFeedback(emailOtpFeedback, result.message || 'Invalid verification code.');
                        return;
                    }
                    document.getElementById('profileEmailValue').textContent = result.email;
                    emailOtpModal.hidden = true;
                    window.location.reload();
                } catch (error) {
                    setEmailFeedback(emailOtpFeedback, 'Verification failed. Please try again.');
                } finally {
                    verifyEmailButton.disabled = false;
                }
            });

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
        });
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
