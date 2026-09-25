<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = false;

$pdo = get_db();

// Get comprehensive SSAA statistics
$ssaaStats = [];

// Alumni statistics
$alumniStats = $pdo->query("
    SELECT
        COUNT(*) as total_alumni,
        SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) as employed_count,
        SUM(CASE WHEN employment_status = 'Unemployed' THEN 1 ELSE 0 END) as unemployed_count,
        SUM(CASE WHEN employment_status = 'unknown' THEN 1 ELSE 0 END) as unknown_count,
        ROUND(AVG(CASE WHEN graduation_year IS NOT NULL AND graduation_year != '' THEN YEAR(CURDATE()) - graduation_year ELSE NULL END), 1) as avg_years_since_graduation
    FROM ssaa_alumni
")->fetch(PDO::FETCH_ASSOC);

$alumniStats['employment_rate'] = $alumniStats['total_alumni'] > 0 ? round(($alumniStats['employed_count'] / $alumniStats['total_alumni']) * 100, 1) : 0;
$ssaaStats['alumni'] = $alumniStats;

// Student progression statistics
$progressStats = $pdo->query("
    SELECT
        COUNT(*) as total_students,
        SUM(CASE WHEN academic_status = 'passed' THEN 1 ELSE 0 END) as passed_count,
        SUM(CASE WHEN academic_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN academic_status = 'irregular' THEN 1 ELSE 0 END) as irregular_count,
        ROUND((SUM(CASE WHEN academic_status = 'passed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as pass_rate
    FROM ssaa_student_progression
    WHERE academic_year LIKE '%4' OR academic_year LIKE '%4th%'
")->fetch(PDO::FETCH_ASSOC);

$ssaaStats['progression'] = $progressStats;

// Graduation events statistics
$graduationStats = $pdo->query("
    SELECT
        COUNT(*) as total_events,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_events,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_events,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_events,
        ROUND(AVG(CASE WHEN graduates_count IS NOT NULL THEN graduates_count ELSE NULL END), 0) as avg_attendance
    FROM ssaa_graduation_events
")->fetch(PDO::FETCH_ASSOC);

$ssaaStats['graduation'] = $graduationStats;

// Employment trends by graduation year
$employmentTrends = $pdo->query("
    SELECT
        graduation_year,
        COUNT(*) as total_graduates,
        SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) as employed_count,
        ROUND((SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as employment_rate
    FROM ssaa_alumni
    WHERE graduation_year IS NOT NULL
    GROUP BY graduation_year
    ORDER BY graduation_year DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Alumni analytics by course and graduation year
$alumniByCourseYear = $pdo->query("
    SELECT
        course,
        graduation_year,
        COUNT(*) as total_alumni,
        SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) as employed_count,
        ROUND((SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as employment_rate
    FROM ssaa_alumni
    WHERE course IS NOT NULL AND course != ''
      AND graduation_year IS NOT NULL AND graduation_year != ''
    GROUP BY course, graduation_year
    ORDER BY course ASC, graduation_year DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | SSAA Management | PASS Support System</title>
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
        .topbar-divider { width: 1px; height: 24px; background: #e9ecef; margin: 0 12px; }
        .mic-button { background: none; border: none; cursor: pointer; color: #666; padding: 0 8px; }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 24px 0;
        }

        .overview-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .overview-card h3 {
            color: #2c3e50;
            margin-bottom: 16px;
            font-size: 1.2rem;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .metric-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #3498db;
            display: block;
        }

        .metric-label {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .report-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 24px 0;
        }

        .action-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .action-card:hover {
            transform: translateY(-2px);
        }

        .action-icon {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 16px;
        }

        .action-button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 12px;
        }

        .action-button:hover {
            background: #2980b9;
        }

        .chart-subtitle {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .chart-filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 18px 0 12px;
        }

        .chart-filter-row label {
            color: #374151;
            font-weight: 600;
        }

        .chart-filter-row input {
            flex: 1;
            min-width: 200px;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #ffffff;
            color: #111827;
        }

        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 24px 0;
        }

        .chart-container canvas {
            width: 100% !important;
            max-width: 100%;
            min-height: 240px;
            max-height: 360px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-placeholder {
            height: 200px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .trend-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .trend-table th,
        .trend-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .trend-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .employment-rate {
            color: #27ae60;
            font-weight: 600;
        }

        /* Expandable Navigation Styles */

        .report-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 24px 0 16px;
        }

        .report-tab {
            padding: 12px 22px;
            border-radius: 999px;
            border: 1px solid #dee2e6;
            background: white;
            color: #3b4255;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
        }

        .report-tab.active {
            background: #c49b34;
            color: white;
            border-color: #c49b34;
        }

        .report-tab:hover {
            background: #f2f4f7;
        }

        .report-tab-panel {
            display: none;
        }

        .report-tab-panel.active {
            display: block;
        }

        .custom-report-form {
            padding: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.95rem;
            color: #374151;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #ffffff;
            color: #1f2937;
        }

        .form-actions {
            align-self: flex-end;
            display: flex;
            justify-content: flex-end;
        }

        [data-tooltip] {
            position: relative;
        }

        .side-nav.collapsed [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: #333;
            color: #fff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            margin-left: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .side-nav.collapsed [data-tooltip]:hover::before {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-right-color: #333;
            margin-left: 2px;
            z-index: 1000;
        }
        /* Mobile adjustments for main content (720px -> 320px) */
        @media (max-width: 768px) {
            .content-panel { padding: 12px; }

            .stats-overview { gap: 12px; }

            .overview-card { padding: 16px; }
            .overview-card h3 { font-size: 1rem; }

            .metric-grid { grid-template-columns: 1fr; gap: 10px; }
            .metric-item { padding: 10px; }
            .metric-value { font-size: 1.25rem; }

            .report-tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
            .report-tab { flex: 0 0 auto; padding: 10px 16px; margin-right: 8px; }

            .chart-container { padding: 16px; margin: 16px 0; }
            .chart-container canvas { min-height: 180px; max-height: 320px; }

            .chart-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .chart-actions { display: flex; gap: 8px; width: 100%; }
            .chart-actions .action-button { flex: 1; }

            .chart-filter-row { flex-direction: column; align-items: stretch; }
            .chart-filter-row label { margin-bottom: 6px; }
            .chart-filter-row input { min-width: 0; width: 100%; }

            /* Trend tables -> card rows */
            .trend-table, .trend-table thead, .trend-table tbody, .trend-table th, .trend-table td, .trend-table tr {
                display: block;
                width: 100%;
            }
            .trend-table thead { display: none; }
            .trend-table tbody tr { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 12px; padding: 10px; box-shadow: 0 6px 18px rgba(15,23,42,0.04); }
            .trend-table td { display: grid; grid-template-columns: 45% 55%; padding: 8px 10px; border: none; border-bottom: 1px solid #f3f4f6; }
            .trend-table td:last-child { border-bottom: none; }
            .trend-table td::before { display: block; margin-bottom: 6px; color: #6b7280; font-weight: 700; }
            .trend-table td:nth-child(1)::before { content: attr(data-label) ""; }

            /* Custom report form spacing */
            .custom-report-form .form-grid { grid-template-columns: 1fr; }
            .form-actions { justify-content: stretch; }
            .form-actions .action-button { width: 100%; }

            /* Make all report card containers full bleed on mobile */
            .report-tab-panel .chart-container {
                position: relative;
                left: 50%;
                right: 50%;
                width: 100vw;
                max-width: 100vw;
                margin-left: -50vw;
                margin-right: -50vw;
                padding-left: 18px;
                padding-right: 18px;
                box-sizing: border-box;
                border-radius: 0;
            }

            .report-tab-panel .chart-header,
            .report-tab-panel .chart-filter-row,
            .report-tab-panel .chart-actions {
                width: 100%;
            }

            .report-tab-panel .chart-actions .action-button {
                flex: 1;
                min-width: 0;
            }
        }

        @media (max-width: 425px) {
            .content-panel { padding: 8px; }
            .overview-card { padding: 12px; }
            .chart-container { padding: 12px; }
            .report-tab { padding: 8px 12px; }
            .report-tab-panel .chart-container {
                position: relative;
                left: 50%;
                right: 50%;
                width: 100vw;
                max-width: 100vw;
                margin-left: -50vw;
                margin-right: -50vw;
                padding-left: 14px;
                padding-right: 14px;
                box-sizing: border-box;
                border-radius: 0;
            }
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
                        <a href="reports_analytics.php" class="active" data-tooltip="Reports & Analytics">
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
                <div class="content-panel">
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow" style="color: #000000;">SSAA Management - Reports & Analytics</p>
                            <h1 style="color: #000000;">Reports & Analytics</h1>
                            <p class="dashboard-subtitle" style="color: #000000;">SSAA-focused analytics for alumni outcomes, student progression, graduation events, and career readiness across the support system.</p>
                        </div>
                        <div class="dashboard-summary">
                            <div>
                                <span>Total Alumni</span>
                                <strong><?= htmlspecialchars($ssaaStats['alumni']['total_alumni'] ?? 0) ?></strong>
                            </div>
                            <div>
                                <span>Pass Rate</span>
                                <strong><?= htmlspecialchars($ssaaStats['progression']['pass_rate'] ?? 0) ?>%</strong>
                            </div>
                            <div>
                                <span>Employment Rate</span>
                                <strong><?= htmlspecialchars($ssaaStats['alumni']['employment_rate'] ?? 0) ?>%</strong>
                            </div>
                        </div>
                    </section>

                    <!-- Statistics Overview -->
                    <div class="stats-overview">
                        <div class="overview-card">
                            <h3><i class="fa-solid fa-users"></i> Alumni Overview</h3>
                            <div class="metric-grid">
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['alumni']['total_alumni'] ?? 0 ?></span>
                                    <span class="metric-label">Total Alumni</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['alumni']['employed_count'] ?? 0 ?></span>
                                    <span class="metric-label">Employed</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['alumni']['unemployed_count'] ?? 0 ?></span>
                                    <span class="metric-label">Unemployed</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['alumni']['avg_years_since_graduation'] ?? 0 ?> yrs</span>
                                    <span class="metric-label">Avg Time Since Grad</span>
                                </div>
                            </div>
                        </div>

                        <div class="overview-card">
                            <h3><i class="fa-solid fa-graduation-cap"></i> Student Progression</h3>
                            <div class="metric-grid">
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['progression']['total_students'] ?? 0 ?></span>
                                    <span class="metric-label">Total Students</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['progression']['passed_count'] ?? 0 ?></span>
                                    <span class="metric-label">Passed</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['progression']['pass_rate'] ?? 0 ?>%</span>
                                    <span class="metric-label">Pass Rate</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['progression']['failed_count'] ?? 0 ?></span>
                                    <span class="metric-label">Failed</span>
                                </div>
                            </div>
                        </div>

                        <div class="overview-card">
                            <h3><i class="fa-solid fa-calendar"></i> Graduation Events</h3>
                            <div class="metric-grid">
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['graduation']['total_events'] ?? 0 ?></span>
                                    <span class="metric-label">Total Events</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['graduation']['completed_events'] ?? 0 ?></span>
                                    <span class="metric-label">Completed</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['graduation']['upcoming_events'] ?? 0 ?></span>
                                    <span class="metric-label">Upcoming</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['graduation']['avg_attendance'] ?? 0 ?></span>
                                    <span class="metric-label">Avg Attendance</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Report Tabs -->
                    <div class="report-tabs" role="tablist" aria-label="Report categories">
                        <button id="tab-alumni-button" class="report-tab active" type="button" data-target="tab-alumni" role="tab" aria-controls="tab-alumni" aria-selected="true">Alumni Reports</button>
                        <button id="tab-progression-button" class="report-tab" type="button" data-target="tab-progression" role="tab" aria-controls="tab-progression" aria-selected="false">Progression Reports</button>
                        <button id="tab-events-button" class="report-tab" type="button" data-target="tab-events" role="tab" aria-controls="tab-events" aria-selected="false">Event Reports</button>
                        <button id="tab-custom-button" class="report-tab" type="button" data-target="tab-custom" role="tab" aria-controls="tab-custom" aria-selected="false">Custom Reports</button>
                    </div>

                    <div class="report-tab-panels">
                        <section id="tab-alumni" class="report-tab-panel active" role="tabpanel" aria-labelledby="tab-alumni-button">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <div>
                                        <h3><i class="fa-solid fa-chart-line"></i> Employment Trends by Graduation Year</h3>
                                        <p class="chart-subtitle">Zoom and pan the timeline to inspect longer graduation year ranges.</p>
                                    </div>
                                    <div class="chart-actions">
                                        <button class="action-button" onclick="resetEmploymentTrendZoom()">Reset Zoom</button>
                                        <button class="action-button" onclick="exportChart()">Export Data</button>
                                    </div>
                                </div>
                                <div class="chart-filter-row">
                                    <label for="employment-trend-search">Filter by course or year:</label>
                                    <input id="employment-trend-search" type="search" placeholder="Type course or year" oninput="filterCourseYearTable()" />
                                </div>
                                <canvas id="employmentTrendChart" height="220"></canvas>
                                <table class="trend-table" id="employment-trend-table">
                                    <thead>
                                        <tr>
                                            <th>Graduation Year</th>
                                            <th>Total Graduates</th>
                                            <th>Employed</th>
                                            <th>Employment Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employmentTrends as $trend): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($trend['graduation_year']) ?></td>
                                                <td><?= htmlspecialchars($trend['total_graduates']) ?></td>
                                                <td><?= htmlspecialchars($trend['employed_count']) ?></td>
                                                <td class="employment-rate"><?= htmlspecialchars($trend['employment_rate']) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3><i class="fa-solid fa-school"></i> Alumni Analytics by Course and Year</h3>
                                    <button class="action-button" onclick="showNotification('Course/year analytics export coming soon', 'info')">Export Data</button>
                                </div>
                                <div class="chart-filter-row">
                                    <label for="alumni-course-year-search">Filter course/year:</label>
                                    <input id="alumni-course-year-search" type="search" placeholder="Type course or year" oninput="filterAlumniCourseYearTable()" />
                                </div>
                                <table class="trend-table" id="alumni-course-year-table">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Graduation Year</th>
                                            <th>Total Alumni</th>
                                            <th>Employed</th>
                                            <th>Employment Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alumniByCourseYear as $courseYear): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($courseYear['course']) ?></td>
                                                <td><?= htmlspecialchars($courseYear['graduation_year']) ?></td>
                                                <td><?= htmlspecialchars($courseYear['total_alumni']) ?></td>
                                                <td><?= htmlspecialchars($courseYear['employed_count']) ?></td>
                                                <td class="employment-rate"><?= htmlspecialchars($courseYear['employment_rate']) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section id="tab-progression" class="report-tab-panel" role="tabpanel" aria-labelledby="tab-progression-button">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3><i class="fa-solid fa-chart-line"></i> Progression Metrics</h3>
                                    <button class="action-button" onclick="exportProgressionData()">Export Data</button>
                                </div>
                                <div class="chart-placeholder">
                                    <i class="fa-solid fa-chart-line" style="font-size: 3rem; color: #dee2e6; margin-bottom: 16px;"></i>
                                    <p>Progression analytics and pass rate chart will be displayed here.</p>
                                </div>
                                <table class="trend-table">
                                    <thead>
                                        <tr>
                                            <th>Metric</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Students</td><td><?= htmlspecialchars($ssaaStats['progression']['total_students'] ?? 0) ?></td></tr>
                                        <tr><td>Passed</td><td><?= htmlspecialchars($ssaaStats['progression']['passed_count'] ?? 0) ?></td></tr>
                                        <tr><td>Failed</td><td><?= htmlspecialchars($ssaaStats['progression']['failed_count'] ?? 0) ?></td></tr>
                                        <tr><td>Pass Rate</td><td><?= htmlspecialchars($ssaaStats['progression']['pass_rate'] ?? 0) ?>%</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section id="tab-events" class="report-tab-panel" role="tabpanel" aria-labelledby="tab-events-button">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3><i class="fa-solid fa-calendar-check"></i> Graduation Event Analytics</h3>
                                    <button class="action-button" onclick="exportEventData()">Export Data</button>
                                </div>
                                <div class="chart-placeholder">
                                    <i class="fa-solid fa-calendar-check" style="font-size: 3rem; color: #dee2e6; margin-bottom: 16px;"></i>
                                    <p>Graduation event analytics will be displayed here.</p>
                                </div>
                                <table class="trend-table">
                                    <thead>
                                        <tr>
                                            <th>Metric</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Events</td><td><?= htmlspecialchars($ssaaStats['graduation']['total_events'] ?? 0) ?></td></tr>
                                        <tr><td>Completed</td><td><?= htmlspecialchars($ssaaStats['graduation']['completed_events'] ?? 0) ?></td></tr>
                                        <tr><td>Upcoming</td><td><?= htmlspecialchars($ssaaStats['graduation']['upcoming_events'] ?? 0) ?></td></tr>
                                        <tr><td>Cancelled</td><td><?= htmlspecialchars($ssaaStats['graduation']['cancelled_events'] ?? 0) ?></td></tr>
                                        <tr><td>Average Attendance</td><td><?= htmlspecialchars($ssaaStats['graduation']['avg_attendance'] ?? 0) ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section id="tab-custom" class="report-tab-panel" role="tabpanel" aria-labelledby="tab-custom-button">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3><i class="fa-solid fa-file-export"></i> Custom Report Builder</h3>
                                </div>
                                <div class="custom-report-form">
                                    <p>Select filters and report type to build a custom SSAA report.</p>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="custom-report-type">Report Type</label>
                                            <select id="custom-report-type">
                                                <option value="alumni">Alumni Outcomes</option>
                                                <option value="progression">Student Progression</option>
                                                <option value="events">Graduation Events</option>
                                                <option value="career">Employment Analytics</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="custom-report-year">Graduation Year</label>
                                            <input id="custom-report-year" type="text" placeholder="e.g. 2023" />
                                        </div>
                                        <div class="form-group">
                                            <label for="custom-report-status">Status / Industry</label>
                                            <input id="custom-report-status" type="text" placeholder="e.g. Employed, Technology" />
                                        </div>
                                        <div class="form-group form-actions">
                                            <button class="action-button" type="button" onclick="showNotification('Custom report generation is coming soon', 'info')">Generate Report</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                </div>
            </div>
        </main>
    </div>

    <div id="notification-container" class="notification-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom/dist/chartjs-plugin-zoom.min.js"></script>
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
        const employmentTrendData = <?= json_encode(array_map(function($trend) {
            return [
                'year' => $trend['graduation_year'],
                'total_graduates' => (int)$trend['total_graduates'],
                'employed_count' => (int)$trend['employed_count'],
                'employment_rate' => (float)$trend['employment_rate']
            ];
        }, $employmentTrends), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        let employmentTrendChart;

        function buildEmploymentTrendChart() {
            if (typeof ChartZoom !== 'undefined' && Chart && Chart.register) {
                Chart.register(ChartZoom);
            }

            const ctx = document.getElementById('employmentTrendChart').getContext('2d');
            const labels = employmentTrendData.map(item => item.year);
            const employed = employmentTrendData.map(item => item.employed_count);
            const rates = employmentTrendData.map(item => item.employment_rate);

            employmentTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Employment Rate (%)',
                            data: rates,
                            borderColor: '#1d4ed8',
                            backgroundColor: 'rgba(29, 78, 216, 0.2)',
                            yAxisID: 'y',
                            tension: 0.3,
                            pointRadius: 4,
                            fill: true
                        },
                        {
                            label: 'Total Graduates',
                            data: employed,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22, 163, 74, 0.2)',
                            yAxisID: 'y1',
                            tension: 0.3,
                            pointRadius: 3,
                            borderDash: [5, 5]
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    stacked: false,
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Employment Rate (%)'
                            },
                            ticks: {
                                callback: value => `${value}%`
                            },
                            beginAtZero: true,
                            max: 100
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Total Graduates'
                            },
                            grid: {
                                drawOnChartArea: false
                            },
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        zoom: {
                            pan: {
                                enabled: true,
                                mode: 'x'
                            },
                            zoom: {
                                wheel: {
                                    enabled: true
                                },
                                pinch: {
                                    enabled: true
                                },
                                mode: 'x'
                            }
                        }
                    }
                }
            });
        }

        function resetEmploymentTrendZoom() {
            if (employmentTrendChart) {
                employmentTrendChart.resetZoom();
            }
        }

        function filterCourseYearTable() {
            const search = document.getElementById('employment-trend-search').value.toLowerCase();
            const rows = document.querySelectorAll('#employment-trend-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        }

        function filterAlumniCourseYearTable() {
            const search = document.getElementById('alumni-course-year-search').value.toLowerCase();
            const rows = document.querySelectorAll('#alumni-course-year-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        }

        function activateReportTab(tabId) {
            const tabs = document.querySelectorAll('.report-tab');
            const panels = document.querySelectorAll('.report-tab-panel');

            if (!tabId) {
                return;
            }

            tabs.forEach(tab => {
                const isActive = tab.dataset.target === tabId;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', isActive.toString());
            });

            panels.forEach(panel => {
                panel.classList.toggle('active', panel.id === tabId);
            });
        }

        function initReportTabs() {
            const reportTabs = document.querySelectorAll('.report-tab');
            if (!reportTabs.length) {
                return;
            }

            reportTabs.forEach(tab => {
                tab.addEventListener('click', event => {
                    event.preventDefault();
                    activateReportTab(tab.dataset.target);
                });
            });
        }

        function initializeReportsPage() {
            buildEmploymentTrendChart();
            initReportTabs();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeReportsPage);
        } else {
            initializeReportsPage();
        }

        function showNotification(message, type) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        function exportChart() {
            showNotification('Employment trends export coming soon', 'info');
        }

        function exportIndustryData() {
            showNotification('Industry distribution export coming soon', 'info');
        }

        function exportProgressionData() {
            showNotification('Progression analytics export coming soon', 'info');
        }

        function exportEventData() {
            showNotification('Graduation event export coming soon', 'info');
        }

    </script>
</body>
</html>