<?php
require_once __DIR__ . '/../includes/session.php';
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
$isSscService = $service === 'ssc';
$isSscScholarshipService = $service === 'ssc/scholarship' || ($service === 'ssc' && $isHeadSscScholarship);
$sscScholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'ssc';
$scholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'scholarship';
$isGuidanceService = $service === 'guidance';

if (!$isGuidanceService && ($user['role'] !== 'teacher' || $user['head_service'] !== 'ssc_scholarship')) {
    header('Location: /auth/teacher_login.php');
    exit;
}
require_once __DIR__ . '/../AI CHAT BOT/chat_widget.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SSC / Scholarship Head Dashboard | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="manifest" href="../PWA/manifest-teacher.json">
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
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
        /* SSC Management Styles */
        .management-sections {
            margin-top: 40px;
        }

        .management-panel {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 45px rgba(82,45,9,0.08);
            margin-bottom: 30px;
            overflow: hidden;
            border: 1px solid rgba(214, 168, 74, 0.18);
        }

        .panel-header {
            background: linear-gradient(135deg, rgba(255, 249, 238, 0.95), rgba(255, 255, 255, 0.95));
            padding: 24px 28px;
            border-bottom: 1px solid rgba(214, 168, 74, 0.22);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header h3 {
            margin: 0;
            color: #7a4b1e;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .panel-header h3 i {
            color: #b9770e;
            font-size: 20px;
        }

        .panel-content {
            padding: 24px;
        }

        .items-list {
            display: grid;
            gap: 16px;
        }

        .item-card {
            background: white;
            border: 1px solid rgba(214, 168, 74, 0.18);
            border-radius: 18px;
            padding: 22px;
            transition: all 0.3s ease;
        }

        .item-card:hover {
            box-shadow: 0 12px 25px rgba(82,45,9,0.08);
            border-color: rgba(214, 168, 74, 0.4);
            transform: translateY(-2px);
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .item-header h4 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .item-actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .btn-sm:hover {
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .item-details {
            margin-bottom: 12px;
        }

        .item-details p {
            margin: 4px 0;
            color: #666;
            font-size: 0.9rem;
        }

        .item-description {
            color: #555;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-upcoming { background: #3498db; color: white; }
        .status-ongoing { background: #f39c12; color: white; }
        .status-completed { background: #27ae60; color: white; }
        .status-cancelled { background: #e74c3c; color: white; }
        .status-active { background: #27ae60; color: white; }
        .status-withdrawn { background: #95a5a6; color: white; }
        .status-elected { background: #9b59b6; color: white; }
        .status-planning { background: #f39c12; color: white; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 16px;
        }

        .empty-state h4 {
            margin: 0 0 8px 0;
            color: #2c3e50;
        }

        .empty-state p {
            margin: 0;
            font-size: 0.9rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: #f8f9fa;
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .modal-actions {
            padding: 20px 24px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #9b6a0b;
            transform: translateY(-1px);
        }

        .btn-sm.btn-primary {
            background: #b9770e;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-sm.btn-primary:hover {
            background: #9b6a0b;
        }

        .btn-sm.btn-secondary {
            background: #f3e2b4;
            color: #7a4b1e;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-sm.btn-secondary:hover {
            background: #e7d1a0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
            }

            .panel-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .item-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .item-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .overview-metrics-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        @media (max-width: 1100px) {
            .overview-metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .overview-metrics-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            border: 1px solid rgba(214, 168, 74, 0.18);
            border-radius: 18px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            box-shadow: 0 12px 24px rgba(82,45,9,0.08);
            border-color: rgba(214, 168, 74, 0.4);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #b9770e, #e1c56a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .stat-content h4 {
            margin: 0 0 4px 0;
            color: #5d4323;
            font-size: 1rem;
            font-weight: 600;
        }

        .stat-number {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: #b9770e;
            line-height: 1;
        }

        .stat-label {
            margin: 4px 0 0 0;
            font-size: 0.85rem;
            color: #666;
        }

        /* Enhanced Item Cards */
        .item-card {
            cursor: pointer;
        }

        .item-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            border-color: #3498db;
            transform: translateY(-2px);
        }

        .item-header h4 {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .item-header h4 i {
            color: #b9770e;
            font-size: 16px;
        }

        .item-description {
            color: #555;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-top: 8px;
        }

        /* Panel Improvements */
        .panel-header h3 {
            color: #2c3e50;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-header h3 i {
            color: #3498db;
            font-size: 18px;
        }

        .panel-content {
            padding: 24px;
        }

        .management-sections {
            display: grid;
            gap: 24px;
        }
        /* Mobile adjustments for 720px -> 320px */
        @media (max-width: 720px) {
            /* Intro: stack left and right panels */
            .dashboard-intro {
                display: flex;
                flex-direction: column;
                gap: 14px;
                padding: 0 8px;
            }

            .dashboard-intro-left {
                order: 1;
            }

            .dashboard-intro-right {
                order: 2;
            }

            .dashboard-intro-left h1 {
                font-size: 1.25rem;
                line-height: 1.2;
            }

            .dashboard-detail-panel {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .dashboard-detail-panel .detail-row {
                display: flex;
                gap: 8px;
                align-items: center;
                padding: 8px;
                border-radius: 10px;
                background: #fff;
                border: 1px solid rgba(0,0,0,0.04);
            }

            .dashboard-detail-panel .detail-icon {
                width: 36px;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                border-radius: 8px;
                background: linear-gradient(135deg,#b9770e,#e1c56a);
                color: #fff;
            }

            /* Metrics: make single column for readability */
            .overview-metrics-grid {
                grid-template-columns: 1fr !important;
                gap: 10px;
            }

            .metric-card {
                padding: 14px;
            }

            .metric-card .metric-icon {
                width: 40px;
                height: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
            }

            /* Activity list: stack items and make actions full-width */
            .activity-list {
                display: grid;
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .activity-item {
                display: flex;
                gap: 12px;
                align-items: flex-start;
                padding: 12px;
                border-radius: 12px;
                background: #fff;
                border: 1px solid rgba(0,0,0,0.04);
            }

            .activity-item .activity-icon {
                width: 46px;
                height: 46px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                background: linear-gradient(135deg,#b9770e,#e1c56a);
                color: #fff;
                flex-shrink: 0;
            }

            .activity-item .activity-content {
                flex: 1 1 auto;
            }

            .activity-item button.btn-sm {
                width: 100%;
                margin-top: 8px;
                align-self: stretch;
            }

            .panel-content, .item-card, .item-card .item-description {
                padding-left: 8px;
                padding-right: 8px;
            }
        }
    </style>
    <style>
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }
    </style>
</head>
<body class="swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>SSC / Scholarship Head</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar" <?= $isSscService ? 'onclick="toggleGuidanceSidebar()"' : '' ?>>
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <?php if ($isGuidanceService): ?>
                    <a href="guidance_home.php?service=guidance" data-tooltip="Dashboard">
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
                            <a href="guidance_home.php?service=guidance" data-tooltip="Guidance">
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
                            <a href="case_management.php?service=guidance" data-tooltip="Case Management">
                                <span class="nav-icon"><i class="fa-solid fa-file-circle-plus"></i></span>
                                <span class="nav-text">Case Management</span>
                            </a>
                            <a href="good_moral.php?service=guidance" data-tooltip="Good Moral">
                                <span class="nav-icon"><i class="fa-solid fa-certificate"></i></span>
                                <span class="nav-text">Good Moral</span>
                            </a>
                            <a href="reports.php?service=guidance" data-tooltip="Reports">
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
                <?php elseif ($isSscService || $isSscScholarshipService): ?>
                    <a href="ssc_head_home.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" class="active" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" data-tooltip="Announcements">
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
                            <a href="ssc/ssc_dashboard.php?service=ssc/scholarship" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=ssc/scholarship" data-tooltip="Scholarship">
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
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=ssc/scholarship" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=ssc/scholarship" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php else: ?>
                    <a href="ssc_head_home.php" class="active" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
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
                            <a href="ssc/ssc_dashboard.php?service=ssc/scholarship" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=ssc/scholarship" data-tooltip="Scholarship">
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
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
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
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
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
                    <a href="profile.php" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php endif; ?>
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
                    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>SSC / Scholarship Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">SSC / Scholarship Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Download App" data-tooltip="Download App" id="installAppBtn"><i class="fa-solid fa-download"></i></button>

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
                        <div class="dashboard-intro-left">
                            <p class="eyebrow"><i class="fa-solid fa-star sparkle-icon"></i> Welcome back</p>
                            <h1>HELLO, <span class="user-name-highlight"><?= htmlspecialchars($user['full_name']) ?></span></h1>
                            <p class="dashboard-subtitle">Manage SSC activities and scholarship support from one centralized dashboard.</p>
                            <p class="dashboard-subtitle">Use the tools below to oversee events, candidates, and scholarship workflows effortlessly.</p>
                        </div>
                        <aside class="dashboard-intro-right">
                            <div class="dashboard-detail-panel">
                                <div class="detail-row role-row">
                                    <div class="detail-icon"><i class="fa-solid fa-award"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Role</div>
                                        <div class="detail-bottom">SSC / Scholarship Head</div>
                                    </div>
                                </div>
                                <div class="detail-row course-row">
                                    <div class="detail-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Services</div>
                                        <div class="detail-bottom">SSC and Scholarship</div>
                                    </div>
                                </div>
                                <div class="detail-row year-row">
                                    <div class="detail-icon"><i class="fa-solid fa-circle-check"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Status</div>
                                        <div class="detail-bottom">Active</div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </section>

                    <!-- Quick Overview -->
                    <section class="dashboard-section">
                        <h2>Quick Overview</h2>
                        <div class="metrics-grid overview-metrics-grid">
                            <div class="metric-card">
                                <div class="metric-card-header">
                                    <div class="metric-icon"><i class="fa-solid fa-calendar-days"></i></div>
                                </div>
                                <div class="metric-card-body">
                                    <h3>--</h3>
                                    <p class="metric-card-label">SSC Events</p>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-card-header">
                                    <div class="metric-icon"><i class="fa-solid fa-bullhorn"></i></div>
                                </div>
                                <div class="metric-card-body">
                                    <h3>--</h3>
                                    <p class="metric-card-label">Announcements</p>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-card-header">
                                    <div class="metric-icon"><i class="fa-solid fa-file-circle-check"></i></div>
                                </div>
                                <div class="metric-card-body">
                                    <h3>--</h3>
                                    <p class="metric-card-label">Applications</p>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-card-header">
                                    <div class="metric-icon"><i class="fa-solid fa-users"></i></div>
                                </div>
                                <div class="metric-card-body">
                                    <h3>--</h3>
                                    <p class="metric-card-label">Candidates</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="dashboard-grid">
                        <section class="dashboard-section activity-card">
                            <div class="activity-header">
                                <div>
                                    <h2>SSC Management</h2>
                                    <p class="activity-subtitle">Manage SSC initiatives and leadership operations.</p>
                                </div>
                            </div>
                            <div class="activity-list">
                                <div class="activity-item" onclick="window.location.href='ssc_manage_events.php'">
                                    <div class="activity-icon"><i class="fa-solid fa-calendar-days"></i></div>
                                    <div class="activity-content">
                                        <h4>Event Management</h4>
                                        <p>Plan and organize SSC events, schedules, and campus activities.</p>
                                    </div>
                                    <button class="btn-sm btn-primary">Manage</button>
                                </div>
                                <div class="activity-item" onclick="window.location.href='ssc_manage_candidates.php'">
                                    <div class="activity-icon"><i class="fa-solid fa-user-group"></i></div>
                                    <div class="activity-content">
                                        <h4>Candidate Management</h4>
                                        <p>Oversee leadership candidates and election processes.</p>
                                    </div>
                                    <button class="btn-sm btn-primary">Manage</button>
                                </div>
                                <div class="activity-item" onclick="window.location.href='ssc_manage_events.php#proposals-section'">
                                    <div class="activity-icon"><i class="fa-solid fa-chart-line"></i></div>
                                    <div class="activity-content">
                                        <h4>Project Proposals</h4>
                                        <p>Review and approve department project proposals.</p>
                                    </div>
                                    <button class="btn-sm btn-primary">Review</button>
                                </div>
                            </div>
                        </section>

                        <section class="dashboard-section activity-card">
                            <div class="activity-header">
                                <div>
                                    <h2>Scholarship Management</h2>
                                    <p class="activity-subtitle">Manage scholarship announcements and applications.</p>
                                </div>
                            </div>
                            <div class="activity-list">
                                <div class="activity-item" onclick="window.location.href='scholarship_create_announcement.php'">
                                    <div class="activity-icon"><i class="fa-solid fa-bullhorn"></i></div>
                                    <div class="activity-content">
                                        <h4>Scholarship Announcements</h4>
                                        <p>Create and share scholarship updates with students.</p>
                                    </div>
                                    <button class="btn-sm btn-primary">Manage</button>
                                </div>
                                <div class="activity-item" onclick="window.location.href='scholarship_application_tracker.php'">
                                    <div class="activity-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                                    <div class="activity-content">
                                        <h4>Application Tracker</h4>
                                        <p>Monitor student application progress and statuses.</p>
                                    </div>
                                    <button class="btn-sm btn-primary">Track</button>
                                </div>
                            </div>
                        </section>
                </div>
            </div>
        </main>
    </div>
    <script>
        // Mobile Menu Toggle (small screens)
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sideNav = document.querySelector('.side-nav');
            const pageOverlay = document.getElementById('pageOverlay');

            if (mobileMenuToggle && sideNav && pageOverlay) {
                mobileMenuToggle.addEventListener('click', function() {
                    sideNav.classList.toggle('mobile-open');
                    pageOverlay.classList.toggle('active');
                });

                pageOverlay.addEventListener('click', function() {
                    sideNav.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });

                const navLinks = sideNav.querySelectorAll('a, .nav-toggle');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (!this.classList.contains('nav-toggle')) {
                            sideNav.classList.remove('mobile-open');
                            pageOverlay.classList.remove('active');
                        }
                    });
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sideNav.classList.contains('mobile-open')) {
                        sideNav.classList.remove('mobile-open');
                        pageOverlay.classList.remove('active');
                    }
                });
            }
        });

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
                    console.log(`User response to install app: ${outcome}`);
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

    </script>
    <script src="../assets/js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function isScreenMobile() {
                return window.innerWidth >= 320 && window.innerWidth <= 768;
            }
            let touchStartY = 0;
            const SWIPE_THRESHOLD = 100;
            const SWIPE_START_LIMIT = 100;
            document.addEventListener('touchstart', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.touches.length !== 1) return;
                touchStartY = e.touches[0].clientY;
            }, { passive: true });
            document.addEventListener('touchend', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.changedTouches.length !== 1) return;
                const touchEndY = e.changedTouches[0].clientY;
                const distance = touchEndY - touchStartY;
                if (touchStartY <= SWIPE_START_LIMIT && distance >= SWIPE_THRESHOLD) {
                    const spinner = document.getElementById('swipe-refresh-spinner');
                    if (spinner) {
                        spinner.style.display = 'flex';
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                }
            }, { passive: true });
        });
    </script>
</body>
</html>
