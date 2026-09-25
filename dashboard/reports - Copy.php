<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

if ($user['role'] !== 'teacher' || $user['head_service'] !== 'guidance') {
    header('Location: guidance_home.php');
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
ensure_guidance_schema();

$pdo = get_db();

// Get case statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'under_counseling' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM guidance_cases
");
$caseStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get cases by concern type
$stmt = $pdo->query("
    SELECT type_of_concern, COUNT(*) as count
    FROM guidance_cases
    GROUP BY type_of_concern
    ORDER BY count DESC
");
$concernStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance stats
$stmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN attendance_status = 'attended' THEN 1 ELSE 0 END) as attended,
        SUM(CASE WHEN attendance_status = 'non_appearance' THEN 1 ELSE 0 END) as non_appearance,
        SUM(CASE WHEN attendance_status = 'pending' THEN 1 ELSE 0 END) as pending,
        COUNT(*) as total
    FROM guidance_sessions
");
$attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get resolution statistics
$stmt = $pdo->query("
    SELECT external_resolution, COUNT(*) as count
    FROM guidance_cases
    WHERE status = 'closed'
    GROUP BY external_resolution
    ORDER BY count DESC
");
$resolutionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly trend
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m-01') as month,
        COUNT(*) as count
    FROM guidance_cases
    GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
    ORDER BY month DESC
    LIMIT 12
");
$monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle export functionality
if (isset($_POST['export_report'])) {
    $reportType = $_POST['report_type'] ?? 'cases';
    $exportFormat = $_POST['export_format'] ?? 'excel';

    if ($exportFormat === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        $filename = "guidance_{$reportType}_report.csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if ($reportType === 'cases') {
            // Get detailed case information
            $stmt = $pdo->query("
                SELECT 
                    c.id as case_id,
                    c.case_number,
                    u.full_name as student_name,
                    c.course_year,
                    c.status,
                    c.type_of_concern,
                    c.created_at
                FROM guidance_cases c
                LEFT JOIN users u ON c.reported_student_id = u.id
                ORDER BY c.created_at DESC
            ");
            $caseDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "Case ID,Case Number,Student Name,Course Year,Status,Type of Concern,Created Date\r\n";
            foreach ($caseDetails as $case) {
                $caseId = $case['case_id'];
                $caseNumber = str_replace('"', '""', $case['case_number']);
                $studentName = str_replace('"', '""', $case['student_name'] ?: 'N/A');
                $courseYear = str_replace('"', '""', $case['course_year'] ?: 'N/A');
                $status = $case['status'];
                $concernType = str_replace('"', '""', $case['type_of_concern'] ?: 'N/A');
                $createdDate = $case['created_at'];
                echo '"' . $caseId . '","' . $caseNumber . '","' . $studentName . '","' . $courseYear . '","' . $status . '","' . $concernType . '","' . $createdDate . '"\r\n';
            }
            // Add total row
            echo '"TOTAL","' . count($caseDetails) . ' cases","","","","",""\r\n';
        } elseif ($reportType === 'concerns') {
            // Get detailed concern information
            $stmt = $pdo->query("
                SELECT 
                    c.id as case_id,
                    c.case_number,
                    u.full_name as student_name,
                    c.course_year,
                    c.type_of_concern,
                    c.status,
                    c.created_at
                FROM guidance_cases c
                LEFT JOIN users u ON c.reported_student_id = u.id
                ORDER BY c.type_of_concern ASC, c.created_at DESC
            ");
            $concernDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "Case ID,Case Number,Student Name,Course Year,Type of Concern,Status,Created Date\r\n";
            foreach ($concernDetails as $concern) {
                $caseId = $concern['case_id'];
                $caseNumber = str_replace('"', '""', $concern['case_number']);
                $studentName = str_replace('"', '""', $concern['student_name'] ?: 'N/A');
                $courseYear = str_replace('"', '""', $concern['course_year'] ?: 'N/A');
                $concernType = str_replace('"', '""', $concern['type_of_concern'] ?: 'N/A');
                $status = $concern['status'];
                $createdDate = $concern['created_at'];
                echo '"' . $caseId . '","' . $caseNumber . '","' . $studentName . '","' . $courseYear . '","' . $concernType . '","' . $status . '","' . $createdDate . '"\r\n';
            }
            // Add total row
            echo '"TOTAL","' . count($concernDetails) . ' cases","","","","",""\r\n';
        } elseif ($reportType === 'attendance') {
            echo "Attendance Status,Count\r\n";
            echo "Attended," . ($attendanceStats['attended'] ?? 0) . "\r\n";
            echo "Non-Appearance," . ($attendanceStats['non_appearance'] ?? 0) . "\r\n";
            echo "Pending," . ($attendanceStats['pending'] ?? 0) . "\r\n";
            echo "Total Sessions," . ($attendanceStats['total'] ?? 0) . "\r\n";
        } elseif ($reportType === 'resolutions') {
            echo "Resolution Type,Count\r\n";
            $totalResolutions = 0;
            foreach ($resolutionStats as $resolution) {
                $resolutionType = str_replace('"', '""', str_replace('_', ' ', ucfirst($resolution['external_resolution'] ?: 'N/A')));
                echo '"' . $resolutionType . '","' . $resolution['count'] . '"\r\n';
                $totalResolutions += $resolution['count'];
            }
            // Add total row
            echo '"TOTAL RESOLUTIONS","' . $totalResolutions . '"\r\n';
        }
    } elseif ($exportFormat === 'docs') {
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        $filename = "guidance_{$reportType}_report.doc";
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo "<html><head><title>Guidance {$reportType} Report</title></head><body>";
        echo "<h1>Guidance " . ucfirst($reportType) . " Report</h1>";
        echo "<p>Generated on: " . date('Y-m-d H:i:s') . "</p><br>";

        if ($reportType === 'cases') {
            // Get detailed case information
            $stmt = $pdo->query("
                SELECT 
                    c.id as case_id,
                    c.case_number,
                    u.full_name as student_name,
                    c.course_year,
                    c.status,
                    c.type_of_concern,
                    c.created_at
                FROM guidance_cases c
                LEFT JOIN users u ON c.reported_student_id = u.id
                ORDER BY c.created_at DESC
            ");
            $caseDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<h2>Detailed Case Information</h2>";
            echo "<table border='1'><tr><th>Case ID</th><th>Case Number</th><th>Student Name</th><th>Course Year</th><th>Status</th><th>Type of Concern</th><th>Created Date</th></tr>";
            foreach ($caseDetails as $case) {
                echo "<tr><td>" . $case['case_id'] . "</td><td>" . htmlspecialchars($case['case_number']) . "</td><td>" . htmlspecialchars($case['student_name'] ?: 'N/A') . "</td><td>" . htmlspecialchars($case['course_year'] ?: 'N/A') . "</td><td>" . $case['status'] . "</td><td>" . htmlspecialchars($case['type_of_concern'] ?: 'N/A') . "</td><td>" . $case['created_at'] . "</td></tr>";
            }
            // Add total row
            echo "<tr style='background-color: #f0f0f0; font-weight: bold;'><td colspan='6'>TOTAL CASES</td><td>" . count($caseDetails) . "</td></tr>";
            echo "</table>";
        } elseif ($reportType === 'concerns') {
            // Get detailed concern information
            $stmt = $pdo->query("
                SELECT 
                    c.id as case_id,
                    c.case_number,
                    u.full_name as student_name,
                    c.course_year,
                    c.type_of_concern,
                    c.status,
                    c.created_at
                FROM guidance_cases c
                LEFT JOIN users u ON c.reported_student_id = u.id
                ORDER BY c.type_of_concern ASC, c.created_at DESC
            ");
            $concernDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<h2>Cases by Concern Type</h2>";
            echo "<table border='1'><tr><th>Case ID</th><th>Case Number</th><th>Student Name</th><th>Course Year</th><th>Type of Concern</th><th>Status</th><th>Created Date</th></tr>";
            foreach ($concernDetails as $concern) {
                echo "<tr><td>" . $concern['case_id'] . "</td><td>" . htmlspecialchars($concern['case_number']) . "</td><td>" . htmlspecialchars($concern['student_name'] ?: 'N/A') . "</td><td>" . htmlspecialchars($concern['course_year'] ?: 'N/A') . "</td><td>" . htmlspecialchars($concern['type_of_concern'] ?: 'N/A') . "</td><td>" . $concern['status'] . "</td><td>" . $concern['created_at'] . "</td></tr>";
            }
            // Add total row
            echo "<tr style='background-color: #f0f0f0; font-weight: bold;'><td colspan='6'>TOTAL CASES</td><td>" . count($concernDetails) . "</td></tr>";
            echo "</table>";
        } elseif ($reportType === 'attendance') {
            echo "<h2>Session Attendance</h2>";
            echo "<table border='1'><tr><th>Status</th><th>Count</th></tr>";
            echo "<tr><td>Attended</td><td>" . ($attendanceStats['attended'] ?? 0) . "</td></tr>";
            echo "<tr><td>Non-Appearance</td><td>" . ($attendanceStats['non_appearance'] ?? 0) . "</td></tr>";
            echo "<tr><td>Pending</td><td>" . ($attendanceStats['pending'] ?? 0) . "</td></tr>";
            echo "<tr><td>Total Sessions</td><td>" . ($attendanceStats['total'] ?? 0) . "</td></tr>";
            echo "</table>";
        } elseif ($reportType === 'resolutions') {
            echo "<h2>Resolution Outcomes</h2>";
            echo "<table border='1'><tr><th>Resolution Type</th><th>Count</th></tr>";
            $totalResolutions = 0;
            foreach ($resolutionStats as $resolution) {
                echo "<tr><td>" . htmlspecialchars(str_replace('_', ' ', ucfirst($resolution['external_resolution'] ?: 'N/A'))) . "</td><td>" . $resolution['count'] . "</td></tr>";
                $totalResolutions += $resolution['count'];
            }
            // Add total row
            echo "<tr style='background-color: #f0f0f0; font-weight: bold;'><td>TOTAL RESOLUTIONS</td><td>" . $totalResolutions . "</td></tr>";
            echo "</table>";
        }
        echo "</body></html>";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | Guidance System</title>
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

        .card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; background: white; margin-bottom: 16px; }
        .card h3 { margin-top: 0; color: #1f2937; }
        .metric-card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; background: white; }
        .metric-card h3 { margin: 0 0 8px 0; color: #6b7280; font-size: 14px; font-weight: 500; text-transform: uppercase; }
        .metric-card .value { font-size: 32px; font-weight: 700; color: #1f2937; margin: 8px 0; }
        .metric-card .status { font-size: 13px; color: #6b7280; }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-info { background: #dbeafe; border-left: 4px solid #3b82f6; color: #1e40af; }
        .stats-table { width: 100%; border-collapse: collapse; }
        .stats-table th, .stats-table td { padding: 12px 10px; border: 1px solid #e2e8f0; text-align: left; }
        .stats-table th { background: #f8fafc; font-weight: 600; }
        .stats-table tr:hover { background: #f9fafb; }
        .progress-bar { height: 24px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #3b82f6; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 600; }
        .chart-container { background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
        .legend { display: flex; flex-wrap: wrap; gap: 16px; font-size: 14px; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-color { width: 12px; height: 12px; border-radius: 2px; }
        @media (max-width: 720px) {
            .page-shell { padding: 0 12px; }
            .topbar, .card, .metric-card, .chart-container, .tabs-header { width: 100%; }
            .main-scroll { padding: 0; }
            .dashboard-intro h1 { font-size: 24px; }
            .dashboard-intro p { font-size: 14px; }
            .metric-card { padding: 16px; }
            .metric-card .value { font-size: 26px; }
            .metric-card .status { font-size: 12px; }
            .card { padding: 16px; border-radius: 14px; }
            .card h3 { font-size: 18px; }
            .card form > div { display: block; }
            .card form > div > div { width: 100%; }
            .card form button { width: 100%; justify-content: center; }
            .stats-table, .students-table { width: 100%; }
            .stats-table th, .stats-table td, .students-table th, .students-table td { padding: 10px 8px; font-size: 12px; }
            .stats-table thead, .students-table thead { display: none; }
            .stats-table tr, .students-table tr { display: block; width: 100%; margin-bottom: 14px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
            .stats-table td, .students-table td { display: block; width: 100%; border: none; border-bottom: 1px solid #e2e8f0; padding: 10px 0; }
            .stats-table td:last-child, .students-table td:last-child { border-bottom: none; }
            .stats-table td::before, .students-table td::before { content: attr(data-label); font-weight: 700; display: block; margin-bottom: 6px; color: #334155; }
            .progress-bar { height: 20px; }
            .progress-fill { font-size: 11px; }
            .chart-container { padding: 14px; }
            .legend { gap: 12px; }
            .legend-item { font-size: 13px; }
            .card h4, .card p, .metric-card h3 { font-size: 14px; }
            .card form select, .card form input, .card form textarea { font-size: 14px; }
            .card form label { font-size: 13px; }
        }
        @media (max-width: 480px) {
            .dashboard-intro h1 { font-size: 22px; }
            .dashboard-intro p { font-size: 13px; }
            .metric-card { padding: 14px; }
            .card { padding: 14px; }
            .card h3 { font-size: 16px; }
            .tab-button { min-width: 110px; padding: 10px 10px; font-size: 12px; }
            .stats-table td::before, .students-table td::before { font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Guidance Counselor</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="guidance_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
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
                        <a href="librarian_home.php" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="clinic_dashboard.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc_head_home.php" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="scholarship_dashboard.php" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="ssaa_student_home.php" data-tooltip="Alumni">
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
                        <a href="reports.php" class="active" data-tooltip="Reports">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span class="nav-text">Reports</span>
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
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Guidance Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Guidance Counselor</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <!-- support icon removed per UI update -->

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
                <section class="dashboard-intro">
                    <div>
                        <p class="eyebrow" style="color: #000000;">Reports & Analytics</p>
                        <h1 style="color: #000000;">Guidance statistics and insights</h1>
                        <p class="dashboard-subtitle" style="color: #000000;">Analyze case statistics, session attendance, concern types, and resolution outcomes in one comprehensive view.</p>
                    </div>
                </section>

                    <!-- CASE STATISTICS OVERVIEW -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        <div class="metric-card">
                            <h3>Total Cases</h3>
                            <div class="value"><?= (int)$caseStats['total'] ?></div>
                            <div class="status">All time submissions</div>
                        </div>
                        <div class="metric-card">
                            <h3>Pending Cases</h3>
                            <div class="value"><?= (int)$caseStats['pending'] ?></div>
                            <div class="status">Awaiting review</div>
                        </div>
                        <div class="metric-card">
                            <h3>Active Cases</h3>
                            <div class="value"><?= (int)$caseStats['active'] ?></div>
                            <div class="status">Under counseling</div>
                        </div>
                        <div class="metric-card">
                            <h3>Closed Cases</h3>
                            <div class="value"><?= (int)$caseStats['closed'] ?></div>
                            <div class="status">Resolved</div>
                        </div>
                    </div>

                    <!-- EXPORT REPORTS SECTION -->
                    <div class="card">
                        <h3><i class="fa-solid fa-file-export"></i> Export Reports</h3>
                        <div style="margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                            <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                <li><strong>Cases:</strong> Detailed case information with case ID, student name, course year, and status</li>
                                <li><strong>Concerns:</strong> Cases grouped by concern type with case ID, student name, and course year</li>
                                <li><strong>Attendance:</strong> Session attendance statistics</li>
                                <li><strong>Resolutions:</strong> Resolution outcomes for closed cases</li>
                            </ul>
                            <p style="margin: 16px 0 0; font-size: 13px; color: #64748b;">Export data in multiple formats for reporting and analysis.</p>
                        </div>
                        <form method="post" style="width: 100%;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label for="report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                    <select name="report_type" id="report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                        <option value="cases">Detailed Case Information</option>
                                        <option value="concerns">Cases by Concern Type</option>
                                        <option value="attendance">Session Attendance</option>
                                        <option value="resolutions">Resolution Outcomes</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Export Format</label>
                                    <select name="export_format" id="export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                        <option value="excel">Excel (CSV)</option>
                                        <option value="docs">Word Document</option>
                                    </select>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: flex-start;">
                                <button type="submit" name="export_report" value="1" style="padding: 14px 28px; background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
                                    <i class="fa-solid fa-download"></i>
                                    Export Report
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- CASE STATUS BREAKDOWN -->
                    <div class="card">
                        <h3><i class="fa-solid fa-chart-bar"></i> Case Status Distribution</h3>
                        <div class="chart-container">
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Pending Review</strong></span>
                                    <span><?= (int)$caseStats['pending'] ?> cases</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $caseStats['total'] > 0 ? (((int)$caseStats['pending'] / (int)$caseStats['total']) * 100) : 0 ?>%">
                                        <?= $caseStats['total'] > 0 ? round(((int)$caseStats['pending'] / (int)$caseStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Active Cases</strong></span>
                                    <span><?= (int)$caseStats['active'] ?> cases</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="background: #10b981; width: <?= $caseStats['total'] > 0 ? (((int)$caseStats['active'] / (int)$caseStats['total']) * 100) : 0 ?>%">
                                        <?= $caseStats['total'] > 0 ? round(((int)$caseStats['active'] / (int)$caseStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Closed Cases</strong></span>
                                    <span><?= (int)$caseStats['closed'] ?> cases</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="background: #6b7280; width: <?= $caseStats['total'] > 0 ? (((int)$caseStats['closed'] / (int)$caseStats['total']) * 100) : 0 ?>%">
                                        <?= $caseStats['total'] > 0 ? round(((int)$caseStats['closed'] / (int)$caseStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CONCERNS BY TYPE -->
                    <div class="card">
                        <h3><i class="fa-solid fa-magnifying-glass"></i> Cases by Concern Type</h3>
                        <table class="stats-table">
                            <thead>
                                <tr>
                                    <th>Concern Type</th>
                                    <th>Number of Cases</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($concernStats as $concern): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($concern['type_of_concern']) ?></td>
                                        <td><?= (int)$concern['count'] ?></td>
                                        <td><?= $caseStats['total'] > 0 ? round(((int)$concern['count'] / (int)$caseStats['total']) * 100, 1) : 0 ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- ATTENDANCE STATISTICS -->
                    <div class="card">
                        <h3><i class="fa-solid fa-calendar-days"></i> Session Attendance Statistics</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                            <div class="metric-card">
                                <h3>Total Sessions</h3>
                                <div class="value"><?= (int)$attendanceStats['total'] ?></div>
                            </div>
                            <div class="metric-card">
                                <h3>Attended</h3>
                                <div class="value" style="color: #10b981;"><?= (int)$attendanceStats['attended'] ?></div>
                            </div>
                            <div class="metric-card">
                                <h3>Non-Appearance</h3>
                                <div class="value" style="color: #ef4444;"><?= (int)$attendanceStats['non_appearance'] ?></div>
                            </div>
                            <div class="metric-card">
                                <h3>Pending</h3>
                                <div class="value" style="color: #f59e0b;"><?= (int)$attendanceStats['pending'] ?></div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Attended</strong></span>
                                    <span><?= (int)$attendanceStats['attended'] ?> sessions</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="background: #10b981; width: <?= $attendanceStats['total'] > 0 ? (((int)$attendanceStats['attended'] / (int)$attendanceStats['total']) * 100) : 0 ?>%">
                                        <?= $attendanceStats['total'] > 0 ? round(((int)$attendanceStats['attended'] / (int)$attendanceStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Non-Appearance</strong></span>
                                    <span><?= (int)$attendanceStats['non_appearance'] ?> sessions</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="background: #ef4444; width: <?= $attendanceStats['total'] > 0 ? (((int)$attendanceStats['non_appearance'] / (int)$attendanceStats['total']) * 100) : 0 ?>%">
                                        <?= $attendanceStats['total'] > 0 ? round(((int)$attendanceStats['non_appearance'] / (int)$attendanceStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RESOLUTION OUTCOMES -->
                    <div class="card">
                        <h3><i class="fa-solid fa-check"></i> Resolution Outcomes - Closed Cases</h3>
                        <table class="stats-table">
                            <thead>
                                <tr>
                                    <th>Resolution Type</th>
                                    <th>Number of Cases</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resolutionStats)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #6b7280;">No closed cases yet</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resolutionStats as $resolution): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(str_replace('_', ' ', ucfirst($resolution['external_resolution']))) ?></td>
                                            <td><?= (int)$resolution['count'] ?></td>
                                            <td><?= $caseStats['closed'] > 0 ? round(((int)$resolution['count'] / (int)$caseStats['closed']) * 100, 1) : 0 ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mobileToggle = document.getElementById('mobileMenuToggle');
            const pageOverlay = document.getElementById('pageOverlay');
            const sideNav = document.querySelector('.side-nav');

            function openMobileNav() {
                if (sideNav) sideNav.classList.add('mobile-open');
                if (pageOverlay) pageOverlay.classList.add('active');
            }

            function closeMobileNav() {
                if (sideNav) sideNav.classList.remove('mobile-open');
                if (pageOverlay) pageOverlay.classList.remove('active');
            }

            if (mobileToggle) {
                mobileToggle.addEventListener('click', function () {
                    if (sideNav && sideNav.classList.contains('mobile-open')) {
                        closeMobileNav();
                    } else {
                        openMobileNav();
                    }
                });
            }

            if (pageOverlay) {
                pageOverlay.addEventListener('click', closeMobileNav);
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeMobileNav();
            });
        });
    </script>
</body>
</html>
