<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();

$service = strtolower(trim($_GET['service'] ?? ''));
// Treat teacher head for SSC/Scholarship as combined mode when no explicit service provided
$isHeadSscScholarship = $user['role'] === 'teacher' && (($user['head_service'] ?? '') === 'ssc_scholarship');
if ($service === '' && $isHeadSscScholarship) { $service = 'ssc/scholarship'; }
$isAlumniService = $service === 'alumni';
$isGuidanceService = $service === 'guidance';
$isLibraryService = $service === 'library';
$isClinicService = $service === 'clinic';
// Combined SSC/Scholarship support
$isSscScholarshipService = $service === 'ssc/scholarship' || ($service === 'ssc' && $isHeadSscScholarship);
$sscScholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'ssc';
$scholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'scholarship';

if (!$user) {
    header('Location: ../auth/student_login.php');
    exit;
}
if ($user['role'] === 'admin') {
    header('Location: admin_home.php');
    exit;
}

if (!in_array($user['role'], ['student', 'teacher'], true)) {
    header('Location: ../auth/student_login.php');
    exit;
}

$displayCourse = normalize_course_label($user['course'] ?? $user['course_year'] ?? 'Course / Department');
$displayYear = $user['year_level'] ?? null;
if (!$displayYear && !empty($user['course_year'])) {
    $parts = explode(' ', trim($user['course_year']));
    $lastPart = end($parts);
    if (in_array($lastPart, ['1', '2', '3', '4'], true)) {
        $displayYear = $lastPart;
        array_pop($parts);
        $displayCourse = normalize_course_label(implode(' ', $parts) ?: $displayCourse);
    }
}
if (!$displayYear) {
    $displayYear = 'N/A';
}
$topbarInfo = build_user_dashboard_header_info($user);
// Remove numeric year display from the course label on the SSAA student home page
$topbarInfo['displayMeta'] = $topbarInfo['displayCourse'];
$roleLabel = $user['role'] === 'teacher' ? 'Teacher' : 'Student';
$dashboardLink = $user['role'] === 'teacher' ? 'teacher_home.php' : 'student_home.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$servicesOpen = in_array($currentPage, ['guidance_home.php', 'nurse_home.php', 'ssc_head_home.php', 'ssaa_student_home.php'], true);

// Get user-specific SSAA data
$conn = get_db();
$userProgress = null;
$userAlumniData = null;
$isAlumni = false;

$showAlumniOnboard = false;

// Ensure onboarding columns exist in ssaa_alumni
function ensure_alumni_onboard_columns(PDO $pdo) {
    $cols = [
        'gender' => "VARCHAR(20) DEFAULT NULL",
        'civil_status' => "VARCHAR(20) DEFAULT NULL",
        'dob' => "DATE DEFAULT NULL",
        'permanent_address' => "TEXT DEFAULT NULL",
        'personal_email' => "VARCHAR(150) DEFAULT NULL",
        'nationality' => "VARCHAR(100) DEFAULT NULL",
        'is_archived' => "TINYINT(1) NOT NULL DEFAULT 0",
        'archived_at' => "DATETIME DEFAULT NULL",
        'archived_by' => "INT(11) DEFAULT NULL",
    ];
    foreach ($cols as $col => $def) {
        $check = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ssaa_alumni' AND COLUMN_NAME = '$col'")->fetchColumn();
        if (!$check) {
            $pdo->exec("ALTER TABLE ssaa_alumni ADD COLUMN `$col` $def");
        }
    }
}

if ($user['role'] === 'student') {
    // Check if student has graduated
    $stmt = $conn->prepare("SELECT * FROM ssaa_alumni WHERE user_id = ? AND (is_archived = 0 OR is_archived IS NULL)");
    $stmt->execute([$user['id']]);
    $userAlumniData = $stmt->fetch(PDO::FETCH_ASSOC);
    $isAlumni = $userAlumniData !== false;

    if (!$isAlumni) {
        // Get latest student progress
        $stmt = $conn->prepare("SELECT * FROM ssaa_student_progression WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $userProgress = $stmt->fetch(PDO::FETCH_ASSOC);
    }


}

// If requested, show alumni onboarding form
if (isset($_GET['alumni_onboard']) && $_GET['alumni_onboard'] == '1') {
    $showAlumniOnboard = true;
}

// Handle alumni profile save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_alumni_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $graduationYear = trim($_POST['graduation_year'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $civilStatus = trim($_POST['civil_status'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $address = trim($_POST['permanent_address'] ?? '');
    $personalEmail = trim($_POST['personal_email'] ?? '');
    $mobile = trim($_POST['mobile_number'] ?? '');
    $nationality = trim($_POST['nationality'] ?? '');

    try {
        ensure_alumni_onboard_columns($conn);
        // Find alumni record for this user
        $stmt = $conn->prepare('SELECT id FROM ssaa_alumni WHERE user_id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $al = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($al) {
            $stmt = $conn->prepare('UPDATE ssaa_alumni SET full_name = ?, course = ?, graduation_year = ?, contact_number = ?, location = ?, personal_email = ?, email = ?, gender = ?, civil_status = ?, dob = ?, permanent_address = ?, nationality = ?, last_updated = NOW() WHERE id = ?');
            $stmt->execute([$fullName, $course, $graduationYear, $mobile, $address, $personalEmail, $personalEmail, $gender, $civilStatus, $dob ?: null, $address, $nationality ?: null, $al['id']]);
        } else {
            $stmt = $conn->prepare('INSERT INTO ssaa_alumni (user_id, full_name, email, personal_email, student_id, course, graduation_year, contact_number, gender, civil_status, dob, permanent_address, nationality, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$user['id'], $fullName, $personalEmail, $personalEmail, $user['student_id'] ?? null, $course, $graduationYear, $mobile, $gender, $civilStatus, $dob ?: null, $address, $nationality ?: null]);
        }
        $showAlumniOnboard = false;
        $successMessage = 'Alumni profile saved successfully.';
    } catch (Exception $e) {
        $errorMessage = 'Error saving alumni profile: ' . $e->getMessage();
    }
}

// Get general SSAA statistics
$alumniCount = $conn->query("SELECT COUNT(*) FROM ssaa_alumni WHERE is_archived = 0 OR is_archived IS NULL")->fetchColumn();
$alumniYears = $conn->query("SELECT DISTINCT graduation_year FROM ssaa_alumni WHERE is_archived = 0 OR is_archived IS NULL ORDER BY graduation_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$rawAlumniCourses = $conn->query("SELECT DISTINCT course FROM ssaa_alumni WHERE (is_archived = 0 OR is_archived IS NULL) AND course IS NOT NULL AND course != '' ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);
$alumniCourses = normalize_course_list($rawAlumniCourses);
?>
<!DOCTYPE html>
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
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSAA Dashboard | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        .topbar-divider { width: 1px; height: 24px; background: #e9ecef; margin: 0 12px; }
        .mic-button { background: none; border: none; cursor: pointer; color: #666; padding: 0 8px; }
        .status-passed { color: #27ae60; font-weight: 600; }
        .status-failed { color: #e74c3c; font-weight: 600; }
        .status-irregular { color: #f39c12; font-weight: 600; }
        .status-pending { color: #95a5a6; font-weight: 600; }

        /* Tab Styles */
        .tab-navigation {
            display: flex;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }

        .tab-button {
            background: none;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-button:hover {
            color: #007bff;
            background-color: #f8f9fa;
        }

        .tab-button.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background-color: #f8f9fa;
        }

        .tab-content {
            display: none;
            padding: 20px 0 0;
        }

        .tab-content.active {
            display: block;
        }

        .tab-panel-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            align-items: start;
        }

        .tab-panel-main {
            width: 100%;
        }

        .tab-panel-main,
        .tab-panel-side {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid rgba(230, 236, 245, 0.9);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
            padding: 24px;
        }

        .tab-panel-main {
            display: grid;
            gap: 20px;
        }

        .tab-panel-side {
            display: grid;
            gap: 18px;
        }

        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .panel-heading h4 {
            margin: 0;
            font-size: 1.1rem;
            color: #1f2937;
        }

        .panel-heading p {
            margin: 0;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .tab-panel-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        /* Clinic-style tab styles (copied from clinic_dashboard for consistency) */
        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-rose: #FFB6C1;
        }

        .clinic-tabs {
            display: flex;
            gap: 10px;
            background: white;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .clinic-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #111827;
            border-radius: 8px;
        }

        .clinic-tab i { color: var(--clinic-maroon); margin-right: 0.5rem; }

        .clinic-tab.active { background: var(--clinic-maroon); color: white; }

        .clinic-tab.active i { color: var(--clinic-gold); }

        .clinic-tab:hover:not(.active) { background: var(--clinic-maroon); color: white; }
        .clinic-tab:hover:not(.active) i { color: var(--clinic-gold); }

        .clinic-content { background: white; border-radius: 15px; padding: 1rem; }

        .summary-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 18px;
            border: 1px solid rgba(226, 232, 240, 1);
            display: grid;
            gap: 8px;
        }

        .summary-card strong {
            font-size: 1.2rem;
            color: #111827;
            display: block;
        }

        .summary-card span {
            color: #6b7280;
            font-size: 0.88rem;
        }

        .filter-panel {
            display: grid;
            gap: 14px;
            margin-bottom: 20px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
        }

        .filter-row input,
        .filter-row select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            background: #ffffff;
            color: #111827;
        }

        .filter-row button {
            border: none;
            background: #1d4ed8;
            color: white;
            padding: 12px 18px;
            border-radius: 14px;
            cursor: pointer;
        }

        .filter-row button:hover {
            background: #1e40af;
        }

        #alumni-tab .filter-panel,
        #career-tab .filter-panel {
            gap: 16px;
            margin-top: 8px;
            padding: 22px;
            border: 1px solid #eadfd8;
            border-radius: 18px;
            background: linear-gradient(135deg, #fffdfb 0%, #fff8f3 100%);
            box-shadow: 0 10px 24px rgba(109, 38, 20, 0.08);
        }

        #alumni-tab .filter-panel > .panel-heading,
        #career-tab .filter-panel > .panel-heading {
            margin-bottom: 2px;
            padding-bottom: 14px;
            border-bottom: 1px solid #eee3dc;
        }

        #alumni-tab .filter-panel .panel-heading h4,
        #career-tab .filter-panel .panel-heading h4 {
            color: #68151c;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.25rem;
        }

        #alumni-tab .filter-panel .panel-heading p,
        #career-tab .filter-panel .panel-heading p {
            color: #8b7770;
        }

        #alumni-tab .filter-row,
        #career-tab .filter-row {
            gap: 12px;
        }

        #alumni-tab .filter-row input,
        #alumni-tab .filter-row select,
        #career-tab .filter-row input,
        #career-tab .filter-row select {
            min-height: 44px;
            padding: 11px 14px;
            border: 1px solid #e5d5cc;
            border-radius: 10px;
            outline: none;
            background: #fffaf4;
            color: #513b39;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        #alumni-tab .filter-row input::placeholder,
        #career-tab .filter-row input::placeholder {
            color: #a18d86;
        }

        #alumni-tab .filter-row input:focus,
        #alumni-tab .filter-row select:focus,
        #career-tab .filter-row input:focus,
        #career-tab .filter-row select:focus {
            border-color: #9b0b16;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(155, 11, 22, 0.1);
        }

        #alumni-tab .filter-row button,
        #career-tab .filter-row button {
            min-height: 44px;
            padding: 11px 22px;
            border: 1px solid #68151c;
            border-radius: 10px;
            background: linear-gradient(135deg, #68151c, #9e3030);
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 6px 14px rgba(109, 38, 20, 0.16);
        }

        #alumni-tab .filter-row button:hover,
        #career-tab .filter-row button:hover {
            background: linear-gradient(135deg, #531017, #8f0011);
        }

