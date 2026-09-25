<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = in_array($currentPage, ['student_promotion.php', 'graduation_events.php', 'alumni_management.php', 'reports_analytics.php'], true);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback & Ratings | PASS Support System</title>
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
        html { scroll-behavior: smooth; }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--surface, #fffaf0);
            border: 2px solid var(--soft-gold, #d4af37);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(105, 74, 16, 0.12);
            position: relative;
            overflow: hidden;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: var(--deep-maroon, #800000);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--deep-maroon, #800000), var(--soft-gold, #d4af37));
        }
        .stat-card h4 {
            margin: 0 0 8px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-soft, #5c4b45);
            line-height: 1.4;
        }
        .stat-card .stat-value {
            font-size: clamp(2.5rem, 3vw, 4rem);
            font-weight: 700;
            line-height: 1.1;
            color: var(--deep-maroon, #800000);
            margin: 12px 0 0;
        }
        .stat-card .stat-unit {
            margin-top: 8px;
            font-size: 18px;
            color: var(--text-soft, #5c4b45);
            line-height: 1.5;
        }
        .feedback-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .feedback-table thead { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        .feedback-table th { padding: 14px 16px; text-align: left; font-weight: 600; color: #475569; font-size: 13px; }
        .feedback-table td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; }
        .feedback-table tbody tr:hover { background: #f8fafc; }
        .star-rating { color: #f39c12; font-weight: 600; }
        .feedback-text { max-width: 300px; word-wrap: break-word; color: #475569; }
        .feedback-date { font-size: 12px; color: #94a3b8; white-space: nowrap; }
        .content-panel {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
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

        .tab-section { margin-bottom: 30px; }
        .tab-buttons { display: flex; gap: 12px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
        .tab-btn { background: none; border: none; padding: 12px 20px; color: #6b7280; font-weight: 500; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .tab-btn.active { color: #0f172a; border-bottom-color: #667eea; }
        .tab-btn:hover { color: #0f172a; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        @keyframes spin-refresh {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 767px) {
            .stats-container {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .stat-card {
                min-height: 150px !important;
                padding: 18px !important;
                border-radius: 14px !important;
            }

            .stat-card h4 {
                font-size: 12px !important;
                letter-spacing: 0.12em !important;
            }

            .stat-card .stat-value {
                font-size: clamp(2rem, 6vw, 3rem) !important;
            }

            .stat-card .stat-unit {
                font-size: 14px !important;
            }

            .tab-buttons {
                display: flex !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                gap: 8px !important;
                padding-bottom: 8px !important;
                white-space: nowrap !important;
                border-bottom: 1px solid #e2e8f0 !important;
            }

            .tab-buttons .tab-btn {
                flex: 0 0 auto !important;
                padding: 10px 14px !important;
                font-size: 12px !important;
                white-space: nowrap !important;
            }

            .tab-content {
                min-height: auto !important;
                overflow-x: auto !important;
            }

            .feedback-table,
            .feedback-table thead,
            .feedback-table tbody,
            .feedback-table tr,
            .feedback-table th,
            .feedback-table td {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .feedback-table thead {
                display: none !important;
            }

            .feedback-table tbody {
                display: block !important;
            }

            .feedback-table tr {
                margin-bottom: 16px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 12px !important;
                background: #ffffff !important;
                padding: 12px !important;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05) !important;
            }

            .feedback-table td {
                padding: 10px 0 !important;
                border: none !important;
                border-bottom: 1px solid #e2e8f0 !important;
                position: relative !important;
                text-align: left !important;
                word-break: break-word !important;
                overflow-wrap: anywhere !important;
                min-height: 42px !important;
            }

            .feedback-table td:last-child {
                border-bottom: none !important;
            }

            .feedback-table td::before {
                content: attr(data-label);
                display: block !important;
                font-weight: 700 !important;
                color: #334155 !important;
                margin-bottom: 6px !important;
            }

            .feedback-table td[style*="text-align: center"] {
                text-align: left !important;
            }

            .feedback-table {
                min-width: auto !important;
                font-size: 12px !important;
            }

            body.swipe-refresh-enabled {
                overscroll-behavior-y: none;
            }
        }
    </style>
</head>
<body class="swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; inset: 0; background: rgba(255, 255, 255, 0.92); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0; text-align: center;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <button type="button" class="nav-search-btn" aria-label="Search" data-tooltip="Search">
                    <i class="fa-solid fa-search"></i>
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
                <a href="feedback_and_ratings.php" class="active" data-tooltip="Feedback">
                    <span class="nav-icon"><i class="fa-solid fa-star"></i></span>
                    <span class="nav-text">Feedback</span>
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
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="admin_assignments.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">Manage admin assignments</span>
                        </div>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="admin_settings.php">System settings</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="page-overlay" id="pageOverlay"></div>
                <div class="content-panel">
                    <section class="dashboard-intro case-page-header">
                        <div>
                            <p class="eyebrow">Feedback</p>
                            <h1><i class="fa-solid fa-star" style="font-size:0.7em; margin-right:16px; color:#111;"></i>Feedback & Ratings</h1>
                            <p class="dashboard-subtitle">Review student feedback across all services</p>
                        </div>
                        <div class="case-header__art" aria-hidden="true">
                            <i class="fa-solid fa-file-lines art-file"></i>
                            <i class="fa-solid fa-user-tie art-card"></i>
                            <i class="fa-solid fa-clipboard-check art-chat"></i>
                            <i class="fa-solid fa-check art-check"></i>
                            <i class="fa-solid fa-location-dot art-pin"></i>
                            <i class="fa-solid fa-leaf art-dots"></i>
                        </div>
                    </section>

                    <div class="tab-section">
                        <div class="stats-container" style="margin-bottom: 20px;">
                            <div class="stat-card">
                                <h4>Total Feedback</h4>
                                <div class="stat-value" id="all-total-count">0</div>
                                <div class="stat-unit">across all services</div>
                            </div>
                            <div class="stat-card">
                                <h4>Average Rating</h4>
                                <div class="stat-value" id="all-avg-rating">0.0</div>
                                <div class="stat-unit">out of 5 stars</div>
                            </div>
                            <div class="stat-card">
                                <h4>This Week</h4>
                                <div class="stat-value" id="all-week-count">0</div>
                                <div class="stat-unit">new submissions</div>
                            </div>
                        </div>

                        <div class="tab-buttons">
                            <button class="tab-btn active" onclick="switchFeedbackTab('all')">
                                <i class="fa-solid fa-list"></i> All Feedback
                            </button>
                            <button class="tab-btn" onclick="switchFeedbackTab('guidance')">
                                <i class="fa-solid fa-head-side-virus"></i> Guidance
                            </button>
                            <button class="tab-btn" onclick="switchFeedbackTab('library')">
                                <i class="fa-solid fa-book"></i> Library
                            </button>
                            <button class="tab-btn" onclick="switchFeedbackTab('clinic')">
                                <i class="fa-solid fa-hospital"></i> Clinic
                            </button>
                            <button class="tab-btn" onclick="switchFeedbackTab('ssc')">
                                <i class="fa-solid fa-award"></i> SSC
                            </button>
                            <button class="tab-btn" onclick="switchFeedbackTab('scholarship')">
                                <i class="fa-solid fa-hand-holding-dollar"></i> Scholarship
                            </button>
                            <button class="tab-btn" onclick="switchFeedbackTab('ssaa')">
                                <i class="fa-solid fa-users"></i> SSAA
                            </button>
                        </div>

                        <!-- All Feedback Tab -->
                        <div id="all-feedback" class="tab-content active">
                            <table class="feedback-table">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Student Name</th>
                                        <th style="text-align: center; width: 100px;">Rating</th>
                                        <th>Feedback</th>
                                        <th style="width: 150px;">Date</th>
                                    </tr>
                                </thead>
                                <tbody id="all-feedback-body">
                                    <tr><td colspan="5" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Guidance Feedback Tab -->
                        <div id="guidance-feedback" class="tab-content">
                            <!-- Sub-tabs for Guidance Feedback Types -->
                            <div class="tab-buttons" style="margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 8px;">
                                <button class="tab-btn active" data-subtab="guidance-case" onclick="switchGuidanceSubTab(event, 'guidance-case')" style="padding: 12px 20px; color: #6b7280; font-weight: 500; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.3s;">
                                    <i class="fa-solid fa-comments"></i> Guidance Case Feedback
                                </button>
                                <button class="tab-btn" data-subtab="guidance-moral" onclick="switchGuidanceSubTab(event, 'guidance-moral')" style="padding: 12px 20px; color: #6b7280; font-weight: 500; cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.3s;">
                                    <i class="fa-solid fa-certificate"></i> Good Moral Feedback
                                </button>
                            </div>

                            <!-- Guidance Case Feedback Sub-tab -->
                            <div id="guidance-case" class="subtab-content active" style="display: block;">
                                <div class="stats-container">
                                    <div class="stat-card">
                                        <h4>Total Case Feedback</h4>
                                        <div class="stat-value" id="guidance-case-count">0</div>
                                        <div class="stat-unit">submissions</div>
                                    </div>
                                    <div class="stat-card">
                                        <h4>Average Rating</h4>
                                        <div class="stat-value" id="guidance-case-avg">0.0</div>
                                        <div class="stat-unit">out of 5 stars</div>
                                    </div>
                                    <div class="stat-card">
                                        <h4>This Week</h4>
                                        <div class="stat-value" id="guidance-case-week">0</div>
                                        <div class="stat-unit">new submissions</div>
                                    </div>
                                </div>

                                <table class="feedback-table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Case ID</th>
                                            <th>Concern Type</th>
                                            <th style="text-align: center; width: 100px;">Rating</th>
                                            <th>Feedback</th>
                                            <th style="width: 150px;">Date Submitted</th>
                                        </tr>
                                    </thead>
                                    <tbody id="guidance-case-feedback-body">
                                        <tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Good Moral Feedback Sub-tab -->
                            <div id="guidance-moral" class="subtab-content" style="display: none;">
                                <div class="stats-container">
                                    <div class="stat-card">
                                        <h4>Total Good Moral Feedback</h4>
                                        <div class="stat-value" id="guidance-moral-count">0</div>
                                        <div class="stat-unit">submissions</div>
                                    </div>
                                    <div class="stat-card">
                                        <h4>Average Rating</h4>
                                        <div class="stat-value" id="guidance-moral-avg">0.0</div>
                                        <div class="stat-unit">out of 5 stars</div>
                                    </div>
                                    <div class="stat-card">
                                        <h4>This Week</h4>
                                        <div class="stat-value" id="guidance-moral-week">0</div>
                                        <div class="stat-unit">new submissions</div>
                                    </div>
                                </div>

                                <table class="feedback-table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Email</th>
                                            <th>Course</th>
                                            <th style="text-align: center; width: 100px;">Rating</th>
                                            <th>Feedback</th>
                                            <th style="width: 150px;">Date Submitted</th>
                                        </tr>
                                    </thead>
                                    <tbody id="guidance-moral-feedback-body">
                                        <tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Library Feedback Tab -->
                        <div id="library-feedback" class="tab-content">
                            <div class="stats-container">
                                <div class="stat-card">
                                    <h4>Book Reviews</h4>
                                    <div class="stat-value" id="library-review-count">0</div>
                                    <div class="stat-unit">submissions</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Avg Book Rating</h4>
                                    <div class="stat-value" id="library-book-rating">0.0</div>
                                    <div class="stat-unit">out of 5 stars</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Avg Service Rating</h4>
                                    <div class="stat-value" id="library-service-rating">0.0</div>
                                    <div class="stat-unit">out of 5 stars</div>
                                </div>
                            </div>

                            <table class="feedback-table">
                                <thead>
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Reviewer</th>
                                        <th>Course</th>
                                        <th style="text-align: center; width: 80px;">Book Rating</th>
                                        <th style="text-align: center; width: 100px;">Service Rating</th>
                                        <th>Book Review</th>
                                        <th>Service Feedback</th>
                                        <th style="width: 120px;">Date</th>
                                    </tr>
                                </thead>
                                <tbody id="library-feedback-body">
                                    <tr><td colspan="8" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Clinic Feedback Tab -->
                        <div id="clinic-feedback" class="tab-content">
                            <div class="stats-container">
                                <div class="stat-card">
                                    <h4>Average Rating</h4>
                                    <div class="stat-value" id="clinic-avg-rating">0.0</div>
                                    <div class="stat-unit">out of 5 stars</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Total Submissions</h4>
                                    <div class="stat-value" id="clinic-total-count">0</div>
                                    <div class="stat-unit">feedback entries</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Excellent Ratings</h4>
                                    <div class="stat-value" id="clinic-excellent-count">0</div>
                                    <div class="stat-unit">4-5 stars</div>
                                </div>
                            </div>

                            <table class="feedback-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th style="text-align: center; width: 100px;">Rating</th>
                                        <th>Comments</th>
                                        <th style="width: 150px;">Date Submitted</th>
                                    </tr>
                                </thead>
                                <tbody id="clinic-feedback-body">
                                    <tr><td colspan="5" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- SSC Feedback Tab -->
                        <div id="ssc-feedback" class="tab-content">
                            <div class="stats-container">
                                <div class="stat-card">
                                    <h4>Average Rating</h4>
                                    <div class="stat-value" id="ssc-avg-rating">0.0</div>
                                    <div class="stat-unit">out of 5 stars</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Total Submissions</h4>
                                    <div class="stat-value" id="ssc-total-count">0</div>
                                    <div class="stat-unit">feedback entries</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Excellent Ratings</h4>
                                    <div class="stat-value" id="ssc-excellent-count">0</div>
                                    <div class="stat-unit">4-5 stars</div>
                                </div>
                            </div>

                            <table class="feedback-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th style="text-align: center; width: 100px;">Rating</th>
                                        <th>Feedback</th>
                                        <th style="width: 150px;">Date Submitted</th>
                                    </tr>
                                </thead>
                                <tbody id="ssc-feedback-body">
                                    <tr><td colspan="4" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Scholarship Feedback Tab -->
                        <div id="scholarship-feedback" class="tab-content">
                            <div class="stats-container">
                                <div class="stat-card">
                                    <h4>Process Rating Avg</h4>
                                    <div class="stat-value" id="scholarship-process-avg">0.0</div>
                                    <div class="stat-unit">out of 5 stars</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Support Rating Avg</h4>
                                    <div class="stat-value" id="scholarship-support-avg">0.0</div>
                                    <div class="stat-unit">out of 5 stars</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Total Submissions</h4>
                                    <div class="stat-value" id="scholarship-total-count">0</div>
                                    <div class="stat-unit">feedback entries</div>
                                </div>
                            </div>

                            <table class="feedback-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Validation ID</th>
                                        <th style="text-align: center; width: 100px;">Process Rating</th>
                                        <th style="text-align: center; width: 100px;">Support Rating</th>
                                        <th>Review</th>
                                        <th style="width: 150px;">Date</th>
                                    </tr>
                                </thead>
                                <tbody id="scholarship-feedback-body">
                                    <tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- SSAA Feedback Tab -->
                        <div id="ssaa-feedback" class="tab-content">
                            <div class="stats-container">
                                <div class="stat-card">
                                    <h4>Average Rating</h4>
                                    <div class="stat-value" id="ssaa-avg-rating">0.0</div>
                                    <div class="stat-unit">out of 5 stars</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Total Submissions</h4>
                                    <div class="stat-value" id="ssaa-total-count">0</div>
                                    <div class="stat-unit">feedback entries</div>
                                </div>
                                <div class="stat-card">
                                    <h4>Excellent Ratings</h4>
                                    <div class="stat-value" id="ssaa-excellent-count">0</div>
                                    <div class="stat-unit">4-5 stars</div>
                                </div>
                            </div>

                            <table class="feedback-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th style="text-align: center; width: 100px;">Rating</th>
                                        <th>Feedback</th>
                                        <th style="width: 150px;">Date Submitted</th>
                                    </tr>
                                </thead>
                                <tbody id="ssaa-feedback-body">
                                    <tr><td colspan="4" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="notification-container" class="notification-container"></div>

    <script>
        function switchFeedbackTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            
            // Remove active from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            const activeContent = document.getElementById(tabName + '-feedback');
            if (activeContent) {
                activeContent.classList.add('active');
            }
            
            // Mark button as active
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
            
            // Load data based on tab
            switch(tabName) {
                case 'all':
                    loadAllFeedback();
                    break;
                case 'guidance':
                    loadGuidanceFeedback();
                    break;
                case 'library':
                    loadLibraryFeedback();
                    break;
                case 'clinic':
                    loadClinicFeedback();
                    break;
                case 'ssc':
                    loadSscFeedback();
                    break;
                case 'scholarship':
                    loadScholarshipFeedback();
                    break;
                case 'ssaa':
                    loadSsaaFeedback();
                    break;
            }
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
            });
        }

        function getStars(rating) {
            return '★'.repeat(rating) + '☆'.repeat(5 - rating);
        }

        function createRatingStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<span style="color: ${i <= rating ? '#fbbf24' : '#d1d5db'}; font-size: 16px;">★</span>`;
            }
            return stars;
        }

        function htmlEscapeText(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function loadAllFeedback() {
            const tbody = document.getElementById('all-feedback-body');
            tbody.innerHTML = '<tr><td colspan="5" style="padding: 24px; text-align: center; color: #94a3b8;">Loading all feedback...</td></tr>';
            
            Promise.all([
                // Guidance Case Feedback
                fetch('../includes/api_guidance_feedback.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'get_all', case_id: 0 }),
                    credentials: 'same-origin'
                }).then(r => r.json()).catch(e => ({ success: false })),
                
                // Good Moral Feedback
                fetch('../includes/api_good_moral_feedback.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'get_all' }),
                    credentials: 'same-origin'
                }).then(r => r.json()).catch(e => ({ success: false })),
                
                // Library Feedback
                fetch('../includes/api_library_feedback.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'get_all' }),
                    credentials: 'same-origin'
                }).then(r => r.json()).catch(e => ({ success: false })),
                
                // Clinic Feedback
                fetch('../includes/api_clinic_feedback.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'get_all' }),
                    credentials: 'same-origin'
                }).then(r => r.json()).catch(e => ({ success: false })),
                
                // SSC Feedback
                fetch('ssc_api.php?action=get_all_feedback').then(r => r.json()).catch(e => ({ success: false })),
                
                // Scholarship Feedback
                fetch('../includes/api_scholarship_feedback.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'get_all', validation_id: 0 }),
                    credentials: 'same-origin'
                }).then(r => r.json()).catch(e => ({ success: false })),
                
                // SSAA Feedback
                fetch('../includes/api_ssaa_feedback.php?action=get_all').then(r => r.json()).catch(e => ({ success: false }))
            ]).then(([guidanceRes, moralRes, libraryRes, clinicRes, sscRes, scholarshipRes, ssaaRes]) => {
                let allData = [];
                
                // Guidance Case Feedback
                if (guidanceRes.success && guidanceRes.data) {
                    allData = allData.concat(guidanceRes.data.map(f => ({
                        service: 'Guidance Case',
                        full_name: f.full_name,
                        rating: f.service_rating,
                        feedback: f.feedback_text,
                        created_at: f.created_at
                    })));
                }
                
                // Good Moral Feedback
                if (moralRes.success && moralRes.data) {
                    allData = allData.concat(moralRes.data.map(f => ({
                        service: 'Good Moral',
                        full_name: f.full_name,
                        rating: f.service_rating,
                        feedback: f.feedback_text,
                        created_at: f.created_at
                    })));
                }
                
                // Library Feedback
                if (libraryRes.success && libraryRes.data) {
                    allData = allData.concat(libraryRes.data.map(f => ({
                        service: 'Library',
                        full_name: f.full_name,
                        rating: f.service_rating,
                        feedback: f.service_feedback || f.book_review,
                        created_at: f.created_at
                    })));
                }
                
                // Clinic Feedback
                if (clinicRes.success && clinicRes.data) {
                    allData = allData.concat(clinicRes.data.map(f => ({
                        service: 'Clinic',
                        full_name: f.full_name,
                        rating: f.rating,
                        feedback: f.comments,
                        created_at: f.created_at
                    })));
                }
                
                // SSC Feedback
                if (sscRes.success && sscRes.data) {
                    allData = allData.concat(sscRes.data.map(f => ({
                        service: 'SSC',
                        full_name: f.full_name,
                        rating: f.rating,
                        feedback: f.comments,
                        created_at: f.created_at
                    })));
                }
                
                // Scholarship Feedback
                if (scholarshipRes.success && scholarshipRes.data) {
                    allData = allData.concat(scholarshipRes.data.map(f => ({
                        service: 'Scholarship',
                        full_name: f.full_name,
                        rating: f.process_rating || f.support_rating,
                        feedback: f.review_text,
                        created_at: f.created_at
                    })));
                }
                
                // SSAA Feedback
                if (ssaaRes.success && ssaaRes.data) {
                    allData = allData.concat(ssaaRes.data.map(f => ({
                        service: 'SSAA',
                        full_name: f.full_name,
                        rating: f.rating,
                        feedback: f.comments,
                        created_at: f.created_at
                    })));
                }
                
                // Sort by date descending
                allData.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                
                if (allData.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="padding: 24px; text-align: center; color: #94a3b8;">No feedback yet</td></tr>';
                    document.getElementById('all-total-count').textContent = '0';
                    document.getElementById('all-avg-rating').textContent = '0.0';
                    document.getElementById('all-week-count').textContent = '0';
                } else {
                    let totalRating = 0;
                    let weekCount = 0;
                    const now = new Date();
                    const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                    
                    tbody.innerHTML = allData.map(f => {
                        const stars = createRatingStars(f.rating);
                        const fbDate = new Date(f.created_at);
                        if (fbDate > weekAgo) weekCount++;
                        if (f.rating > 0) totalRating += f.rating;
                        
                        return `
                            <tr>
                                <td data-label="Service"><strong>${htmlEscapeText(f.service)}</strong></td>
                                <td data-label="Student Name">${htmlEscapeText(f.full_name || 'Unknown')}</td>
                                <td data-label="Rating" style="text-align: center;">${stars}</td>
                                <td data-label="Feedback">${htmlEscapeText((f.feedback || '').substring(0, 60)) + (f.feedback && f.feedback.length > 60 ? '...' : '')}</td>
                                <td data-label="Date">${fbDate.toLocaleDateString()}</td>
                            </tr>
                        `;
                    }).join('');
                    
                    document.getElementById('all-total-count').textContent = allData.length;
                    document.getElementById('all-avg-rating').textContent = allData.filter(f => f.rating > 0).length > 0 
                        ? (totalRating / allData.filter(f => f.rating > 0).length).toFixed(1) 
                        : '0.0';
                    document.getElementById('all-week-count').textContent = weekCount;
                }
            }).catch(e => {
                console.error('Error loading all feedback:', e);
                tbody.innerHTML = '<tr><td colspan="5" style="padding: 24px; text-align: center; color: red;">Error loading feedback</td></tr>';
            });
        }

        function switchGuidanceSubTab(event, subTabName) {
            // Hide all guidance sub-tabs
            document.querySelectorAll('[id^="guidance-"]:not([data-subtab])').forEach(tab => {
                if (tab.classList.contains('subtab-content')) {
                    tab.style.display = 'none';
                    tab.classList.remove('active');
                }
            });
            
            // Remove active from all guidance sub-buttons
            document.querySelectorAll('[data-subtab]').forEach(btn => {
                btn.classList.remove('active');
                btn.style.borderBottomColor = 'transparent';
                btn.style.color = '#6b7280';
            });
            
            // Show selected sub-tab
            const subTab = document.getElementById(subTabName);
            if (subTab) {
                subTab.style.display = 'block';
                subTab.classList.add('active');
            }
            
            // Mark button as active
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
                event.currentTarget.style.borderBottomColor = '#667eea';
                event.currentTarget.style.color = '#0f172a';
            }
            
            // Load data
            if (subTabName === 'guidance-case') {
                loadGuidanceCaseFeedback();
            } else if (subTabName === 'guidance-moral') {
                loadGuidanceMoralFeedback();
            }
        }

        function loadGuidanceCaseFeedback() {
            const tbody = document.getElementById('guidance-case-feedback-body');
            tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>';
            
            fetch('../includes/api_guidance_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_all',
                    case_id: 0
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">No feedback submitted yet.</td></tr>';
                    document.getElementById('guidance-case-count').textContent = '0';
                    document.getElementById('guidance-case-avg').textContent = '0.0';
                    document.getElementById('guidance-case-week').textContent = '0';
                    return;
                }
                
                let rows = '';
                let totalRating = 0;
                let weekCount = 0;
                const now = new Date();
                const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                
                data.data.forEach(fb => {
                    const stars = createRatingStars(fb.service_rating);
                    const fbDate = new Date(fb.created_at);
                    if (fbDate > weekAgo) weekCount++;
                    totalRating += parseInt(fb.service_rating) || 0;
                    
                    rows += `
                        <tr>
                            <td data-label="Student Name">${htmlEscapeText(fb.full_name || 'Unknown')}</td>
                            <td data-label="Case ID"><strong>${fb.case_id}</strong></td>
                            <td data-label="Concern Type">${htmlEscapeText(fb.type_of_concern?.substring(0, 30) || 'N/A')}</td>
                            <td data-label="Rating" style="text-align: center;">${stars}</td>
                            <td data-label="Feedback">${htmlEscapeText(fb.feedback_text?.substring(0, 40) || '-')}</td>
                            <td data-label="Date Submitted">${fbDate.toLocaleDateString()}</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = rows;
                document.getElementById('guidance-case-count').textContent = data.data.length;
                document.getElementById('guidance-case-avg').textContent = (totalRating / data.data.length).toFixed(1);
                document.getElementById('guidance-case-week').textContent = weekCount;
            })
            .catch(e => {
                console.error('Error loading guidance case feedback:', e);
                tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: red;">Error loading feedback: ' + e.message + '</td></tr>';
            });
        }

        function loadGuidanceMoralFeedback() {
            const tbody = document.getElementById('guidance-moral-feedback-body');
            tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>';
            
            fetch('../includes/api_good_moral_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_all'
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">No feedback submitted yet.</td></tr>';
                    document.getElementById('guidance-moral-count').textContent = '0';
                    document.getElementById('guidance-moral-avg').textContent = '0.0';
                    document.getElementById('guidance-moral-week').textContent = '0';
                    return;
                }
                
                let rows = '';
                let totalRating = 0;
                let weekCount = 0;
                const now = new Date();
                const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                
                data.data.forEach(fb => {
                    const stars = createRatingStars(fb.service_rating);
                    const fbDate = new Date(fb.created_at);
                    if (fbDate > weekAgo) weekCount++;
                    totalRating += parseInt(fb.service_rating) || 0;
                    
                    rows += `
                        <tr>
                            <td data-label="Student Name">${htmlEscapeText(fb.full_name || 'Unknown')}</td>
                            <td data-label="Email">${htmlEscapeText(fb.email || '-')}</td>
                            <td data-label="Course">${htmlEscapeText(fb.course_year?.substring(0, 30) || 'N/A')}</td>
                            <td data-label="Rating" style="text-align: center;">${stars}</td>
                            <td data-label="Feedback">${htmlEscapeText(fb.feedback_text?.substring(0, 40) || '-')}</td>
                            <td data-label="Date Submitted">${fbDate.toLocaleDateString()}</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = rows;
                document.getElementById('guidance-moral-count').textContent = data.data.length;
                document.getElementById('guidance-moral-avg').textContent = (totalRating / data.data.length).toFixed(1);
                document.getElementById('guidance-moral-week').textContent = weekCount;
            })
            .catch(e => {
                console.error('Error loading good moral feedback:', e);
                tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: red;">Error loading feedback: ' + e.message + '</td></tr>';
            });
        }

        function loadGuidanceFeedback() {
            // Load the first guidance case sub-tab by default
            loadGuidanceCaseFeedback();
        }

        function loadLibraryFeedback() {
            const tbody = document.getElementById('library-feedback-body');
            tbody.innerHTML = '<tr><td colspan="8" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>';
            
            fetch('../includes/api_library_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_all'
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="padding: 24px; text-align: center; color: #94a3b8;">No feedback submitted yet.</td></tr>';
                    document.getElementById('library-review-count').textContent = '0';
                    document.getElementById('library-book-rating').textContent = '0.0';
                    document.getElementById('library-service-rating').textContent = '0.0';
                    return;
                }
                
                let rows = '';
                let totalBookRating = 0;
                let totalServiceRating = 0;
                let bookReviewCount = 0;
                let serviceReviewCount = 0;
                
                data.data.forEach(fb => {
                    const bookStars = createRatingStars(fb.book_rating);
                    const serviceStars = createRatingStars(fb.service_rating);
                    const fbDate = new Date(fb.created_at);
                    
                    if (fb.book_rating > 0) {
                        totalBookRating += parseInt(fb.book_rating) || 0;
                        bookReviewCount++;
                    }
                    if (fb.service_rating > 0) {
                        totalServiceRating += parseInt(fb.service_rating) || 0;
                        serviceReviewCount++;
                    }
                    
                    rows += `
                        <tr>
                            <td data-label="Book Title"><strong>${htmlEscapeText(fb.book_title || 'Unknown')}</strong></td>
                            <td data-label="Reviewer">${htmlEscapeText(fb.full_name || 'Unknown')}</td>
                            <td data-label="Course" style="font-size:12px;">${htmlEscapeText(fb.course_year || '—')}</td>
                            <td data-label="Book Rating" style="text-align:center;">${fb.book_rating > 0 ? bookStars : '—'}</td>
                            <td data-label="Service Rating" style="text-align:center;">${fb.service_rating > 0 ? serviceStars : '—'}</td>
                            <td data-label="Book Review" style="max-width:200px;word-wrap:break-word;font-size:12px;">${fb.book_review ? htmlEscapeText(fb.book_review.substring(0, 60)) + (fb.book_review.length > 60 ? '...' : '') : '—'}</td>
                            <td data-label="Service Feedback" style="max-width:200px;word-wrap:break-word;font-size:12px;">${fb.service_feedback ? htmlEscapeText(fb.service_feedback.substring(0, 60)) + (fb.service_feedback.length > 60 ? '...' : '') : '—'}</td>
                            <td data-label="Date">${fbDate.toLocaleDateString()}</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = rows;
                document.getElementById('library-review-count').textContent = data.data.length;
                document.getElementById('library-book-rating').textContent = bookReviewCount > 0 ? (totalBookRating / bookReviewCount).toFixed(1) : '0.0';
                document.getElementById('library-service-rating').textContent = serviceReviewCount > 0 ? (totalServiceRating / serviceReviewCount).toFixed(1) : '0.0';
            })
            .catch(e => {
                console.error('Error loading library feedback:', e);
                tbody.innerHTML = '<tr><td colspan="8" style="padding: 24px; text-align: center; color: red;">Error loading feedback: ' + e.message + '</td></tr>';
            });
        }

        function loadClinicFeedback() {
            const tbody = document.getElementById('clinic-feedback-body');
            tbody.innerHTML = '<tr><td colspan="5" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>';
            
            fetch('../includes/api_clinic_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_all'
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="padding: 24px; text-align: center; color: #94a3b8;">No feedback submitted yet.</td></tr>';
                    document.getElementById('clinic-avg-rating').textContent = '0.0';
                    document.getElementById('clinic-total-count').textContent = '0';
                    document.getElementById('clinic-excellent-count').textContent = '0';
                    return;
                }
                
                let rows = '';
                let totalRating = 0;
                let excellentCount = 0;
                const ratingTexts = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                
                data.data.forEach(fb => {
                    const stars = '★'.repeat(fb.rating) + '☆'.repeat(5 - fb.rating);
                    const ratingLabel = ratingTexts[fb.rating] || '';
                    const fbDate = new Date(fb.created_at);
                    const dateStr = fbDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    totalRating += parseInt(fb.rating) || 0;
                    if (fb.rating >= 4) excellentCount++;
                    
                    rows += `
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td data-label="Student Name" style="padding: 12px; color: #111827;">${htmlEscapeText(fb.full_name || 'Unknown')}</td>
                            <td data-label="Email" style="padding: 12px; color: #6b7280; font-size: 0.9em;">${htmlEscapeText(fb.email || '-')}</td>
                            <td data-label="Rating" style="padding: 12px; text-align: center; color: #fbbf24; font-size: 16px;" title="${ratingLabel}">${stars}</td>
                            <td data-label="Comments" style="padding: 12px; color: #374151; max-width: 300px; word-break: break-word;">${htmlEscapeText(fb.comments || '-')}</td>
                            <td data-label="Date Submitted" style="padding: 12px; color: #6b7280; font-size: 0.9em;">${dateStr}</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = rows;
                document.getElementById('clinic-total-count').textContent = data.data.length;
                document.getElementById('clinic-avg-rating').textContent = (totalRating / data.data.length).toFixed(1);
                document.getElementById('clinic-excellent-count').textContent = excellentCount;
            })
            .catch(e => {
                console.error('Error loading clinic feedback:', e);
                tbody.innerHTML = '<tr><td colspan="5" style="padding: 24px; text-align: center; color: red;">Error loading feedback: ' + e.message + '</td></tr>';
            });
        }

        function loadSscFeedback() {
            fetch('ssc_api.php?action=get_all_feedback')
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        const tbody = document.getElementById('ssc-feedback-body');
                        if (result.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="4" style="padding: 24px; text-align: center; color: #94a3b8;">No feedback yet</td></tr>';
                        } else {
                            tbody.innerHTML = result.data.map(feedback => `
                                <tr>
                                    <td>${feedback.full_name}</td>
                                    <td style="text-align: center;" class="star-rating">${getStars(feedback.rating)}</td>
                                    <td class="feedback-text">${feedback.comments}</td>
                                    <td class="feedback-date">${formatDate(feedback.created_at)}</td>
                                </tr>
                            `).join('');
                        }
                    }
                })
                .catch(error => console.error('Error loading SSC feedback:', error));

            fetch('ssc_api.php?action=get_feedback_stats')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        document.getElementById('ssc-avg-rating').textContent = (result.avg_rating || 0).toFixed(1);
                        document.getElementById('ssc-total-count').textContent = result.total_feedback || 0;
                        document.getElementById('ssc-excellent-count').textContent = '—';
                    }
                })
                .catch(error => console.error('Error loading SSC stats:', error));
        }

        function loadScholarshipFeedback() {
            const tbody = document.getElementById('scholarship-feedback-body');
            tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>';
            
            fetch('../includes/api_scholarship_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_all',
                    validation_id: 0
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">No feedback submitted yet.</td></tr>';
                    document.getElementById('scholarship-process-avg').textContent = '0.0';
                    document.getElementById('scholarship-support-avg').textContent = '0.0';
                    document.getElementById('scholarship-total-count').textContent = '0';
                    return;
                }
                
                let rows = '';
                let totalProcessRating = 0;
                let totalSupportRating = 0;
                let processCount = 0;
                let supportCount = 0;
                
                data.data.forEach(fb => {
                    const processStars = fb.process_rating > 0 ? '★'.repeat(fb.process_rating) + '☆'.repeat(5 - fb.process_rating) : '—';
                    const supportStars = fb.support_rating > 0 ? '★'.repeat(fb.support_rating) + '☆'.repeat(5 - fb.support_rating) : '—';
                    const fbDate = new Date(fb.created_at);
                    
                    if (fb.process_rating > 0) {
                        totalProcessRating += parseInt(fb.process_rating) || 0;
                        processCount++;
                    }
                    if (fb.support_rating > 0) {
                        totalSupportRating += parseInt(fb.support_rating) || 0;
                        supportCount++;
                    }
                    
                    rows += `
                        <tr>
                            <td><strong>${htmlEscapeText(fb.full_name || 'Unknown')}</strong></td>
                            <td>#${fb.validation_id}</td>
                            <td style="text-align:center;"><strong style="color: #3b82f6;">${fb.process_rating > 0 ? processStars : '—'}</strong></td>
                            <td style="text-align:center;"><strong style="color: #10b981;">${fb.support_rating > 0 ? supportStars : '—'}</strong></td>
                            <td style="max-width: 200px; word-wrap: break-word; font-size: 12px;">${fb.review_text ? htmlEscapeText(fb.review_text.substring(0, 80)) + (fb.review_text.length > 80 ? '...' : '') : '—'}</td>
                            <td>${fbDate.toLocaleDateString()}</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = rows;
                document.getElementById('scholarship-total-count').textContent = data.data.length;
                document.getElementById('scholarship-process-avg').textContent = processCount > 0 ? (totalProcessRating / processCount).toFixed(1) : '0.0';
                document.getElementById('scholarship-support-avg').textContent = supportCount > 0 ? (totalSupportRating / supportCount).toFixed(1) : '0.0';
            })
            .catch(e => {
                console.error('Error loading scholarship feedback:', e);
                tbody.innerHTML = '<tr><td colspan="6" style="padding: 24px; text-align: center; color: red;">Error loading feedback: ' + e.message + '</td></tr>';
            });
        }

        function loadSsaaFeedback() {
            fetch('../includes/api_ssaa_feedback.php?action=get_all')
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        const tbody = document.getElementById('ssaa-feedback-body');
                        if (result.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="4" style="padding: 24px; text-align: center; color: #94a3b8;">No feedback yet</td></tr>';
                        } else {
                            tbody.innerHTML = result.data.map(feedback => `
                                <tr>
                                    <td>${feedback.full_name}</td>
                                    <td style="text-align: center;" class="star-rating">${getStars(feedback.rating)}</td>
                                    <td class="feedback-text">${feedback.comments}</td>
                                    <td class="feedback-date">${formatDate(feedback.created_at)}</td>
                                </tr>
                            `).join('');
                        }
                    }
                })
                .catch(error => console.error('Error loading SSAA feedback:', error));

            fetch('../includes/api_ssaa_feedback.php?action=get_stats')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        document.getElementById('ssaa-avg-rating').textContent = (result.avg_rating || 0).toFixed(1);
                        document.getElementById('ssaa-total-count').textContent = result.total_feedback || 0;
                        document.getElementById('ssaa-excellent-count').textContent = '—';
                    }
                })
                .catch(error => console.error('Error loading SSAA stats:', error));
        }

        // Load on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadAllFeedback();
        });

        // Auto-refresh every 10 seconds
        setInterval(() => {
            const activeTab = document.querySelector('.tab-content.active');
            if (activeTab) {
                const tabId = activeTab.id.replace('-feedback', '');
                switchFeedbackTab(tabId);
            }
        }, 10000);
    </script>

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