.alumni-list,
        .career-list {
            display: grid;
            gap: 14px;
        }

        #career-tab .career-list {
            display: block;
        }

        #career-tab .career-page {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
        }

        #career-tab .career-page:not(.active) {
            display: none;
        }

        #career-tab .career-card {
            min-height: 190px;
            background: #fffaf6;
            border-color: #eadfd8;
            box-shadow: 0 7px 18px rgba(92, 31, 35, 0.08);
        }

        #career-tab .career-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px rgba(92, 31, 35, 0.16);
        }

        #career-tab .career-meta {
            color: #68494a;
        }

        #career-tab .pill-year,
        #career-tab .pill-status {
            background: #f4e2d9;
            color: #8f0011;
            border: 1px solid #e3c7b8;
        }

        #career-tab .career-card > div:not(.career-meta) {
            color: #68494a;
            line-height: 1.5;
        }

        #career-tab .career-pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 18px;
        }

        #career-tab .career-page-button {
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border: 1px solid #d8b8a8;
            border-radius: 9px;
            background: #ffffff;
            color: #8f0011;
            cursor: pointer;
            font-weight: 700;
        }

        #career-tab .career-page-button.active {
            background: linear-gradient(135deg, #68151c, #9e3030);
            border-color: #68151c;
            color: #ffffff;
        }

        @media (max-width: 900px) {
            #career-tab .career-page {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            #career-tab .career-page {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 420px) {
            #career-tab .career-page {
                grid-template-columns: 1fr;
            }
        }

        .alumni-year-section {
            margin-top: 26px;
        }

        .alumni-year-heading {
            margin: 0 0 16px;
            font-size: 1.2rem;
            color: #1f2937;
            font-weight: 700;
        }

        .alumni-carousel-wrapper {
            position: relative;
            display: grid;
            gap: 12px;
            padding: 0 24px;
        }

        .alumni-carousel-track {
            display: grid;
            grid-template-rows: repeat(2, minmax(0, auto));
            grid-auto-flow: column;
            grid-auto-columns: 280px;
            gap: 14px;
            overflow-x: auto;
            padding: 4px 0 12px;
            scroll-behavior: smooth;
            scrollbar-width: thin;
        }

        .alumni-carousel-track::-webkit-scrollbar {
            height: 8px;
        }

        .alumni-carousel-track::-webkit-scrollbar-thumb {
            background: #d8b8a8;
            border-radius: 999px;
        }

        .alumni-carousel-track > .alumni-card {
            width: 280px;
            min-height: 220px;
        }

        .alumni-carousel-pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        .alumni-carousel-page-button {
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            font-weight: 600;
        }

        .alumni-carousel-page-button.active {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #ffffff;
        }

        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: #ffffff;
            color: #1f2937;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .carousel-nav:hover {
            background: #f8fafc;
            transform: translateY(-50%) scale(1.02);
        }

        .carousel-nav:disabled {
            opacity: 0.4;
            cursor: default;
        }

        .carousel-prev {
            left: -18px;
        }

        .carousel-next {
            right: -18px;
        }

        /* Mobile Responsive: Horizontal Scrollable Tabs */
        @media (max-width: 768px) {
            .tab-navigation {
                display: flex;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                gap: 8px;
                padding: 12px;
                white-space: nowrap;
                scroll-behavior: smooth;
                scrollbar-width: none;
                border-bottom: none;
                margin-bottom: 12px;
                background: #f8f9fa;
                border-radius: 8px;
            }

            .tab-navigation::-webkit-scrollbar {
                display: none;
            }

            .tab-button {
                flex-shrink: 0;
                white-space: nowrap;
                padding: 8px 14px;
                font-size: 12px;
                border-radius: 6px;
                background: #ffffff;
                border-bottom: none;
                border: 1px solid #e9ecef;
            }

            .tab-button:hover {
                border-color: #007bff;
                background-color: #ffffff;
            }

            .tab-button.active {
                background: #007bff;
                color: #ffffff;
                border-color: #007bff;
            }
        }

        /* Mobile Responsive: Alumni Carousel */
        @media (max-width: 768px) {
            .alumni-carousel-wrapper {
                gap: 0;
                padding: 0;
            }

            .alumni-carousel-track {
                grid-auto-columns: 260px;
                gap: 12px;
                padding: 8px 0 12px;
            }

            .carousel-nav {
                display: inline-flex !important;
            }

            .alumni-carousel-track > .alumni-card {
                width: 260px;
            }
        }

        @media (max-width: 480px) {
            .alumni-carousel-track {
                grid-auto-columns: 240px;
            }

            .alumni-carousel-track > .alumni-card {
                width: 240px;
            }
        }

        /* Mobile Responsive: Tab Content Spacing */
        @media (max-width: 768px) {
            .tab-content {
                padding: 12px 0 0 !important;
            }

            .tab-panel-grid {
                gap: 12px;
            }

            .tab-panel-main,
            .tab-panel-side {
                padding: 14px;
            }

            .tab-panel-main {
                gap: 14px;
            }

            .tab-panel-side {
                gap: 12px;
            }

            .panel-heading {
                gap: 8px;
                margin-bottom: 12px;
            }

            .panel-heading h4 {
                font-size: 0.95rem;
            }

            .panel-heading p {
                font-size: 0.85rem;
            }

            .tab-panel-summary {
                gap: 10px;
                margin-bottom: 14px;
            }
        }

        /* Extra small phones: Further optimize spacing */
        @media (max-width: 480px) {
            .tab-panel-main,
            .tab-panel-side {
                padding: 12px;
                border-radius: 12px;
            }

            .tab-panel-main {
                gap: 12px;
            }

            .tab-panel-side {
                gap: 10px;
            }

            .panel-heading {
                gap: 6px;
                margin-bottom: 10px;
            }

            .panel-heading h4 {
                font-size: 0.9rem;
            }

            .panel-heading p {
                font-size: 0.8rem;
            }

            .tab-panel-summary {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                margin-bottom: 12px;
            }
        }

        /* Mobile Responsive: Cards and Content Elements */
        @media (max-width: 768px) {
            .service-card {
                padding: 14px;
                gap: 8px;
                border-radius: 12px;
            }

            .service-card h4 {
                font-size: 0.95rem;
            }

            .service-card p {
                font-size: 0.9rem;
            }

            .service-card button {
                padding: 8px 12px;
                font-size: 0.85rem;
            }

            .tab-content .section-heading {
                margin: 0 0 12px !important;
                font-size: 0.95rem;
            }

            /* Reduce text spacing in tab content */
            .tab-content p {
                margin-bottom: 8px !important;
            }

            .tab-content h3 {
                margin-bottom: 8px !important;
            }

            .tab-content h4 {
                margin-bottom: 6px !important;
            }

            /* Grid layouts on mobile */
            .services-grid,
            .features-grid {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }
        }

        /* Extra small: Further reduce spacing */
        @media (max-width: 480px) {
            .service-card {
                padding: 12px;
                gap: 6px;
                border-radius: 10px;
            }

            .service-card h4 {
                font-size: 0.9rem;
            }

            .service-card p {
                font-size: 0.85rem;
            }

            .service-card button {
                padding: 6px 10px;
                font-size: 0.8rem;
            }

            .tab-content .section-heading {
                margin: 0 0 10px !important;
                font-size: 0.9rem;
            }

            .tab-content p {
                margin-bottom: 6px !important;
                font-size: 0.85rem;
            }

            .tab-content h3 {
                margin-bottom: 6px !important;
                font-size: 1rem;
            }

            .tab-content h4 {
                margin-bottom: 4px !important;
                font-size: 0.9rem;
            }
        }

        /* Extra small: Further reduce spacing */
        @media (max-width: 480px) {
            .service-card {
                padding: 12px;
                gap: 6px;
                border-radius: 10px;
            }

            .service-card h4 {
                font-size: 0.9rem;
            }

            .service-card p {
                font-size: 0.85rem;
            }

            .service-card button {
                padding: 6px 10px;
                font-size: 0.8rem;
            }

            .tab-content .section-heading {
                margin: 0 0 10px !important;
                font-size: 0.9rem;
            }

            .tab-content p {
                margin-bottom: 6px !important;
                font-size: 0.85rem;
            }

            .tab-content h3 {
                margin-bottom: 6px !important;
                font-size: 1rem;
            }

            .tab-content h4 {
                margin-bottom: 4px !important;
                font-size: 0.9rem;
            }
        }

        /* Mobile Responsive: SSAA Services Buttons */
        @media (max-width: 768px) {
            .clinic-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                padding: 8px;
                margin-bottom: 16px;
            }

            .clinic-tab {
                flex: 1 1 calc(50% - 4px);
                padding: 10px 8px;
                font-size: 12px;
                white-space: normal;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
                gap: 6px;
            }

            .clinic-tab i {
                margin-right: 0;
            }
        }

        @media (max-width: 480px) {
            .clinic-tab {
                flex: 1 1 100%;
                padding: 10px 8px;
            }
        }

        /* Mobile Responsive: Network Overview Cards */
        @media (max-width: 768px) {
            .tab-panel-summary {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-bottom: 16px;
            }

            .summary-card {
                padding: 14px;
                border-radius: 12px;
                text-align: center;
            }

            .summary-card strong {
                font-size: 1.1rem;
            }

            .summary-card span {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .tab-panel-summary {
                gap: 10px;
            }

            .summary-card {
                padding: 12px;
                border-radius: 10px;
            }

            .summary-card strong {
                font-size: 1rem;
            }

            .summary-card span {
                font-size: 0.8rem;
            }
        }

        /* Mobile Responsive: Search & Filter Controls */
        @media (max-width: 768px) {
            .filter-panel {
                gap: 12px;
                margin-bottom: 16px;
            }

            .filter-row {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
                width: 100%;
            }

            .filter-row input,
            .filter-row select {
                width: 100%;
                padding: 11px 12px;
                font-size: 14px;
                border-radius: 10px;
            }

            .filter-row button {
                width: 100%;
                padding: 11px 12px;
                font-size: 14px;
                border-radius: 10px;
            }

            /* Make sure empty div in filter-row doesn't take space */
            .filter-row div:empty {
                display: none;
            }

            /* Fix feedback section for mobile */
            #feedback-tab > div {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 16px !important;
                padding: 24px 16px !important;
                overflow: hidden !important;
            }

            #feedback-tab > div > div {
                flex: 1 !important;
                min-width: 0 !important;
            }

            #rate-ssaa-service-btn {
                padding: 12px 14px !important;
                font-size: 12px !important;
                white-space: normal !important;
                flex-shrink: 0 !important;
            }
        }

        @media (max-width: 480px) {
            .filter-row input,
            .filter-row select {
                padding: 10px 10px;
                font-size: 13px;
            }

            .filter-row button {
                padding: 10px 10px;
                font-size: 13px;
            }
        }

        /* Mobile Responsive: Alumni Profile Cards */
        @media (max-width: 768px) {
            .alumni-card {
                gap: 12px;
                min-height: auto;
            }

            .alumni-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .alumni-card-image {
                width: 100%;
                height: 200px;
                border-radius: 14px;
            }

            .alumni-card-title {
                font-size: 1.1rem;
            }

            .alumni-card-body {
                width: 100%;
            }

            .alumni-card-academic {
                padding: 12px 14px;
            }

            .alumni-card-academic div {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .alumni-card {
                gap: 10px;
            }

            .alumni-card-header {
                gap: 10px;
            }

            .alumni-card-image {
                height: 160px;
                border-radius: 12px;
            }

            .alumni-card-title {
                font-size: 1rem;
            }

            .alumni-card-academic {
                padding: 10px 12px;
                gap: 6px;
            }

            .alumni-card-academic div {
                font-size: 0.85rem;
            }

            .alumni-card-academic .label {
                margin-right: 4px;
            }
        }

        /* Mobile Responsive: Modal Popup */
        @media (max-width: 768px) {
            .modal {
                padding: 12px;
            }

            .modal-content {
                border-radius: 16px;
            }

            .modal-header {
                padding: 16px;
            }

            .modal-header h3 {
                font-size: 1rem;
            }

            .modal-body {
                padding: 16px;
                gap: 12px;
            }

            .modal-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .modal-row .modal-column {
                gap: 8px;
            }

            .modal-footer {
                padding: 12px 16px 16px;
                gap: 8px;
                flex-wrap: wrap;
            }

            .modal-meta {
                gap: 6px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .modal {
                padding: 8px;
            }

            .modal-content {
                border-radius: 14px;
            }

            .modal-header {
                padding: 12px;
            }

            .modal-header h3 {
                font-size: 0.95rem;
                word-wrap: break-word;
            }

            .modal-close {
                font-size: 1.2rem;
            }

            .modal-body {
                padding: 12px;
                gap: 10px;
            }

            .modal-meta {
                gap: 4px;
                font-size: 0.85rem;
            }

            .modal-meta strong {
                font-size: 0.9rem;
            }
        }

        /* Mobile Responsive: General Content Spacing */
        @media (max-width: 768px) {
            .main-scroll {
                padding: 12px !important;
            }

            .dashboard-card {
                padding: 12px !important;
                border-radius: 14px;
                margin: 0 !important;
            }

            .card-content {
                padding: 10px !important;
            }

            .card-header {
                padding: 10px 0 8px 0 !important;
            }

            .card-header h3 {
                font-size: 1.1rem !important;
            }
        }

        @media (max-width: 480px) {
            .main-scroll {
                padding: 8px !important;
            }

            .dashboard-card {
                padding: 10px !important;
                border-radius: 12px;
            }

            .card-content {
                padding: 8px !important;
            }

            .card-header {
                padding: 8px 0 6px 0 !important;
            }

            .card-header h3 {
                font-size: 1rem !important;
            }

            .panel-heading {
                gap: 4px;
                margin-bottom: 8px;
            }

            .panel-heading h4 {
                font-size: 0.95rem;
            }

            .panel-heading p {
                font-size: 0.8rem;
            }
        }

        /* Ultra-small phones (320px-374px): Fix left-side cutoff in tabs */
        @media (max-width: 374px) {
            .tab-panel-main {
                padding: 0 12px !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                width: 100% !important;
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                border-radius: 0 !important;
                gap: 12px !important;
                overflow: hidden !important;
            }

            .tab-panel-grid {
                padding: 0 12px !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                width: 100% !important;
                overflow-x: hidden !important;
                overflow: hidden !important;
            }

            .clinic-content {
                padding: 0 12px !important;
                margin: 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
                overflow: hidden !important;
            }

            .panel-heading {
                padding: 0 !important;
                margin: 0 0 12px 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .panel-heading > div {
                width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            .filter-panel {
                padding: 0 !important;
                margin: 0 0 12px 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            .filter-row {
                width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            .filter-row input,
            .filter-row select,
            .filter-row button {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            #student-alumni-groups {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            .alumni-year-section,
            .career-section {
                padding: 0 !important;
                margin: 0 0 12px 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            .summary-card,
            .alumni-card,
            .career-card {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            /* Fix course dropdown overflow on 320px-374px */
            #student-alumni-course {
                width: 100% !important;
                max-width: 100% !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                word-break: break-word !important;
                padding: 8px 6px !important;
                font-size: 11px !important;
            }

            /* Fix "Professional & Mentorship" text wrap */
            .summary-card strong {
                word-wrap: break-word !important;
                word-break: break-word !important;
                white-space: normal !important;
                overflow-wrap: break-word !important;
                line-height: 1.2 !important;
            }
            /* Reduce font-size specifically for Connection Type card on ultra-small screens */
            .tab-panel-summary .summary-card:nth-child(3) strong {
                font-size: 12px !important;
                line-height: 1.1 !important;
                display: block !important;
            }


            .tab-panel-summary {
                width: 100% !important;
                box-sizing: border-box !important;
            }

            /* Prevent text overflow on ultra-small screens */
            .panel-heading h4,
            .panel-heading p,
            .summary-card span,
            .summary-card strong,
            .alumni-card h5,
            .alumni-card p,
            .career-card h5,
            .career-card p {
                word-wrap: break-word !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
                max-width: 100% !important;
            }

            /* Force flex items to not exceed width */
            .tab-panel-main > *,
            .clinic-content > *,
            .filter-panel > * {
                max-width: 100% !important;
                box-sizing: border-box !important;
                min-width: 0 !important;
            }
        }

        /* Extra small phones: Fix overflow issues (320px-425px) */
        @media (max-width: 425px) {
            body {
                overflow-x: hidden;
            }

            /* Fix feedback section gradient container overflow */
            #feedback-tab > div {
                display: flex !important;
                flex-direction: column !important;
                justify-content: flex-start !important;
                gap: 12px !important;
                padding: 16px 12px !important;
                border-radius: 12px !important;
            }

            #feedback-tab > div > div {
                flex: 1 !important;
                width: 100% !important;
            }

            #rate-ssaa-service-btn {
                width: 100% !important;
                padding: 12px 16px !important;
                font-size: 13px !important;
                justify-content: center !important;
            }

            #rate-ssaa-service-btn i {
                margin-right: 4px !important;
            }

            /* Fix alumni networks dropdowns - extra small */
            .filter-row {
                grid-template-columns: 1fr !important;
                gap: 8px !important;
            }

            .filter-row input,
            .filter-row select {
                width: 100% !important;
                max-width: 100% !important;
                padding: 10px 8px !important;
                font-size: 12px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            .filter-row button {
                width: 100% !important;
                padding: 10px 8px !important;
                font-size: 12px !important;
            }

            /* Prevent content overflow on small screens */
            .tab-panel-grid,
            .tab-panel-main,
            .clinic-content {
                overflow-x: hidden !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding-left: 12px !important;
                padding-right: 12px !important;
            }

            .dashboard-card-full-width .card-content {
                overflow-x: hidden !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .alumni-list,
            .career-list {
                overflow-x: hidden !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            /* Remove padding from SSAA Services card wrapper to prevent right shift */
            .dashboard-card-full-width {
                padding: 12px 0 !important;
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
            }
        }

        .alumni-card,
        .career-card {
            padding: 22px;
            border-radius: 22px;
            background: #fffaf6;
            border: 1px solid #eadbd2;
            box-shadow: 0 7px 18px rgba(92, 31, 35, 0.08);
            display: grid;
            gap: 12px;
        }

        .alumni-card strong,
        .career-card strong {
            font-size: 1rem;
            color: #5c1f23;
        }

        .alumni-meta,
        .career-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: #475569;
            font-size: 0.92rem;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .pill-employment { background: rgba(34, 197, 94, 0.12); color: #166534; }
        .pill-year { background: rgba(37, 99, 235, 0.12); color: #1d4ed8; }
        .pill-status { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }

        .alumni-card {
            display: grid;
            gap: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            min-height: 220px;
        }

        .alumni-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px rgba(92, 31, 35, 0.16);
        }

        .alumni-card-header {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .alumni-card-image {
            width: 104px;
            height: 104px;
            border-radius: 18px;
            overflow: hidden;
            background: #f7e9e1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .alumni-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .alumni-card-placeholder {
            font-size: 2.4rem;
            color: #9b0b16;
        }

        .alumni-card-body {
            display: grid;
            gap: 14px;
        }

        .alumni-card-header {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .alumni-card-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #5c1f23;
            line-height: 1.1;
        }

        .alumni-card-academic {
            display: grid;
            gap: 8px;
            background: #fffdfb;
            border: 1px solid #eadbd2;
            border-radius: 16px;
            padding: 14px 16px;
        }

        .alumni-card-academic div {
            color: #68494a;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .alumni-card-academic .label {
            font-weight: 600;
            color: #9b0b16;
            margin-right: 6px;
        }

        .alumni-card-details {
            display: grid;
            gap: 6px;
            color: #68494a;
            font-size: 0.92rem;
        }

        .alumni-card-achievements {
            font-size: 0.9rem;
            color: #9b0b16;
        }

        .alumni-card-quote {
            font-size: 0.95rem;
            font-style: italic;
            color: #68494a;
            line-height: 1.7;
            background: #f9ebe5;
            border-left: 4px solid #9b0b16;
            padding: 14px 16px;
            border-radius: 14px;
        }
        

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 9999;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            width: min(760px, 100%);
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }

        .modal-header {
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #0f172a;
        }

        .modal-close {
            border: none;
            background: transparent;
            font-size: 1.5rem;
            color: #475569;
            cursor: pointer;
        }

        .modal-body {
            padding: 24px;
            display: grid;
            gap: 16px;
        }

        .modal-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .modal-row .modal-column {
            display: grid;
            gap: 12px;
        }

        .modal-footer {
            padding: 16px 24px 24px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-top: 1px solid #e2e8f0;
        }

        .modal-meta {
            display: grid;
            gap: 8px;
            color: #475569;
            font-size: 0.95rem;
        }

        .modal-meta strong {
            color: #0f172a;
        }

        #alumniDetailsModal .modal-content {
            width: min(760px, 100%);
            border: 1px solid #eadfd8;
            border-radius: 18px;
            background: #fffdfb;
            box-shadow: 0 24px 60px rgba(109, 38, 20, 0.2);
        }

        #alumniDetailsModal .modal-header {
            position: relative;
            align-items: flex-end;
            min-height: 132px;
            padding: 26px 28px 22px 116px;
            border-bottom: 0;
            background: linear-gradient(135deg, #68151c, #9e3030);
            color: #ffffff;
        }

        #alumniDetailsModal .modal-header::before {
            content: '';
            position: absolute;
            left: 28px;
            bottom: -30px;
            width: 68px;
            height: 68px;
            border: 5px solid #ffffff;
            border-radius: 16px;
            background: #f8e9e1;
            box-shadow: 0 8px 18px rgba(109, 38, 20, 0.18);
        }

        #alumniDetailsModal .modal-header h3 {
            color: #ffffff;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.65rem;
        }

        #alumniDetailsModal .modal-header p {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        #alumniDetailsModal .modal-close {
            color: #ffffff;
            z-index: 1;
        }

        #alumniDetailsModal .modal-body {
            padding: 52px 28px 26px;
            gap: 16px;
        }

        #alumniDetailsModal .modal-row:first-child {
            grid-template-columns: 150px minmax(0, 1fr);
        }

        #alumniDetailsModal .modal-row:first-child .modal-column:first-child {
            display: grid;
            align-content: start;
        }

        #alumniDetailsModal #modal-profile-image {
            width: 132px;
            height: 132px;
            border: 5px solid #ffffff;
            border-radius: 16px;
            background: #f8e9e1;
            box-shadow: 0 8px 18px rgba(109, 38, 20, 0.14);
        }

        #alumniDetailsModal #modal-profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #alumniDetailsModal #modal-profile-image .alumni-card-placeholder {
            color: #8f0011;
            font-size: 2.5rem;
        }

        #alumniDetailsModal .modal-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        #alumniDetailsModal .modal-meta > div {
            min-width: 0;
            padding: 11px 12px;
            border: 1px solid #eee3dc;
            border-radius: 10px;
            background: #fffaf4;
            color: #756d69;
            font-size: 0.88rem;
            line-height: 1.45;
        }

        #alumniDetailsModal .modal-meta strong,
        #alumniDetailsModal .modal-body .modal-row:nth-child(2) strong,
        #alumniDetailsModal .modal-body > div:last-child > div strong {
            color: #8f0011;
        }

        #alumniDetailsModal .modal-row:nth-child(2) {
            gap: 12px;
        }

        #alumniDetailsModal .modal-row:nth-child(2) .modal-column,
        #alumniDetailsModal .modal-body > div:last-child {
            padding: 15px;
            border: 1px solid #eee3dc;
            border-radius: 12px;
            background: #ffffff;
        }

        #alumniDetailsModal .modal-footer {
            padding: 14px 28px 22px;
            border-top: 1px solid #eee3dc;
        }

        #alumniDetailsModal .modal-footer .btn-secondary {
            padding: 9px 20px;
            border: 1px solid #d8b8a8;
            border-radius: 9px;
            background: #ffffff;
            color: #8f0011;
            font-weight: 700;
            cursor: pointer;
        }

        @media (max-width: 600px) {
            #alumniDetailsModal .modal-header {
                min-height: 118px;
                padding: 22px 20px 18px 100px;
            }
            #alumniDetailsModal .modal-header::before {
                left: 20px;
                width: 60px;
                height: 60px;
            }
            #alumniDetailsModal .modal-header h3 {
                font-size: 1.3rem;
            }
            #alumniDetailsModal .modal-body {
                padding: 45px 16px 20px;
            }
            #alumniDetailsModal .modal-meta {
                grid-template-columns: 1fr;
            }
            #alumniDetailsModal .modal-row:first-child {
                grid-template-columns: 1fr;
            }
            #alumniDetailsModal .modal-row:first-child .modal-column:first-child {
                justify-items: center;
            }
            #alumniDetailsModal .modal-row:nth-child(2) {
                grid-template-columns: 1fr;
            }
            #alumniDetailsModal .modal-footer {
                padding-left: 16px;
                padding-right: 16px;
            }
        }

        .service-card {
            border-radius: 18px;
            border: 1px solid rgba(226, 232, 240, 1);
            padding: 20px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
            display: grid;
            gap: 10px;
        }

        .service-card h4 {
            margin: 0;
            font-size: 1rem;
            color: #111827;
        }

        .service-card p {
            margin: 0;
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .service-card button {
            border: none;
            background: #1d4ed8;
            color: white;
            padding: 10px 14px;
            border-radius: 12px;
            cursor: pointer;
            width: fit-content;
        }

        .service-card button:hover {
            background: #1e40af;
        }

        .tab-content .section-heading {
            margin: 0 0 16px;
            font-size: 1.05rem;
            color: #334155;
        }

        @media (max-width: 960px) {
            .tab-panel-grid {
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
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar" <?= $isAlumniService ? 'onclick="toggleGuidanceSidebar()"' : '' ?>>
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="nav-section">
                <?php if ($isGuidanceService): ?>
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
                    <a href="profile.php?service=guidance" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=guidance" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isAlumniService): ?>
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
                    <a href="profile.php?service=alumni" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=alumni" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isLibraryService): ?>
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
                            <a href="library_checkin.php" data-tooltip="QR Check-In">
                                <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                                <span class="nav-text">QR Check-In</span>
                            </a>
                            <a href="library_catalog.php" data-tooltip="Digital Catalog">
                                <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                                <span class="nav-text">Digital Catalog</span>
                            </a>
                            <a href="library_inventory.php" data-tooltip="Inventory">
                                <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                                <span class="nav-text">Inventory</span>
                            </a>
                            <a href="library_reports.php" data-tooltip="Reports">
                                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                                <span class="nav-text">Reports</span>
                            </a>
                            <a href="library_settings.php" data-tooltip="Settings">
                                <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                                <span class="nav-text">Settings</span>
                            </a>
                            <a href="library_qr.php" data-tooltip="Library QR">
                                <span class="nav-icon"><i class="fa-solid fa-code"></i></span>
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
                <?php elseif ($isClinicService): ?>
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
                            <a href="clinic_analytics.php" data-tooltip="Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=clinic" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=clinic" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isSscScholarshipService): ?>
                    <a href="ssc_head_home.php" data-tooltip="Dashboard">
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
                            <a href="guidance_dashboard.php?service=ssc/scholarship" data-tooltip="Guidance"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span><span class="nav-text">Guidance</span></a>
                            <a href="library_dashboard.php?service=ssc/scholarship" data-tooltip="Library"><span class="nav-icon"><i class="fa-solid fa-book"></i></span><span class="nav-text">Library</span></a>
                            <a href="clinic_dashboard.php?service=ssc/scholarship" data-tooltip="Clinic"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span><span class="nav-text">Clinic</span></a>
                            <a href="ssc/ssc_dashboard.php?service=ssc/scholarship" data-tooltip="SSC"><span class="nav-icon"><i class="fa-solid fa-award"></i></span><span class="nav-text">SSC</span></a>
                            <a href="scholarship/scholarship_dashboard.php?service=ssc/scholarship" data-tooltip="Scholarship"><span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span><span class="nav-text">Scholarship</span></a>
                            <a href="ssaa_student_home.php?service=ssc/scholarship" data-tooltip="Alumni"><span class="nav-icon"><i class="fa-solid fa-users"></i></span><span class="nav-text">Alumni</span></a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage SSC"><span class="nav-icon"><i class="fa-solid fa-sliders"></i></span><span class="nav-text">Manage SSC</span><span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span></button>
                        <div class="submenu" aria-hidden="true">
                            <a href="ssc_manage_events.php" data-tooltip="SSC Events"><span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span><span class="nav-text">SSC Events</span></a>
                            <a href="ssc_manage_candidates.php" data-tooltip="Candidates"><span class="nav-icon"><i class="fa-solid fa-user-group"></i></span><span class="nav-text">Candidates</span></a>
                            <a href="ssc_reports.php" data-tooltip="Reports & Analytics"><span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span><span class="nav-text">Reports & Analytics</span></a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Scholarship"><span class="nav-icon"><i class="fa-solid fa-sliders"></i></span><span class="nav-text">Manage Scholarship</span><span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span></button>
                        <div class="submenu" aria-hidden="true">
                            <a href="scholarship/scholarship_create_announcement.php" data-tooltip="Scholarship Announcements"><span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span><span class="nav-text">Scholarship Announcements</span></a>
                            <a href="scholarship/scholarship_reports.php" data-tooltip="Reports & Analytics"><span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span><span class="nav-text">Reports & Analytics</span></a>
                        </div>
                    </div>
                    <a href="profile.php?service=ssc/scholarship" data-tooltip="Profile"><span class="nav-icon"><i class="fa-solid fa-user"></i></span><span class="nav-text">Profile</span></a>
                    <a href="about.php?service=ssc/scholarship" data-tooltip="About"><span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span><span class="nav-text">About</span></a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($dashboardLink) ?>" data-tooltip="Dashboard">
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
                            <a href="guidance_dashboard.php" data-tooltip="Guidance">
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
                            <a href="ssaa_student_home.php" class="active" data-tooltip="Alumni">
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
                            <p>Alumni Services</p>
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
            <div class="main-scroll">
                    <div class="service-page-header">
                        <div class="service-page-header-main">
                            <div class="service-page-header-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
                            <div>
                                <p class="service-page-header-eyebrow">Campus Portal</p>
                                <h1>SSAA Services</h1>
                                <p class="service-page-header-welcome">Welcome back, <strong><?= htmlspecialchars($user['full_name']) ?></strong></p>
                            </div>
                        </div>
                        <div class="service-page-header-center">
                            <i class="fa-solid fa-handshake" aria-hidden="true"></i>
                            <div><strong>Stay connected</strong><span>Keep your alumni profile updated and join the PASS community.</span></div>
                        </div>
                        <div class="service-page-header-profile">
                            <strong><?= htmlspecialchars($roleLabel) ?></strong>
                            <div><?= htmlspecialchars($topbarInfo['displayMeta']) ?></div>
                        </div>
                    </div>

                    <?php 
                    // Show congratulations modal if user is alumni and hasn't dismissed it
                    if ($isAlumni && !isset($_POST['dismiss_congratulations'])): 
                        $congratsCourse = htmlspecialchars($userAlumniData['course'] ?? $user['course_year'] ?? 'Your Program');
                        $congradsYear = htmlspecialchars($userAlumniData['graduation_year'] ?? date('Y'));
                        $congradsName = htmlspecialchars($user['full_name']);
                    ?>
                    <div id="graduationCongratulationsModal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:9998;">
                        <div style="background:linear-gradient(135deg,oklch(0.32 0.13 22) 0%,oklch(0.45 0.16 25) 60%,oklch(0.55 0.15 35) 100%);color:#faf7f0;padding:60px 40px;border-radius:16px;max-width:600px;width:90%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,0.3);animation:slideIn 0.5s ease-out;">
                            <div style="font-size:48px;margin-bottom:20px;">🎓</div>
                            <h2 style="margin:0 0 16px 0;font-family:'Playfair Display',serif;font-size:36px;font-weight:bold;">Congratulations on Your Graduation!</h2>
                            <p style="margin:0 0 24px 0;font-size:18px;opacity:0.95;">Class of <strong><?= $congradsYear ?></strong></p>
                            <div style="background:rgba(255,255,255,0.1);padding:20px;border-radius:8px;margin:24px 0;backdrop-filter:blur(10px);">
                                <p style="margin:0 0 8px 0;opacity:0.9;font-size:14px;">Program</p>
                                <p style="margin:0;font-size:18px;font-weight:600;"><?= $congratsCourse ?></p>
                            </div>
                            <p style="margin:0 0 24px 0;font-size:15px;line-height:1.6;opacity:0.95;">
                                We're excited to welcome you to our alumni community! Complete your alumni profile to stay connected with PASS College and access alumni benefits.
                            </p>
                            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                                <form method="post" style="display:inline;">
                                    <button type="submit" name="dismiss_congratulations" value="1" onclick="document.getElementById('graduationCongratulationsModal').style.display='none';" style="background:rgba(255,255,255,0.2);color:#faf7f0;border:2px solid #faf7f0;padding:12px 24px;border-radius:6px;font-weight:600;cursor:pointer;font-size:15px;transition:all 0.3s ease;">Dismiss</button>
                                </form>
                                <a href="?alumni_onboard=1" style="background:linear-gradient(135deg,#d4af37,#f4d03f);color:#5c1f23;padding:12px 32px;border-radius:6px;font-weight:600;text-decoration:none;display:inline-block;font-size:15px;transition:all 0.3s ease;">Complete Profile</a>
                            </div>
                        </div>
                    </div>
                    <style>
                        @keyframes slideIn {
                            from {
                                opacity: 0;
                                transform: translateY(-30px);
                            }
                            to {
                                opacity: 1;
                                transform: translateY(0);
                            }
                        }
                    </style>
                    <?php endif; ?>

                    <?php if ($showAlumniOnboard):
                        $prefFullName = htmlspecialchars($user['full_name']);
                        $prefCourse = htmlspecialchars($userAlumniData['course'] ?? $user['course_year'] ?? '');
                        $prefGradYear = htmlspecialchars($userAlumniData['graduation_year'] ?? date('Y'));
                        $prefGender = htmlspecialchars($userAlumniData['gender'] ?? '');
                        $prefCivil = htmlspecialchars($userAlumniData['civil_status'] ?? '');
                        $prefDob = htmlspecialchars($userAlumniData['dob'] ?? '');
                        $prefAddr = htmlspecialchars($userAlumniData['permanent_address'] ?? '');
                        $prefEmail = htmlspecialchars($userAlumniData['personal_email'] ?? '');
                        $prefMobile = htmlspecialchars($userAlumniData['contact_number'] ?? '');
                        $actualStudentCourseYear = htmlspecialchars($user['course_year'] ?? '');
                    ?>
                    <div id="alumniOnboardModal" class="modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;">
                        <section class="alumni-onboard-panel" style="background:#fff;padding:24px;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;box-shadow:0 4px 20px rgba(0,0,0,0.2);">
                            <h3 style="margin-top:0;color:#800000;font-size:1.5rem;margin-bottom:8px;">Welcome, Alumni!</h3>
                            <p style="color:#666;font-size:0.95rem;margin-bottom:16px;">Help us keep your alumni record up to date. Fill in the information below.</p>
                            <?php if (!empty($errorMessage)): ?><div class="alert alert-error" style="background:#fee;color:#c00;padding:10px;border-radius:4px;margin-bottom:12px;"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>
                            <?php if (!empty($successMessage)): ?><div class="alert alert-success" style="background:#efe;color:#060;padding:10px;border-radius:4px;margin-bottom:12px;"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
                            <form method="post">
                                <input type="hidden" name="save_alumni_profile" value="1" />
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Full Name</label>
                                        <input type="text" name="full_name" value="<?= $prefFullName ?>" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;" />
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Course</label>
                                        <select name="course" id="alumniCourseSelect" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                                            <option value="">-- Select Course --</option>
                                            <?php
                                            $courses = [
                                                'Bachelor of Science in Accountancy',
                                                'Bachelor of Science in Business Administration',
                                                'Bachelor in Elementary Education',
                                                'Bachelor of Science in Computer Science',
                                                'Bachelor of Science in Criminology',
                                                'Bachelor of Science in Hospitality Management',
                                                'Bachelor of Science in Tourism Management',
                                                'Associate in Computer Technology',
                                                'Associate in Office Administration',
                                                'Associate in Hotel Management',
                                                'Associate in Tourism Management',
                                            ];
                                            foreach ($courses as $c) {
                                                echo "<option value=\"" . htmlspecialchars($c) . "\">" . htmlspecialchars($c) . "</option>";
                                            }
                                            ?>
                                        </select>
                                        <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                const courseSelect = document.getElementById('alumniCourseSelect');
                                                const studentCourse = '<?= $actualStudentCourseYear ?>';
                                                const savedCourse = '<?= $prefCourse ?>';
                                                
                                                // First try to match with saved course
                                                if (savedCourse) {
                                                    courseSelect.value = savedCourse;
                                                    if (courseSelect.value === savedCourse) return;
                                                }
                                                
                                                // Then try to match with student's actual course_year
                                                if (studentCourse) {
                                                    const courseYearClean = studentCourse.replace(/\b1st Year\b|\b2nd Year\b|\b3rd Year\b|\b4th Year\b/gi, '').trim();
                                                    for (let i = 0; i < courseSelect.options.length; i++) {
                                                        const option = courseSelect.options[i];
                                                        if (option.value.toLowerCase().includes(courseYearClean.toLowerCase())) {
                                                            courseSelect.value = option.value;
                                                            return;
                                                        }
                                                    }
                                                }
                                            });
                                        </script>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Year Graduated</label>
                                        <input type="text" name="graduation_year" value="<?= $prefGradYear ?>" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;" />
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Gender</label>
                                        <div style="display:flex;gap:12px;margin-top:6px;">
                                            <label style="display:flex;align-items:center;gap:4px;"><input type="radio" name="gender" value="Male" <?= $prefGender === 'Male' ? 'checked' : '' ?> /> Male</label>
                                            <label style="display:flex;align-items:center;gap:4px;"><input type="radio" name="gender" value="Female" <?= $prefGender === 'Female' ? 'checked' : '' ?> /> Female</label>
                                            <label style="display:flex;align-items:center;gap:4px;"><input type="radio" name="gender" value="Other" <?= $prefGender === 'Other' ? 'checked' : '' ?> /> Other</label>
                                        </div>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Civil Status</label>
                                        <select name="civil_status" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                                            <option value="Single" <?= $prefCivil === 'Single' ? 'selected' : '' ?>>Single</option>
                                            <option value="Married" <?= $prefCivil === 'Married' ? 'selected' : '' ?>>Married</option>
                                            <option value="Widowed" <?= $prefCivil === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                            <option value="Separated" <?= $prefCivil === 'Separated' ? 'selected' : '' ?>>Separated</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Date of Birth</label>
                                        <input type="date" name="dob" value="<?= $prefDob ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;" />
                                    </div>
                                    <div style="grid-column:1/ -1">
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Permanent Address</label>
                                        <textarea name="permanent_address" rows="2" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;font-family:inherit;"><?= $prefAddr ?></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Personal Email</label>
                                        <input type="email" name="personal_email" value="" placeholder="example@gmail.com" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;" />
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Mobile Number</label>
                                        <input type="text" name="mobile_number" value="<?= $prefMobile ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;" />
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Nationality</label>
                                        <input type="text" name="nationality" value="<?= htmlspecialchars($userAlumniData['nationality'] ?? '') ?>" placeholder="e.g., Filipino, American" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;" />
                                    </div>
                                </div>
                                <div style="margin-top:16px;display:flex;gap:12px;">
                                    <button class="btn btn-primary" type="submit" style="flex:1;padding:10px;border:none;border-radius:4px;background:#800000;color:#fff;cursor:pointer;font-weight:600;">Save Profile</button>
                                    <a class="btn btn-secondary" href="ssaa_student_home.php" style="flex:1;padding:10px;text-align:center;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#666;cursor:pointer;box-sizing:border-box;">Cancel</a>
                                </div>
                            </form>
                        </section>
                    </div>
                    <?php endif; ?>

                    <div class="dashboard-grid">
                        <?php if ($user['role'] === 'student' && $isAlumni): ?>
                        <?php elseif ($user['role'] === 'student' && !$isAlumni): ?>
                        <!-- Active student cards -->
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-chart-line"></i> Academic Progress</h3>
                            </div>
                            <div class="card-content">
                                <div id="progress-info">
                                    <?php if ($userProgress): ?>
                                    <p><strong>Academic Year:</strong> <?= htmlspecialchars($userProgress['academic_year']) ?></p>
                                    <p><strong>Status:</strong> <span class="status-<?= strtolower($userProgress['academic_status']) ?>"><?= htmlspecialchars($userProgress['academic_status']) ?></span></p>
                                    <?php if ($userProgress['gpa']): ?>
                                    <p><strong>GPA:</strong> <?= htmlspecialchars($userProgress['gpa']) ?></p>
                                    <?php endif; ?>
                                    <p><strong>Subjects:</strong> <?= htmlspecialchars($userProgress['total_subjects'] ?? 0) ?> total, <?= htmlspecialchars($userProgress['failed_subjects'] ?? 0) ?> failed</p>
                                    <?php if ($userProgress['remarks']): ?>
                                    <p><strong>Remarks:</strong> <?= htmlspecialchars($userProgress['remarks']) ?></p>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <p>No academic progress data available yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-calendar-graduation"></i> Graduation Timeline</h3>
                            </div>
                            <div class="card-content">
                                <div id="graduation-timeline">
                                    <p>Based on your current year level, graduation is approximately <?= 4 - (int)$displayYear + 1 ?> year(s) away.</p>
                                    <p><strong>Expected Graduation:</strong> Academic Year 202<?= (int)$displayYear + 1 ?>-202<?= (int)$displayYear + 2 ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Common cards for all users - now tabbed -->
                        <div class="dashboard-card dashboard-card-full-width">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-users"></i> SSAA Services</h3>
                            </div>
                            <div class="card-content">
                                <!-- Tab Navigation (clinic-style) -->
                                <div class="clinic-tabs">
                                    <button class="clinic-tab active" onclick="switchTab(event, 'alumni')">
                                        <i class="fa-solid fa-users"></i> Alumni Networks
                                    </button>
                                    <button class="clinic-tab" onclick="switchTab(event, 'career')">
                                        <i class="fa-solid fa-handshake"></i> Career Services
                                    </button>
                                    <button class="clinic-tab" onclick="switchTab(event, 'feedback')">
                                        <i class="fa-solid fa-star"></i> Feedback
                                    </button>
                                </div>

                                <!-- Tab Content -->
                                <div id="alumni-tab" class="tab-content clinic-content active">
                                    <div class="tab-panel-grid">
                                        <div class="tab-panel-main">
                                            <div class="panel-heading">
                                                <div>
                                                    <h4>Alumni Network Overview</h4>
                                                    <p>Discover alumni by course, year, employment status, and connection strength.</p>
                                                </div>
                                            </div>

                                            <div class="tab-panel-summary">
                                                <div class="summary-card">
                                                    <span>Total Alumni</span>
                                                    <strong><?= htmlspecialchars($alumniCount) ?></strong>
                                                </div>
                                                <div class="summary-card">
                                                    <span>Available Years</span>
                                                    <strong><?= count($alumniYears) ?></strong>
                                                </div>
                                                <div class="summary-card">
                                                    <span>Connection Type</span>
                                                    <strong>Professional & Mentorship</strong>
                                                </div>
                                            </div>

                                            <div class="filter-panel">
                                                <div class="panel-heading">
                                                    <h4>Search Alumni</h4>
                                                    <p>Live search by name, course, year, or company.</p>
                                                </div>
                                                <div class="filter-row">
                                                    <input type="search" id="student-alumni-search" placeholder="Search by name, course, company or job title..." oninput="loadStudentAlumni()">
                                                    <button type="button" onclick="loadStudentAlumni()">Search</button>
                                                </div>
                                                <div class="filter-row">
                                                    <select id="student-alumni-year" onchange="loadStudentAlumni()">
                                                        <option value="">All years</option>
                                                        <?php foreach ($alumniYears as $year): ?>
                                                            <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <select id="student-alumni-course" onchange="loadStudentAlumni()">
                                                        <option value="">All courses</option>
                                                        <?php foreach ($alumniCourses as $course): ?>
                                                            <?php $normalizedCourseOption = normalize_course_label((string)$course); ?>
                                                        <option value="<?= htmlspecialchars($normalizedCourseOption) ?>"><?= htmlspecialchars($normalizedCourseOption) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="filter-row">
                                                    <select id="student-alumni-status" onchange="loadStudentAlumni()">
                                                        <option value="">All statuses</option>
                                                        <option value="employed">Employed</option>
                                                        <option value="unemployed">Unemployed</option>
                                                        <option value="unknown">Unknown</option>
                                                    </select>
                                                    <div></div>
                                                </div>
                                            </div>

                                            <div id="student-alumni-groups"></div>
                                        </div>

                                    </div>
                                </div>

                                <div id="career-tab" class="tab-content clinic-content">
                                    <div class="tab-panel-grid">
                                        <div class="tab-panel-main">
                                            <div class="panel-heading">
                                                <div>
                                                    <h4>Career Services</h4>
                                                    <p>Find job-ready alumni, available career development resources, and community support.</p>
                                                </div>
                                            </div>

                                            <div class="filter-panel">
                                                <div class="panel-heading">
                                                    <h4>Job Seeker Search</h4>
                                                    <p>Filter active alumni with current employment for mentorship and opportunities.</p>
                                                </div>
                                                <div class="filter-row">
                                                    <input type="search" id="career-alumni-search" placeholder="Search employed alumni by name, course, or job title..." oninput="loadCareerAlumni()">
                                                    <button type="button" onclick="loadCareerAlumni()">Search</button>
                                                </div>
                                                <div class="filter-row">
                                                    <select id="career-alumni-year" onchange="loadCareerAlumni()">
                                                        <option value="">All years</option>
                                                        <?php foreach ($alumniYears as $year): ?>
                                                            <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div></div>
                                                </div>
                                            </div>

                                            <div id="career-alumni-list" class="career-list"></div>
                                        </div>

                                    </div>
                                </div>

                                <div id="feedback-tab" class="tab-content clinic-content">
                                    <div style="background: linear-gradient(135deg, #5C1F23 0%, #8B2E2F 55%, #A84A38 100%); border: 1px solid rgba(224, 178, 80, 0.35); border-radius: 16px; padding: 32px 28px; box-shadow: 0 8px 24px rgba(92, 31, 35, 0.25); display: flex; align-items: center; justify-content: space-between; gap: 24px;">
                                        <div style="flex: 1; color: #FBF4E4;">
                                            <h3 style="margin: 0 0 8px 0; font-size: 24px; font-weight: 700;">SSAA Feedback & Ratings</h3>
                                            <p style="margin: 0; font-size: 15px; opacity: 0.95; line-height: 1.6;">Share your feedback about our services to help us improve.</p>
                                        </div>
                                        <button type="button" id="rate-ssaa-service-btn" onclick="openSsaaFeedbackModal()" style="background: #FBF4E4; color: #8F0011; border: 1px solid #E0B250; padding: 14px 28px; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 15px; transition: all 0.3s ease; white-space: nowrap; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(45, 12, 15, 0.22);">
                                            <i class="fa-solid fa-star"></i> Rate Our Service
                                        </button>
                                    </div>
                                </div>
                            </div>
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
    <div class="page-overlay" id="pageOverlay"></div>

    <!-- SSAA Feedback Modal -->
    <div id="ssaaFeedbackModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Rate Our Services</h3>
                <button type="button" class="modal-close" onclick="closeSsaaFeedbackModal()">&times;</button>
            </div>
            <form id="ssaaFeedbackForm">
                <div class="modal-body" style="gap: 20px;">
                    <div style="text-align: center; margin: 20px 0;">
                        <div id="ssaaFeedbackMessage" class="feedback-message" style="display: none; margin-bottom: 15px; padding: 10px; border-radius: 8px;"></div>
                        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">How would you rate our SSAA services?</p>
                        <div style="display: flex; justify-content: center; gap: 10px; margin: 20px 0;">
                            <label style="cursor: pointer;">
                                <input type="radio" name="ssaa_rating" value="1" style="display: none;" onchange="updateSsaaStarColors(1)">
                                <span class="star" style="font-size: 32px; color: #ccc;">★</span>
                            </label>
                            <label style="cursor: pointer;">
                                <input type="radio" name="ssaa_rating" value="2" style="display: none;" onchange="updateSsaaStarColors(2)">
                                <span class="star" style="font-size: 32px; color: #ccc;">★</span>
                            </label>
                            <label style="cursor: pointer;">
                                <input type="radio" name="ssaa_rating" value="3" style="display: none;" onchange="updateSsaaStarColors(3)">
                                <span class="star" style="font-size: 32px; color: #ccc;">★</span>
                            </label>
                            <label style="cursor: pointer;">
                                <input type="radio" name="ssaa_rating" value="4" style="display: none;" onchange="updateSsaaStarColors(4)">
                                <span class="star" style="font-size: 32px; color: #ccc;">★</span>
                            </label>
                            <label style="cursor: pointer;">
                                <input type="radio" name="ssaa_rating" value="5" style="display: none;" onchange="updateSsaaStarColors(5)">
                                <span class="star" style="font-size: 32px; color: #ccc;">★</span>
                            </label>
                        </div>
                    </div>
                    <div style="margin: 20px 0;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Your Feedback *</label>
                        <textarea id="ssaaFeedbackComments" name="comments" placeholder="Share your feedback to help us improve..." required style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: 14px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeSsaaFeedbackModal()" style="background: #e2e8f0; color: #333; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="background: #800000; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;">Submit Feedback</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .feedback-message {
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
        }
        .feedback-message-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .feedback-message-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .star.filled {
            color: #f39c12 !important;
        }
    </style>

    <!-- Employment Update Modal -->
    <div id="employmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Employment Information</h3>
                <button class="modal-close" onclick="closeModal('employmentModal')">&times;</button>
            </div>
            <form id="employmentForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="employmentStatus">Employment Status *</label>
                        <select id="employmentStatus" name="employment_status" required>
                            <option value="employed">Employed</option>
                            <option value="unemployed">Unemployed</option>
                            <option value="unknown">Unknown</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="companyName">Company Name</label>
                        <input type="text" id="companyName" name="company_name">
                    </div>
                    <div class="form-group">
                        <label for="jobTitle">Job Title</label>
                        <input type="text" id="jobTitle" name="job_title">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('employmentModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Update Information</button>
                </div>
            </form>
        </div>
    </div>

    <div id="alumniDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3 id="modal-full-name">Alumni Name</h3>
                    <p id="modal-course-year" style="margin:0;color:#475569;font-size:.95rem;">Course · Graduation Year</p>
                </div>
                <button class="modal-close" onclick="closeModal('alumniDetailsModal')">&times;</button>
            </div>
            <div class="modal-body">
                    <div class="modal-row alumni-modal-identity">
                    <div class="modal-column">
                        <div id="modal-profile-image" class="alumni-card-image"></div>
                    </div>
                    <div class="modal-column modal-meta">
                        <div><strong>Status:</strong> <span id="modal-employment-status">N/A</span></div>
                        <div><strong>Company / Title:</strong> <span id="modal-company-title">N/A</span></div>
                        <div><strong>Contact:</strong> <span id="modal-contact">N/A</span></div>
                        <div><strong>Email:</strong> <span id="modal-email">N/A</span></div>
                        <div><strong>Location:</strong> <span id="modal-location">N/A</span></div>
                        <div><strong>Industry:</strong> <span id="modal-industry">N/A</span></div>
                        <div><strong>Salary Range:</strong> <span id="modal-salary-range">N/A</span></div>
                    </div>
                </div>
                    <div class="modal-row alumni-modal-content-row">
                    <div class="modal-column">
                        <div><strong>Job Description</strong></div>
                        <p id="modal-job-description" style="margin:0;color:#475569;line-height:1.6;">N/A</p>
                    </div>
                    <div class="modal-column">
                        <div><strong>Achievements</strong></div>
                        <p id="modal-achievements" style="margin:0;color:#475569;line-height:1.6;">N/A</p>
                    </div>
                </div>
                <div>
                    <div><strong>Quote</strong></div>
                    <p id="modal-quote" style="margin:0;color:#475569;line-height:1.6;">N/A</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" type="button" onclick="closeModal('alumniDetailsModal')">Close</button>
            </div>
        </div>
    </div>

    <div id="notification-container" class="notification-container"></div>

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

            // Load student and alumni data
            loadStudentAlumni();
            loadCareerAlumni();
        });

        function switchTab(event, tabName) {
            // Hide all tab contents (support both tab-content and clinic-content)
            const tabContents = document.querySelectorAll('.tab-content, .clinic-content');
            tabContents.forEach(content => content.classList.remove('active'));

            // Remove active class from all tab buttons (support both tab-button and clinic-tab)
            const tabButtons = document.querySelectorAll('.tab-button, .clinic-tab');
            tabButtons.forEach(button => button.classList.remove('active'));

            // Show selected tab content
            const panel = document.getElementById(tabName + '-tab');
            if (panel) panel.classList.add('active');

            // Add active class to clicked button
            const clickedButton = event && event.currentTarget ? event.currentTarget : event && event.target ? event.target : null;
            if (clickedButton && clickedButton.classList) {
                clickedButton.classList.add('active');
            }
        }

        function normalizeCourseLabel(course) {
            course = String(course || '').trim();
            const numericMatch = course.match(/^(.*)\s+(1|2|3|4)$/);
            if (numericMatch) {
                return numericMatch[1].trim();
            }
            const graduateMatch = course.match(/^(.*)\s+Graduate$/i);
            if (graduateMatch) {
                return graduateMatch[1].trim();
            }
            return course;
        }

        const alumniCourseOrder = [
            'bachelor of science in accountancy',
            'bachelor of science in business administration',
            'bachelor in elementary education',
            'bachelor of science in computer science',
            'bachelor of science in criminology',
            'bachelor of science in hospitality management',
            'bachelor of science in tourism management',
            'associate in computer technology',
            'associate in business knowledge',
            'associate in hotel management',
            'associate in tourism management'
        ];

        function getAlumniCourseRank(course) {
            const normalizedCourse = normalizeCourseLabel(course).toLowerCase();
            const aliases = {
                'associate in hospitality management': 'associate in hotel management'
            };
            const courseKey = aliases[normalizedCourse] || normalizedCourse;
            const rank = alumniCourseOrder.indexOf(courseKey);
            return rank === -1 ? alumniCourseOrder.length : rank;
        }

        function sortAlumniByCourse(alumni) {
            return alumni.sort((firstAlumni, secondAlumni) => {
                const courseRankDifference = getAlumniCourseRank(firstAlumni.course) - getAlumniCourseRank(secondAlumni.course);
                if (courseRankDifference !== 0) return courseRankDifference;
                return String(firstAlumni.full_name || '').localeCompare(String(secondAlumni.full_name || ''));
            });
        }

        window.alumniDetailsMap = window.alumniDetailsMap || {};

        function updateEmploymentInfo() {
            const modal = document.getElementById('employmentModal');
            <?php if ($user['role'] === 'student' && $isAlumni && $userAlumniData): ?>
            // Pre-fill current data for alumni
            document.getElementById('employmentStatus').value = '<?= $userAlumniData['employment_status'] ?>';
            document.getElementById('companyName').value = '<?= htmlspecialchars($userAlumniData['company_name'] ?? '') ?>';
            document.getElementById('jobTitle').value = '<?= htmlspecialchars($userAlumniData['job_title'] ?? '') ?>';
            <?php endif; ?>
            modal.classList.add('show');
        }

        document.getElementById('employmentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const response = await fetch('ssaa_api.php?action=update_employment', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Employment information updated successfully!', 'success');
                    closeModal('employmentModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(result.message || 'Error updating information', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error updating employment information', 'error');
            }
        });

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
        }

        async function loadStudentAlumni() {
            const search = document.getElementById('student-alumni-search').value.trim();
            const year = document.getElementById('student-alumni-year').value;
            const course = document.getElementById('student-alumni-course').value;
            const status = document.getElementById('student-alumni-status').value;
            const container = document.getElementById('student-alumni-groups');
            container.innerHTML = '<p>Loading alumni records...</p>';

            try {
                const params = new URLSearchParams({
                    action: 'get_alumni_list',
                    query: search,
                    year,
                    course,
                    status
                });
                const response = await fetch('ssaa_api.php?' + params.toString());
                const data = await response.json();
                if (!data.success || !Array.isArray(data.alumni)) {
                    container.innerHTML = '<p>No alumni found.</p>';
                    return;
                }

                if (data.alumni.length === 0) {
                    window.alumniDetailsMap = {};
                    container.innerHTML = '<p>No alumni found.</p>';
                    return;
                }

                window.alumniDetailsMap = {};
                const alumniByYear = data.alumni.reduce((acc, alum) => {
                    const gradYear = alum.graduation_year || 'Unknown';
                    if (!acc[gradYear]) acc[gradYear] = [];
                    acc[gradYear].push(alum);
                    return acc;
                }, {});

                const sortedYears = Object.keys(alumniByYear).sort((a, b) => b.localeCompare(a, undefined, { numeric: true }));
                container.innerHTML = sortedYears.map(yearKey => {
                    sortAlumniByCourse(alumniByYear[yearKey]);
                    return renderYearSection(yearKey, alumniByYear[yearKey]);
                }).join('');

                sortedYears.forEach(yearKey => setupCarouselNav(getCarouselId(yearKey)));
                attachAlumniCardListeners();
            } catch (error) {
                console.error('Error loading alumni:', error);
                container.innerHTML = '<p>Unable to load alumni records.</p>';
            }
        }

        function getCarouselId(year) {
            return `alumni-carousel-${year}`.replace(/\s+/g, '-').replace(/[^a-zA-Z0-9_-]/g, '').toLowerCase();
        }

        function renderYearSection(year, alumni) {
            const safeId = getCarouselId(year);
            const renderAlumniCard = (alum) => {
                window.alumniDetailsMap[alum.id] = alum;
                const profileImagePath = alum.profile_image && !alum.profile_image.startsWith('../') && !alum.profile_image.startsWith('/')
                    ? `../${alum.profile_image}`
                    : (alum.profile_image || '');
                const profileImageHtml = profileImagePath
                    ? `<img src="${profileImagePath}" alt="${alum.full_name}" />`
                    : '<span class="alumni-card-placeholder"><i class="fa-solid fa-user"></i></span>';
                const achievementsHtml = alum.achievements ? `<div class="alumni-card-achievements">Achievements: ${alum.achievements}</div>` : '';
                const quoteHtml = alum.quote ? `<div class="alumni-card-quote">“${alum.quote}”</div>` : '';

                return `
                    <div class="alumni-card" data-alumni-id="${alum.id}">
                        <div class="alumni-card-header">
                            <div class="alumni-card-image">${profileImageHtml}</div>
                            <strong class="alumni-card-title">${alum.full_name || 'Name unavailable'}</strong>
                        </div>
                        <div class="alumni-card-academic">
                            <div><span class="label">Course:</span> ${normalizeCourseLabel(alum.course) || 'Course N/A'}</div>
                            <div><span class="label">Graduation Year:</span> ${alum.graduation_year || 'Year N/A'}</div>
                        </div>
                        ${alum.employment_status && alum.employment_status.toLowerCase() === 'employed' ? `<div class="alumni-card-details">
                            ${alum.company && alum.company.trim() ? `<div><strong>Company:</strong> ${alum.company}</div>` : ''}
                            ${alum.job_title && alum.job_title.trim() ? `<div><strong>Job Title:</strong> ${alum.job_title}</div>` : ''}
                        </div>` : ''}
                        ${achievementsHtml}
                        ${quoteHtml}
                    </div>
                `;
            };

            const cardsHtml = alumni.map(renderAlumniCard).join('');
            const tabCount = Math.ceil(alumni.length / 20);
            const paginationHtml = tabCount > 1 ? `
                <div class="alumni-carousel-pagination" role="tablist" aria-label="${year} alumni pages">
                    ${Array.from({ length: tabCount }, (_, index) => `
                        <button class="alumni-carousel-page-button${index === 0 ? ' active' : ''}" type="button" data-target="${safeId}" data-page="${index}" role="tab" aria-selected="${index === 0 ? 'true' : 'false'}">${index + 1}</button>
                    `).join('')}
                </div>
            ` : '';

            return `
                <section class="alumni-year-section">
                    <h3 class="alumni-year-heading">${year}</h3>
                    <div class="alumni-carousel-wrapper">
                        <button class="carousel-nav carousel-prev" type="button" data-target="${safeId}">‹</button>
                        <div class="alumni-carousel-track" id="${safeId}">
                            ${cardsHtml}
                        </div>
                        <button class="carousel-nav carousel-next" type="button" data-target="${safeId}">›</button>
                        ${paginationHtml}
                    </div>
                </section>
            `;
        }

        function setupCarouselNav(trackId) {
            const track = document.getElementById(trackId);
            if (!track) return;
            const prevButton = track.parentElement.querySelector('.carousel-prev');
            const nextButton = track.parentElement.querySelector('.carousel-next');
            const cards = Array.from(track.querySelectorAll('.alumni-card'));
            const pageButtons = Array.from(track.parentElement.querySelectorAll('.alumni-carousel-page-button'));
            if (!prevButton || !nextButton || !cards.length) return;

            let scrollAmount = 0;
            const gap = 14;
            const cardWidth = cards[0].offsetWidth;

            const updateControls = () => {
                const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
                scrollAmount = Math.max(0, Math.min(scrollAmount, maxScroll));
                const activeTab = Math.min(pageButtons.length - 1, Math.floor(scrollAmount / ((cardWidth + gap) * 10)));
                pageButtons.forEach((button, index) => {
                    const active = index === activeTab;
                    button.classList.toggle('active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                prevButton.disabled = scrollAmount === 0;
                nextButton.disabled = scrollAmount >= maxScroll;
            };

            prevButton.addEventListener('click', () => {
                scrollAmount = Math.max(0, scrollAmount - (cardWidth + gap));
                track.scrollTo({ left: scrollAmount, behavior: 'smooth' });
            });
            nextButton.addEventListener('click', () => {
                const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
                scrollAmount = Math.min(maxScroll, scrollAmount + (cardWidth + gap));
                track.scrollTo({ left: scrollAmount, behavior: 'smooth' });
            });
            pageButtons.forEach(button => {
                button.addEventListener('click', () => {
                    scrollAmount = Math.min(track.scrollWidth - track.clientWidth, Number(button.dataset.page) * 10 * (cardWidth + gap));
                    track.scrollTo({ left: Math.max(0, scrollAmount), behavior: 'smooth' });
                });
            });
            track.addEventListener('scroll', () => {
                scrollAmount = track.scrollLeft;
                updateControls();
            }, { passive: true });
            window.addEventListener('resize', updateControls);
            updateControls();

            // Touch swipe support for mobile carousel
            let touchStartX = 0;
            let touchEndX = 0;
            let isScrolling = false;

            track.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
                isScrolling = false;
            }, false);

            track.addEventListener('touchmove', () => {
                isScrolling = true;
            }, false);

            track.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                if (!isScrolling) {
                    const diff = touchStartX - touchEndX;
                    if (Math.abs(diff) > 50) {
                        scrollAmount = Math.max(0, scrollAmount + (diff > 0 ? 1 : -1) * (cardWidth + gap));
                        track.scrollTo({ left: scrollAmount, behavior: 'smooth' });
                    }
                }
            }, false);
        }

        function attachAlumniCardListeners() {
            document.querySelectorAll('.alumni-card[data-alumni-id]').forEach(card => {
                card.addEventListener('click', () => {
                    const alumniId = card.getAttribute('data-alumni-id');
                    const alumni = window.alumniDetailsMap?.[alumniId];
                    if (alumni) {
                        showAlumniDetailsModal(alumni);
                    }
                });
            });
        }

        function showAlumniDetailsModal(alumni) {
            const modal = document.getElementById('alumniDetailsModal');
            document.getElementById('modal-full-name').textContent = alumni.full_name || 'N/A';
            document.getElementById('modal-course-year').textContent = `${normalizeCourseLabel(alumni.course)} · ${alumni.graduation_year || 'Year N/A'}`;
            document.getElementById('modal-company-title').textContent = `${alumni.company || 'N/A'} · ${alumni.job_title || 'N/A'}`;
            document.getElementById('modal-employment-status').textContent = alumni.employment_status || 'Unknown';
            document.getElementById('modal-contact').textContent = alumni.contact_number || 'N/A';
            document.getElementById('modal-email').textContent = alumni.email || 'N/A';
            document.getElementById('modal-location').textContent = alumni.location || 'N/A';
            document.getElementById('modal-industry').textContent = alumni.industry || 'N/A';
            document.getElementById('modal-salary-range').textContent = alumni.salary_range || 'N/A';
            document.getElementById('modal-job-description').textContent = alumni.job_description || 'N/A';
            document.getElementById('modal-achievements').textContent = alumni.achievements || 'N/A';
            document.getElementById('modal-quote').textContent = alumni.quote ? `“${alumni.quote}”` : 'No quote available.';
            const profileImg = document.getElementById('modal-profile-image');
            if (alumni.profile_image) {
                const profilePath = alumni.profile_image && !alumni.profile_image.startsWith('../') && !alumni.profile_image.startsWith('/')
                    ? `../${alumni.profile_image}`
                    : alumni.profile_image;
                profileImg.innerHTML = `<img src="${profilePath}" alt="${alumni.full_name}" />`;
            } else {
                profileImg.innerHTML = '<span class="alumni-card-placeholder"><i class="fa-solid fa-user"></i></span>';
            }
            modal.classList.add('show');
        }

        async function loadCareerAlumni() {
            const search = document.getElementById('career-alumni-search').value.trim();
            const year = document.getElementById('career-alumni-year').value;
            const list = document.getElementById('career-alumni-list');
            list.innerHTML = '<p>Loading employed alumni...</p>';

            try {
                const params = new URLSearchParams({
                    action: 'get_alumni_list',
                    query: search,
                    year,
                    status: 'employed'
                });
                const response = await fetch('ssaa_api.php?' + params.toString());
                const data = await response.json();
                if (!data.success || !Array.isArray(data.alumni)) {
                    list.innerHTML = '<p>No employed alumni found.</p>';
                    return;
                }

                if (data.alumni.length === 0) {
                    list.innerHTML = '<p>No employed alumni found.</p>';
                    return;
                }

                const renderCareerCard = alum => `
                    <div class="career-card">
                        <strong>${alum.full_name || ''}</strong>
                        <div class="career-meta">
                            <span class="pill pill-year">${alum.graduation_year || 'Year N/A'}</span>
                            <span>${normalizeCourseLabel(alum.course) || 'Course N/A'}</span>
                        </div>
                        <div>Company: ${alum.company || 'N/A'}</div>
                        <div>Job Title: ${alum.job_title || 'N/A'}</div>
                    </div>
                `;
                const pageSize = 40;
                const pages = [];
                for (let index = 0; index < data.alumni.length; index += pageSize) {
                    pages.push(data.alumni.slice(index, index + pageSize));
                }
                const pagesHtml = pages.map((page, index) => `
                    <div class="career-page${index === 0 ? ' active' : ''}" data-page="${index}">
                        ${page.map(renderCareerCard).join('')}
                    </div>
                `).join('');
                const paginationHtml = pages.length > 1 ? `
                    <div class="career-pagination" role="tablist" aria-label="Career alumni pages">
                        ${pages.map((_, index) => `
                            <button class="career-page-button${index === 0 ? ' active' : ''}" type="button" data-page="${index}" role="tab" aria-selected="${index === 0 ? 'true' : 'false'}">${index + 1}</button>
                        `).join('')}
                    </div>
                ` : '';
                list.innerHTML = pagesHtml + paginationHtml;
                list.querySelectorAll('.career-page-button').forEach(button => {
                    button.addEventListener('click', () => {
                        const selectedPage = Number(button.dataset.page);
                        list.querySelectorAll('.career-page').forEach((page, index) => {
                            page.classList.toggle('active', index === selectedPage);
                        });
                        list.querySelectorAll('.career-page-button').forEach((pageButton, index) => {
                            const active = index === selectedPage;
                            pageButton.classList.toggle('active', active);
                            pageButton.setAttribute('aria-selected', active ? 'true' : 'false');
                        });
                    });
                });
            } catch (error) {
                console.error('Error loading career alumni:', error);
                list.innerHTML = '<p>Unable to load employed alumni.</p>';
            }
        }

        function showNotification(message, type) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // Handle search button click
        const searchBtn = document.querySelector('.nav-search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                alert('Search functionality coming soon!');
            });
        }

        // SSAA Feedback Functions
        function openSsaaFeedbackModal() {
            const modal = document.getElementById('ssaaFeedbackModal');
            if (modal) modal.classList.add('show');
        }

        function closeSsaaFeedbackModal() {
            const modal = document.getElementById('ssaaFeedbackModal');
            if (modal) modal.classList.remove('show');
        }

        function updateSsaaStarColors(rating) {
            const stars = document.querySelectorAll('#ssaaFeedbackForm .star');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('filled');
                } else {
                    star.classList.remove('filled');
                }
            });
        }

        function showSsaaFeedbackMessage(text, type) {
            const msgDiv = document.getElementById('ssaaFeedbackMessage');
            if (msgDiv) {
                msgDiv.textContent = text;
                msgDiv.className = 'feedback-message feedback-message-' + type;
                msgDiv.style.display = 'block';
                setTimeout(() => msgDiv.style.display = 'none', 3000);
            }
        }

        // Load feedback on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Handle feedback form submission
            const feedbackForm = document.getElementById('ssaaFeedbackForm');
            if (feedbackForm) {
                feedbackForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const rating = document.querySelector('input[name="ssaa_rating"]:checked')?.value || null;
                    const comments = document.getElementById('ssaaFeedbackComments')?.value || '';

                    if (!rating) {
                        showSsaaFeedbackMessage('Please select a rating', 'error');
                        return;
                    }

                    if (!comments.trim()) {
                        showSsaaFeedbackMessage('Please enter your feedback', 'error');
                        return;
                    }

                    try {
                        const response = await fetch('../includes/api_ssaa_feedback.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'save',
                                rating: parseInt(rating),
                                comments: comments
                            }),
                            credentials: 'same-origin'
                        });

                        const result = await response.json();
                        if (result.success) {
                            showSsaaFeedbackMessage('Thank you! Your feedback has been submitted.', 'success');
                            feedbackForm.reset();
                            updateSsaaStarColors(0);
                            setTimeout(() => {
                                closeSsaaFeedbackModal();
                                loadAllSsaaFeedback();
                                loadSsaaFeedbackStats();
                            }, 1000);
                        } else {
                            showSsaaFeedbackMessage(result.message || 'Error submitting feedback', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showSsaaFeedbackMessage('Error submitting feedback', 'error');
                    }
                });
            }
        });
    </script>

    <script>
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
    <?php include '../AI CHAT BOT/AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
