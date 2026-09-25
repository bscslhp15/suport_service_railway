<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'ssc_scholarship') {
    header('Location: /auth/teacher_login.php');
    exit;
}
require_once __DIR__ . '/../AI CHAT BOT/chat_widget.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage SSC Candidates | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/scholar-header.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .management-page {
            display: block;
            margin-top: 20px;
        }

        .management-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 1400px; /* widen the main content area */
            margin: 0 auto; /* center the main content */
            width: 100%;
        }

        .management-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 16px;
        }

        .management-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.5rem;
        }

        /* Horizontal Tabs */
        .tabs-container {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 24px;
            overflow-x: auto;
        }

        .tab-link {
            padding: 14px 20px;
            border: none;
            background: none;
            cursor: pointer;
            color: #666;
            font-size: 0.95rem;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            text-decoration: none;
        }

        .tab-link:hover {
            color: #3498db;
            background: #f8f9fa;
        }

        .tab-link.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }

        .tab-badge {
            background: #e9ecef;
            color: #555;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .tab-link.active .tab-badge {
            background: #3498db;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .btn-add {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            align-self: flex-start;
        }

        .btn-add:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-add[style*="background: #f39c12"]:hover {
            background: #e67e22 !important;
        }

        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* wider cards */
            gap: 20px;
            align-items: start;
        }

        .candidate-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* keep buttons at bottom */
            min-height: 420px; /* uniform card height */
        }

        .candidate-item:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-color: #3498db;
            transform: translateY(-2px);
        }

        .candidate-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 16px;
            object-fit: cover;
            border: 3px solid #f0f0f0;
        }

        .candidate-photo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 16px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bdc3c7;
            font-size: 2rem;
            border: 3px solid #e9ecef;
        }

        .candidate-name {
            margin: 0 0 4px 0;
            color: #2c3e50;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .candidate-position {
            color: #3498db;
            font-weight: 600;
            margin: 0 0 8px 0;
            font-size: 0.95rem;
        }

        .candidate-course {
            color: #666;
            font-size: 0.85rem;
            margin: 0 0 16px 0;
        }

        .candidate-platform {
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
            margin: 0 0 16px 0;
            max-height: 90px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Notification System */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        }

        .notification {
            background: #4caf50;
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
        }

        .notification.error {
            background: #f44336;
        }

        .notification.success {
            background: #4caf50;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .candidate-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-withdrawn { background: #f8f9fa; color: #666; }
        .status-elected { background: #cfe2ff; color: #084298; }

        .candidate-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        /* Officer and Advicer hover colors */
        .candidate-item.status-elected:hover { border-color: #D4AF37; box-shadow: 0 12px 28px rgba(212,175,55,0.08); }
        .candidate-item.status-advicer { border-color: #e9e6e2; }
        .candidate-item.status-advicer:hover { border-color: #800000; box-shadow: 0 12px 28px rgba(128,0,0,0.08); }

        .btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            margin: 0 0 8px 0;
            color: #2c3e50;
        }

        .empty-state p {
            margin: 0;
            color: #666;
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

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
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
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        /* Search & Filter Section */
        .search-filter-section {
            margin: 20px 0;
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 200px;
        }

        .search-input-group i {
            color: #3498db;
            font-size: 1rem;
        }

        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: white;
            min-width: 180px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .results-count {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
            white-space: nowrap;
        }

        .results-count i {
            color: #3498db;
        }

        @media (max-width: 1024px) {
            .candidates-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .management-page {
                flex-direction: column;
            }

            .management-sidebar {
                width: 100%;
                position: relative;
                top: 0;
            }

            .management-header {
                flex-wrap: wrap;
                gap: 12px;
            }

            .management-header h2 {
                flex: 1 1 100%;
                font-size: 1.3rem;
            }

            .management-header > div {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                justify-content: flex-start;
            }

            .management-header .btn-add {
                flex: 1 1 100%;
                max-width: none;
                justify-content: center;
            }

            .candidates-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .search-filter-section {
                flex-direction: column;
            }

            .search-input-group {
                width: 100%;
            }

            .filter-group {
                width: 100%;
            }

            .filter-select {
                width: 100%;
            }

            .results-count {
                margin-left: 0;
            }
        }

        /* Inventory Tab Navigation Styles */
        .inventory-tab-nav {
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
            color: black;
            border-radius: 8px;
        }
        .clinic-tab i {
            color: #800000;
            margin-right: 0.5rem;
        }
        .clinic-tab.active {
            background: #800000;
            color: white;
        }
        .clinic-tab.active i {
            color: #D4AF37;
        }
        .clinic-tab:hover:not(.active) {
            background: #800000;
            color: white;
        }
        .clinic-tab:hover:not(.active) i {
            color: #D4AF37;
        }
        .inventory-tab-content {
            display: none;
        }
        .inventory-tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        /* Candidate tabs: enable horizontal swipe and scroll-snap on small screens */
        @media (max-width: 720px) {
            .inventory-tab-nav {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scroll-snap-type: x mandatory;
                white-space: nowrap;
            }

            .inventory-tab-nav .clinic-tab {
                scroll-snap-align: center;
                min-width: 120px;
                flex: 0 0 auto;
            }

            .inventory-tab-nav::-webkit-scrollbar {
                height: 6px;
            }

            .inventory-tab-nav::-webkit-scrollbar-thumb {
                background: rgba(0,0,0,0.12);
                border-radius: 6px;
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
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
               
            </div>
            <div class="nav-section">
                <a href="ssc_head_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                    <span class="nav-text">Dashboard</span>
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
                        <a href="ssc_manage_candidates.php" class="active" data-tooltip="Candidates">
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
                <a href="profile.php?service=ssc/scholarship" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="about.php?service=ssc/scholarship" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
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
                <section class="dashboard-intro scholar-page-header">
                    <div class="scholar-header__content">
                        <span class="eyebrow">SSC CANDIDATES</span>
                        <h1>Manage Election Candidates and Officers</h1>
                        <p class="scholar-header__subtitle">Track current officers, candidates, and manage elections in one clean, organized view.</p>
                    </div>
                    <div class="scholar-header__art" aria-hidden="true"><i class="fa-solid fa-message chat-left"></i><i class="fa-solid fa-comment chat-top"></i><i class="fa-solid fa-address-card profile-card"></i><i class="fa-solid fa-heart heart-check"></i><i class="fa-solid fa-leaf scholar-leaf"></i><i class="fa-solid fa-circle scholar-dot"></i></div>
                </section>
                    <div class="management-page">
                        <!-- Main Content -->
                        <div class="management-content">
                            <div class="management-header">
                                <h2>SSC Candidates Manager</h2>
                                <div style="display: flex; gap: 12px;">
                                    <button class="btn-add" onclick="showCandidateModal(null, 'officer')" style="background: #f39c12;">
                                        <i class="fa-solid fa-crown"></i> Add Officer
                                    </button>
                                    <button class="btn-add" onclick="showAdvicerModal()" style="background: #6b7280;">
                                        <i class="fa-solid fa-chalkboard-user"></i> Add SSC Advicer
                                    </button>
                                    <button class="btn-add" onclick="showCandidateModal(null, 'candidate')">
                                        <i class="fa-solid fa-plus"></i> Add Candidate
                                    </button>
                                </div>
                            </div>

                            <!-- Candidate Tabs -->
                            <div class="inventory-tab-nav clinic-tabs">
                                <button type="button" class="clinic-tab active" onclick="switchTab('officers', event)">
                                    <i class="fa-solid fa-crown"></i> Current Officers
                                    <span class="tab-badge" id="badge-officers">1</span>
                                </button>
                                <button type="button" class="clinic-tab" onclick="switchTab('previous-officer', event)">
                                    <i class="fa-solid fa-list"></i> Previous Officer
                                    <span class="tab-badge" id="badge-previous-officer">3</span>
                                </button>
                                <button type="button" class="clinic-tab" onclick="switchTab('candidate', event)">
                                    <i class="fa-solid fa-check-circle"></i> Candidate
                                    <span class="tab-badge" id="badge-candidate">1</span>
                                </button>
                                <button type="button" class="clinic-tab" onclick="switchTab('withdrawn', event)">
                                    <i class="fa-solid fa-times-circle"></i> Withdrawn
                                    <span class="tab-badge" id="badge-withdrawn">1</span>
                                </button>
                                <button type="button" class="clinic-tab" onclick="switchTab('election-settings', event)">
                                    <i class="fa-solid fa-cog"></i> Election Settings
                                </button>
                            </div>

                            <!-- Search & Filter Section -->
                            <div class="search-filter-section" id="searchFilterSection">
                                <div class="search-input-group">
                                    <i class="fa-solid fa-search"></i>
                                    <input type="text" id="candidate-search" placeholder="Search by name or position..." class="search-input">
                                </div>
                                <div class="filter-group">
                                    <select id="course-filter" class="filter-select">
                                        <option value="">All Courses</option>
                                        <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                                        <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                                        <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                                        <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                                        <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                                        <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                                        <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                                    </select>
                                </div>
                                <div class="results-count">
                                    <i class="fa-solid fa-list"></i>
                                    <span id="results-count">Showing all candidates</span>
                                </div>
                            </div>

                            <!-- Officers Tab -->
                            <div id="officers" class="inventory-tab-content active">
                                <div id="candidates-officers" class="candidates-grid">
                                    <!-- Current officers will be loaded here -->
                                </div>
                            </div>

                            <!-- Candidates List -->
                            <div id="previous-officer" class="inventory-tab-content">
                                <div style="display: flex; gap: 12px; margin-bottom: 20px; align-items: center;">
                                    <label for="officerYearFilter" style="font-weight: 600; white-space: nowrap;">Filter by Year:</label>
                                    <select id="officerYearFilter" class="filter-select" onchange="filterCandidates()" style="max-width: 200px;">
                                        <option value="">All Years</option>
                                    </select>
                                </div>
                                <div id="candidates-all" class="candidates-grid">
                                    <!-- Previous officers will be loaded here -->
                                </div>
                            </div>

                            <div id="candidate" class="inventory-tab-content">
                                <div id="candidates-candidate" class="candidates-grid">
                                    <!-- Candidates will be loaded here -->
                                </div>
                            </div>

                            <div id="withdrawn" class="inventory-tab-content">
                                <div id="candidates-withdrawn" class="candidates-grid">
                                    <!-- Withdrawn candidates will be loaded here -->
                                </div>
                            </div>

                            <!-- Election Settings Tab -->
                            <div id="election-settings" class="inventory-tab-content">
                                <div style="background: white; border-radius: 12px; padding: 24px;">
                                    <h3 style="margin-top: 0; color: #2c3e50;">SSC Election Settings</h3>
                                    <div class="form-group" style="max-width: 400px;">
                                        <label for="electionDate">Election Date</label>
                                        <input type="date" id="electionDate" placeholder="Select election date">
                                    </div>
                                    <button class="btn-add" onclick="saveElectionDate()" style="margin-top: 0;">
                                        <i class="fa-solid fa-save"></i> Save Election Date
                                    </button>
                                    <p style="color: #666; font-size: 0.9rem; margin-top: 16px;" id="electionDateDisplay">No election date set</p>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Candidate Modal -->
    <div id="candidateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="candidateModalTitle">Add Candidate</h3>
                <button class="modal-close" onclick="closeModal('candidateModal')">&times;</button>
            </div>
            <form id="candidateForm" enctype="multipart/form-data">
                <div style="padding: 20px;">
                    <input type="hidden" id="candidateId" name="id">
                    <input type="hidden" id="candidateStatus" name="status" value="active">
                    <div class="form-group">
                        <label for="candidatePhoto">Profile Photo</label>
                        <input type="file" id="candidatePhoto" name="photo" accept="image/*">
                        <small style="color: #666; font-size: 0.85rem;">Upload a profile picture (JPG, PNG, GIF)</small>
                        <div id="currentPhotoPreview" style="margin-top: 8px; display: none;">
                            <small style="color: #666;">Current photo:</small><br>
                            <img id="currentPhotoImg" src="" alt="Current photo" style="max-width: 100px; max-height: 100px; border-radius: 8px; margin-top: 4px;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="candidateName">Full Name *</label>
                        <input type="text" id="candidateName" name="full_name" required>
                    </div>
                    <div class="form-group" id="officerYearGroup" style="display: none;">
                        <label for="officerYear">Academic Year *</label>
                        <select id="officerYear" name="officer_year" required>
                            <option value="">Select academic year</option>
                        </select>
                        <small style="color: #666; font-size: 0.85rem;">e.g., 2025-2026</small>
                    </div>
                    <div class="form-group">
                        <label for="candidatePosition">Position *</label>
                        <select id="candidatePosition" name="position" required onchange="handlePositionChange()">
                            <option value="">Select position</option>
                            <option value="President">President</option>
                            <option value="Vice President for Academic Affairs">Vice President for Academic Affairs</option>
                            <option value="Vice President for Non-Academic Affairs">Vice President for Non-Academic Affairs</option>
                            <option value="Vice President for Finance">Vice President for Finance</option>
                            <option value="Deputy for Finance">Deputy for Finance</option>
                            <option value="Vice President for Audit">Vice President for Audit</option>
                            <option value="Deputy Vice Auditor">Deputy Vice Auditor</option>
                            <option value="Vice President for Public Relations">Vice President for Public Relations</option>
                            <option value="Vice President for Marketing & Operations">Vice President for Marketing & Operations</option>
                            <option value="Media and Publications Officers">Media and Publications Officers</option>
                            <option value="Executive Secretary">Executive Secretary</option>
                            <option value="Deputy Secretary">Deputy Secretary</option>
                            <option value="College Representatives">College Representatives</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" id="customPositionGroup" style="display: none;">
                        <label for="customPosition">Custom Position</label>
                        <input type="text" id="customPosition" name="custom_position" placeholder="Enter custom position title">
                    </div>
                    <div class="form-group">
                        <label for="candidateCourse">Course</label>
                        <select id="candidateCourse" name="course" required>
                            <option value="">Select course</option>
                            <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                            <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                            <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                            <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                            <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                            <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                            <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="candidateYear">Year Level</label>
                        <select id="candidateYear" name="year">
                            <option value="">Select year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="candidatePlatform">Platform/Description</label>
                        <textarea id="candidatePlatform" name="platform" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('candidateModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Officer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification System -->
    <div id="notification-container" class="notification-container" style="display: none;">
        <div class="notification">
            <span id="notification-message"></span>
        </div>
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

    </script>
    <script src="../assets/js/app.js" defer></script>
    <!-- Advicer Modal -->
    <div id="advicerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="advicerModalTitle">Add SSC Advicer</h3>
                <button class="modal-close" onclick="closeModal('advicerModal')">&times;</button>
            </div>
            <form id="advicerForm">
                <div style="padding: 20px;">
                    <input type="hidden" id="advicerId" name="id">
                    <div class="form-group">
                        <label for="advicerName">Full Name *</label>
                        <input type="text" id="advicerName" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label for="advicerYear">Academic Year *</label>
                        <select id="advicerYear" name="advicer_year" required>
                            <option value="">Select academic year</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('advicerModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Advicer</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        // Notification system
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notification-container');
            const notification = container.querySelector('.notification');
            const messageEl = document.getElementById('notification-message');
            
            messageEl.textContent = message;
            notification.className = `notification ${type}`;
            
            container.style.display = 'block';
            
            setTimeout(() => {
                container.style.display = 'none';
            }, 5000);
        }
        // Course/Year handling
        const courseSelect = document.getElementById('candidateCourse');
        const yearSelect = document.getElementById('candidateYear');
        const twoYearCourses = []; // All remaining courses are 4-year programs

        function populateYearOptions(course) {
            const years = twoYearCourses.includes(course) ? [1, 2] : [1, 2, 3, 4];
            yearSelect.innerHTML = '<option value="">Select year</option>' + years.map(year => {
                return `<option value="${year}">${year}</option>`;
            }).join('');
        }

        courseSelect.addEventListener('change', function() {
            populateYearOptions(this.value);
        });

        // Tab Switching
        document.querySelectorAll('.tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tabName = this.dataset.tab;

                // Update active tab link
                document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');

                // Show active tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabName).classList.add('active');

                // Hide search filter section for Election Settings tab
                const searchFilterSection = document.getElementById('searchFilterSection');
                if (tabName === 'election-settings') {
                    searchFilterSection.style.display = 'none';
                } else {
                    searchFilterSection.style.display = 'flex';
                }
            });
        });

        // Page Initialization
        let allCandidates = [];
        
        // Position order for officers
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

        // Initialize officer year dropdown
        function initializeOfficerYearOptions() {
            const currentYear = new Date().getFullYear();
            const startYear = 1998;
            const officerYearSelect = document.getElementById('officerYear');
            const yearFilterSelect = document.getElementById('officerYearFilter');
            
            let options = '<option value="">Select academic year</option>';
            for (let year = currentYear; year >= startYear; year--) {
                const academicYear = `${year}-${year + 1}`;
                options += `<option value="${academicYear}">${academicYear}</option>`;
            }
            
            officerYearSelect.innerHTML = options;
            
            // Set default to previous academic year (e.g., 2025-2026 when current year is 2026)
            const defaultYear = `${currentYear - 1}-${currentYear}`;
            officerYearSelect.value = defaultYear;
        }

        // Update year filter dropdown based on available officers
        function updateYearFilterOptions() {
            const officers = allCandidates.filter(c => c.status === 'elected' || c.status === 'advicer');
            const yearFilterSelect = document.getElementById('officerYearFilter');
            const uniqueYears = [...new Set(officers.map(o => o.officer_year || o.year_range || ''))].filter(y => y).sort().reverse();
            
            let filterOptions = '<option value="">All Years</option>';
            uniqueYears.forEach(year => {
                filterOptions += `<option value="${year}">${year}</option>`;
            });
            
            yearFilterSelect.innerHTML = filterOptions;
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeOfficerYearOptions();
            loadAllCandidates();
            loadElectionDate();
            document.getElementById('candidateForm').addEventListener('submit', handleCandidateSubmit);
            const advicerFormEl = document.getElementById('advicerForm');
            if (advicerFormEl) advicerFormEl.addEventListener('submit', handleAdvicerSubmit);
            
            // Setup search and filter listeners
            const searchInput = document.getElementById('candidate-search');
            const courseFilter = document.getElementById('course-filter');
            
            searchInput.addEventListener('input', filterCandidates);
            courseFilter.addEventListener('change', filterCandidates);
            
            // Ensure modal is hidden on page load
            const modal = document.getElementById('candidateModal');
            modal.classList.remove('show');
        });

        // Load Candidates
        async function loadAllCandidates() {
            try {
                const response = await fetch('ssc_api.php?action=get_candidates');
                const data = await response.json();
                allCandidates = data.candidates || [];
                console.log('All candidates loaded:', allCandidates);
                console.log('Advicers:', allCandidates.filter(c => c.status === 'advicer'));
                displayCandidatesByStatus(allCandidates);
            } catch (error) {
                console.error('Error loading candidates:', error);
            }
        }

        // Filter candidates based on search and course
        function filterCandidates() {
            const searchValue = document.getElementById('candidate-search').value.toLowerCase();
            const courseValue = document.getElementById('course-filter').value.toLowerCase();
            const yearFilterValue = document.getElementById('officerYearFilter') ? document.getElementById('officerYearFilter').value : '';
            
            // Filter the candidates
            const filtered = allCandidates.filter(candidate => {
                const name = candidate.full_name.toLowerCase();
                const position = candidate.position.toLowerCase();
                const courseYear = (candidate.course_year || '').toLowerCase();
                const officerYear = candidate.officer_year || candidate.year_range || '';
                
                const matchesSearch = !searchValue || name.includes(searchValue) || position.includes(searchValue);
                const matchesCourse = !courseValue || courseYear.includes(courseValue);
                const matchesYear = !yearFilterValue || ((candidate.status === 'elected' || candidate.status === 'advicer') && officerYear === yearFilterValue);
                
                return matchesSearch && matchesCourse && matchesYear;
            });
            
            // Display filtered candidates
            displayCandidatesByStatus(filtered);
            
            // Update results count
            const count = filtered.length;
            const countEl = document.getElementById('results-count');
            if (countEl) {
                countEl.textContent = 'Showing ' + count + ' candidate' + (count !== 1 ? 's' : '');
            }
        }

        function displayCandidatesByStatus(candidates) {
            // Get all elected officers
            const electedOfficers = candidates.filter(c => c.status === 'elected');
            
            // Find the maximum year among elected officers
            let maxYear = null;
            if (electedOfficers.length > 0) {
                const years = electedOfficers.map(c => {
                    const year = c.officer_year || c.year_range || ((new Date().getFullYear() - 1) + '-' + new Date().getFullYear());
                    return parseInt(year.split('-')[0]); // Get starting year as number
                });
                maxYear = Math.max(...years);
            }
            
            // Separate officers: current (max year) vs previous (older years)
            const currentYearOfficers = electedOfficers.filter(c => {
                const year = c.officer_year || c.year_range || ((new Date().getFullYear() - 1) + '-' + new Date().getFullYear());
                const startYear = parseInt(year.split('-')[0]);
                return startYear === maxYear;
            });
            
            const previousYearOfficers = electedOfficers.filter(c => {
                const year = c.officer_year || c.year_range || ((new Date().getFullYear() - 1) + '-' + new Date().getFullYear());
                const startYear = parseInt(year.split('-')[0]);
                return startYear < maxYear;
            });

            // Get advicers for current year
            const currentYearAdvicers = candidates.filter(c => c.status === 'advicer' && 
                (c.officer_year || c.year_range || ((new Date().getFullYear() - 1) + '-' + new Date().getFullYear())).split('-')[0] === maxYear?.toString()
            );
            
            // Get advicers from previous years
            const previousYearAdvicers = candidates.filter(c => c.status === 'advicer' && 
                parseInt((c.officer_year || c.year_range || ((new Date().getFullYear() - 1) + '-' + new Date().getFullYear())).split('-')[0]) < maxYear
            );
            
            // Update badges
            document.getElementById('badge-officers').textContent = currentYearOfficers.length;
            document.getElementById('badge-previous-officer').textContent = previousYearOfficers.length + previousYearAdvicers.length + candidates.filter(c => c.status === 'previous').length;
            document.getElementById('badge-candidate').textContent = candidates.filter(c => c.status === 'active').length;
            document.getElementById('badge-withdrawn').textContent = candidates.filter(c => c.status === 'withdrawn').length;
            
            // Combine current officers and advicers for display
            const currentOfficersAndAdvicers = currentYearOfficers.concat(currentYearAdvicers);
            displayCandidates(currentOfficersAndAdvicers, 'officers', 'candidates-officers', true);
            
            // Combine previous officers and advicers with status='previous' for display
            const allPreviousOfficers = previousYearOfficers.concat(previousYearAdvicers).concat(candidates.filter(c => c.status === 'previous'));
            displayCandidates(allPreviousOfficers, 'previous', 'candidates-all', false);
            
            displayCandidates(candidates.filter(c => c.status === 'active'), 'candidate', 'candidates-candidate');
            displayCandidates(candidates.filter(c => c.status === 'withdrawn'), 'withdrawn', 'candidates-withdrawn');
        }

        function displayCandidates(candidates, status, containerId, isOfficers = false) {
            const container = document.getElementById(containerId);

            if (candidates.length === 0) {
                let emptyMessage, emptyText;
                if (status === 'officers') {
                    emptyMessage = 'No current officers set';
                    emptyText = 'Promote candidates to officer status to add them here.';
                } else if (status === 'previous') {
                    emptyMessage = 'No previous officers';
                    emptyText = 'Previous officers will appear here when replaced.';
                } else if (status === 'candidate') {
                    emptyMessage = 'No candidates';
                    emptyText = 'Add candidates to get started.';
                } else {
                    emptyMessage = `No ${status} Candidates`;
                    emptyText = 'Add a new candidate to get started.';
                }
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-user-group"></i>
                        <h3>${emptyMessage}</h3>
                        <p>${emptyText}</p>
                    </div>
                `;
                return;
            }

            if (isOfficers) {
                // Group officers AND advicers by year
                const groupedByYear = {};
                candidates.forEach(candidate => {
                    const year = candidate.officer_year || candidate.year_range || ( (new Date().getFullYear() - 1) + '-' + new Date().getFullYear() );
                    if (!groupedByYear[year]) {
                        groupedByYear[year] = { officers: [], advicers: [] };
                    }
                    if (candidate.status === 'advicer') {
                        groupedByYear[year].advicers.push(candidate);
                    } else {
                        groupedByYear[year].officers.push(candidate);
                    }
                });
                
                // Sort years in descending order
                const sortedYears = Object.keys(groupedByYear).sort((a, b) => {
                    const yearA = parseInt(a.split('-')[0]);
                    const yearB = parseInt(b.split('-')[0]);
                    return yearB - yearA;
                });
                
                let html = '';
                sortedYears.forEach(year => {
                    const officersInYear = groupedByYear[year].officers;
                    const advicersInYear = groupedByYear[year].advicers;
                    
                    // Only render year section if there are officers or advicers
                    if (officersInYear.length === 0 && advicersInYear.length === 0) return;
                    
                    // Sort officers by position order (normalize strings) and fall back to name
                    const normalizedOrder = positionOrder.map(p => p.toString().toLowerCase().replace(/\s+/g, ' ').trim());
                    const normalize = s => (s || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();

                    officersInYear.sort((a, b) => {
                        const posAraw = normalize(a.position);
                        const posBraw = normalize(b.position);

                        const posAidx = normalizedOrder.indexOf(posAraw);
                        const posBidx = normalizedOrder.indexOf(posBraw);

                        const aPos = posAidx === -1 ? normalizedOrder.length : posAidx;
                        const bPos = posBidx === -1 ? normalizedOrder.length : posBidx;

                        if (aPos !== bPos) return aPos - bPos;
                        return (a.full_name || '').localeCompare(b.full_name || '');
                    });
                    
                    html += `<div style="margin-bottom: 50px;">
                        <h3 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px;">
                            <i class="fa-solid fa-crown" style="color: #f39c12; margin-right: 8px;"></i>SSC Appointed Officer ${year}
                        </h3>
                        <div class="candidates-grid">`;
                    
                    // Render OFFICERS
                    officersInYear.forEach(candidate => {
                        html += `<div class="candidate-item">
                            ${candidate.photo_url ? 
                                `<img src="../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : 
                                `<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>`
                            }
                            <h3 class="candidate-name">${candidate.full_name}</h3>
                            <p class="candidate-position">${candidate.position}</p>
                            ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                            ${candidate.platform ? `<p class="candidate-platform">${candidate.platform}</p>` : ''}
                            <span class="candidate-status status-elected">Current Officer</span>
                            <div class="candidate-actions">
                                <button class="btn-sm btn-edit" onclick="showCandidateModal(${candidate.id})">
                                    <i class="fa-solid fa-edit"></i> Edit
                                </button>
                                <button class="btn-sm btn-edit" onclick="replaceOfficer(${candidate.id})" style="background: #f39c12;">
                                    <i class="fa-solid fa-replace"></i> Replace
                                </button>
                                <button class="btn-sm btn-delete" onclick="withdrawOfficer(${candidate.id})">
                                    <i class="fa-solid fa-sign-out-alt"></i> Withdraw
                                </button>
                            </div>
                        </div>`;
                    });
                    
                    html += `</div>`;
                    
                    // Render ADVICERS below officers
                    if (advicersInYear.length > 0) {
                        html += `<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9e6e2;">
                            <h4 style="color: #5a6b7a; margin-bottom: 15px;">
                                <i class="fa-solid fa-chalkboard-user" style="color: #6b7280; margin-right: 8px;"></i>SSC Advicer(s) ${year}
                            </h4>
                            <div class="candidates-grid">`;
                        
                        advicersInYear.forEach(candidate => {
                            html += `<div class="candidate-item status-advicer">
                                ${candidate.photo_url ? `<img src="../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : `<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>`}
                                <h3 class="candidate-name">${candidate.full_name}</h3>
                                ${candidate.position ? `<p class="candidate-position">${candidate.position}</p>` : ''}
                                ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                                <span class="candidate-status status-advicer">Advisor</span>
                                <div class="candidate-actions">
                                    <button class="btn-sm btn-edit" onclick="showCandidateModal(${candidate.id})"><i class="fa-solid fa-edit"></i> Edit</button>
                                    <button class="btn-sm btn-delete" onclick="deleteCandidate(${candidate.id})"><i class="fa-solid fa-trash"></i> Delete</button>
                                </div>
                            </div>`;
                        });
                        
                        html += `</div></div>`;
                    }
                    
                    html += `</div>`;
                });
                
                // Remove grid class so year sections display full-width as blocks
                container.classList.remove('candidates-grid');
                container.innerHTML = html;
                updateYearFilterOptions();
            } else {
                // Group by year for 'previous' status, or by year+position for 'candidate' status
                if (status === 'previous') {
                    // Group previous officers by year
                    const groupedByYear = {};
                    candidates.forEach(candidate => {
                        const year = candidate.officer_year || candidate.year_range || ((new Date().getFullYear() - 1) + '-' + new Date().getFullYear());
                        if (!groupedByYear[year]) {
                            groupedByYear[year] = [];
                        }
                        groupedByYear[year].push(candidate);
                    });
                    
                    // Sort years in descending order
                    const sortedYears = Object.keys(groupedByYear).sort((a, b) => {
                        const yearA = parseInt(a.split('-')[0]);
                        const yearB = parseInt(b.split('-')[0]);
                        return yearB - yearA;
                    });
                    
                    let html = '';
                    sortedYears.forEach(year => {
                        const yearCandidates = groupedByYear[year];
                        
                        // Separate officers and advicers
                        const officersInYear = yearCandidates.filter(c => c.status !== 'advicer');
                        const advicersInYear = yearCandidates.filter(c => c.status === 'advicer');
                        
                        if (officersInYear.length === 0 && advicersInYear.length === 0) return;
                        
                        // Sort by position order and name
                        const normalizedOrder = positionOrder.map(p => p.toString().toLowerCase().replace(/\s+/g, ' ').trim());
                        const normalize = s => (s || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();
                        
                        officersInYear.sort((a, b) => {
                            const posAraw = normalize(a.position);
                            const posBraw = normalize(b.position);
                            
                            const posAidx = normalizedOrder.indexOf(posAraw);
                            const posBidx = normalizedOrder.indexOf(posBraw);
                            
                            const aPos = posAidx === -1 ? normalizedOrder.length : posAidx;
                            const bPos = posBidx === -1 ? normalizedOrder.length : posBidx;
                            
                            if (aPos !== bPos) return aPos - bPos;
                            return (a.full_name || '').localeCompare(b.full_name || '');
                        });
                        
                        // Only render if there are officers
                        if (officersInYear.length > 0) {
                            html += `<div style="margin-bottom: 50px;">
                                <h3 style="color: #2c3e50; border-bottom: 2px solid #8b5a3c; padding-bottom: 10px; margin-bottom: 20px;">
                                    <i class="fa-solid fa-history" style="color: #95754d; margin-right: 8px;"></i>SSC Appointed Officer ${year}
                                </h3>
                                <div class="candidates-grid">`;
                            
                            officersInYear.forEach(candidate => {
                                html += `<div class="candidate-item">
                                    ${candidate.photo_url ? 
                                        `<img src="../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : 
                                        `<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>`
                                    }
                                    <h3 class="candidate-name">${candidate.full_name}</h3>
                                    <p class="candidate-position">${candidate.position}</p>
                                    ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                                    ${candidate.platform ? `<p class="candidate-platform">${candidate.platform}</p>` : ''}
                                    <span class="candidate-status status-previous">Previous Officer</span>
                                    <div class="candidate-actions">
                                        <button class="btn-sm btn-edit" onclick="showCandidateModal(${candidate.id})">
                                            <i class="fa-solid fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-sm btn-delete" onclick="deleteCandidate(${candidate.id})">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>`;
                            });
                            
                            html += `</div>`;
                            
                            // Render ADVICERS below officers
                            if (advicersInYear.length > 0) {
                                html += `<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9e6e2;">
                                    <h4 style="color: #5a6b7a; margin-bottom: 15px;">
                                        <i class="fa-solid fa-chalkboard-user" style="color: #6b7280; margin-right: 8px;"></i>SSC Advicer(s) ${year}
                                    </h4>
                                    <div class="candidates-grid">`;
                                
                                advicersInYear.forEach(candidate => {
                                    html += `<div class="candidate-item status-advicer">
                                        ${candidate.photo_url ? `<img src="../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : `<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>`}
                                        <h3 class="candidate-name">${candidate.full_name}</h3>
                                        ${candidate.position ? `<p class="candidate-position">${candidate.position}</p>` : ''}
                                        ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                                        <span class="candidate-status status-previous">Previous Advisor</span>
                                        <div class="candidate-actions">
                                            <button class="btn-sm btn-edit" onclick="showCandidateModal(${candidate.id})"><i class="fa-solid fa-edit"></i> Edit</button>
                                            <button class="btn-sm btn-delete" onclick="deleteCandidate(${candidate.id})"><i class="fa-solid fa-trash"></i> Delete</button>
                                        </div>
                                    </div>`;
                                });
                                
                                html += `</div></div>`;
                            }
                            
                            html += `</div>`;
                        } else if (advicersInYear.length > 0) {
                            // If only advicers, show them with year header
                            html += `<div style="margin-bottom: 50px;">
                                <h3 style="color: #2c3e50; border-bottom: 2px solid #8b5a3c; padding-bottom: 10px; margin-bottom: 20px;">
                                    <i class="fa-solid fa-chalkboard-user" style="color: #95754d; margin-right: 8px;"></i>SSC Advicer(s) ${year}
                                </h3>
                                <div class="candidates-grid">`;
                            
                            advicersInYear.forEach(candidate => {
                                html += `<div class="candidate-item status-advicer">
                                    ${candidate.photo_url ? `<img src="../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : `<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>`}
                                    <h3 class="candidate-name">${candidate.full_name}</h3>
                                    ${candidate.position ? `<p class="candidate-position">${candidate.position}</p>` : ''}
                                    ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                                    <span class="candidate-status status-previous">Previous Advisor</span>
                                    <div class="candidate-actions">
                                        <button class="btn-sm btn-edit" onclick="showCandidateModal(${candidate.id})"><i class="fa-solid fa-edit"></i> Edit</button>
                                        <button class="btn-sm btn-delete" onclick="deleteCandidate(${candidate.id})"><i class="fa-solid fa-trash"></i> Delete</button>
                                    </div>
                                </div>`;
                            });
                            
                            html += `</div></div>`;
                        }
                    });
                    
                    container.classList.remove('candidates-grid');
                    container.innerHTML = html;
                } else if (status === 'candidate') {
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
                        
                        html += `<div style="margin-bottom: 50px;">
                            <h3 style="color: #2c3e50; border-bottom: 2px solid #27ae60; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fa-solid fa-users" style="color: #27ae60; margin-right: 8px;"></i>Candidates ${year}
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
                            
                            html += `<div style="margin-bottom: 30px;">
                                <h4 style="color: #555; font-size: 1.05rem; margin-bottom: 12px; padding-left: 10px; border-left: 3px solid #27ae60;">
                                    ${position}
                                </h4>
                                <div class="candidates-grid">`;
                            
                            candidatesInPosition.forEach(candidate => {
                                html += `<div class="candidate-item">
                                    ${candidate.photo_url ? 
                                        `<img src="../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : 
                                        `<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>`
                                    }
                                    <h3 class="candidate-name">${candidate.full_name}</h3>
                                    ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                                    ${candidate.platform ? `<p class="candidate-platform">${candidate.platform}</p>` : ''}
                                    <span class="candidate-status status-active">Candidate</span>
                                    <div class="candidate-actions">
                                        <button class="btn-sm btn-edit" onclick="showCandidateModal(${candidate.id})">
                                            <i class="fa-solid fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-sm btn-delete" onclick="deleteCandidate(${candidate.id})">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>`;
                            });
                            
                            html += `</div></div>`;
                        });
                        
                        html += `</div>`;
                    });
                    
                    container.classList.remove('candidates-grid');
                    container.innerHTML = html;
                } else {
                    // For other statuses (withdrawn), use simple grid layout
                    container.classList.add('candidates-grid');
                    container.innerHTML = candidates.map(candidate => `
                    <div class="candidate-item">
                        ${candidate.photo_url ? 
                            `<img src="../${candidate.photo_url}" alt="${candidate.full_name}" class="candidate-photo">` : 
                            `<div class="candidate-photo-placeholder"><i class="fa-solid fa-user"></i></div>`
                        }
                        <h3 class="candidate-name">${candidate.full_name}</h3>
                        <p class="candidate-position">${candidate.position}</p>
                        ${candidate.course_year ? `<p class="candidate-course">${candidate.course_year}</p>` : ''}
                        ${candidate.platform ? `<p class="candidate-platform">${candidate.platform}</p>` : ''}
                        <span class="candidate-status status-${candidate.status}">${status === 'officers' ? 'Current Officer' : status === 'previous' ? 'Previous Officer' : candidate.status}</span>
                        <div class="candidate-actions">
                            <button class="btn-sm btn-edit" onclick="showCandidateModal(${candidate.id})">
                                <i class="fa-solid fa-edit"></i> Edit
                            </button>
                            <button class="btn-sm btn-delete" onclick="deleteCandidate(${candidate.id})">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `).join('');
                }
            }
        }

        let replacingOfficerId = null;

        // Handle position change - show custom position input if "other" selected
        function handlePositionChange() {
            const positionSelect = document.getElementById('candidatePosition');
            const customPositionGroup = document.getElementById('customPositionGroup');
            
            if (positionSelect.value === 'other') {
                customPositionGroup.style.display = 'block';
                document.getElementById('customPosition').required = true;
            } else {
                customPositionGroup.style.display = 'none';
                document.getElementById('customPosition').required = false;
                document.getElementById('customPosition').value = '';
            }
        }

        // Replace Officer Function
        function replaceOfficer(candidateId) {
            replacingOfficerId = candidateId;
            const modal = document.getElementById('candidateModal');
            const form = document.getElementById('candidateForm');
            const title = document.getElementById('candidateModalTitle');
            const currentPhotoPreview = document.getElementById('currentPhotoPreview');
            
            title.textContent = 'Add Replacement Officer';
            form.reset();
            document.getElementById('candidateId').value = '';
            currentPhotoPreview.style.display = 'none';
            document.getElementById('candidateStatus').value = 'elected';
            
            modal.classList.add('show');
        }

        // Modal Functions
        function showCandidateModal(candidateId = null, type = 'candidate') {
            replacingOfficerId = null;
            const modal = document.getElementById('candidateModal');
            const form = document.getElementById('candidateForm');
            const title = document.getElementById('candidateModalTitle');
            const currentPhotoPreview = document.getElementById('currentPhotoPreview');
            const currentPhotoImg = document.getElementById('currentPhotoImg');
            const officerYearGroup = document.getElementById('officerYearGroup');

            if (candidateId) {
                // EDITING existing candidate
                title.textContent = 'Edit Officer';
                
                // Fetch candidate data
                fetch(`ssc_api.php?action=get_candidates`)
                    .then(response => response.json())
                    .then(data => {
                        const candidate = data.candidates.find(c => c.id == candidateId);
                        if (candidate) {
                            document.getElementById('candidateId').value = candidate.id;
                            document.getElementById('candidateName').value = candidate.full_name;
                            
                            // Set position - check if it's a standard position or custom
                            const positionSelect = document.getElementById('candidatePosition');
                            if (positionOrder.includes(candidate.position)) {
                                positionSelect.value = candidate.position;
                                document.getElementById('customPositionGroup').style.display = 'none';
                            } else {
                                positionSelect.value = 'other';
                                document.getElementById('customPosition').value = candidate.position;
                                document.getElementById('customPositionGroup').style.display = 'block';
                            }
                            
                            document.getElementById('candidatePlatform').value = candidate.platform || '';
                            document.getElementById('candidateStatus').value = candidate.status || 'active';
                            
                            // Set officer year if exists
                            if (candidate.officer_year || candidate.year_range) {
                                document.getElementById('officerYear').value = candidate.officer_year || candidate.year_range;
                            }
                            
                            // Parse course_year and set course and year separately
                            if (candidate.course_year) {
                                const parts = candidate.course_year.trim().split(' ');
                                const lastPart = parts[parts.length - 1];
                                
                                // Check if last part is a year number (1, 2, 3, 4)
                                if (/^\d$/.test(lastPart)) {
                                    const year = lastPart;
                                    const course = parts.slice(0, -1).join(' ');
                                    
                                    courseSelect.value = course;
                                    populateYearOptions(course);
                                    yearSelect.value = year;
                                } else {
                                    courseSelect.value = candidate.course_year;
                                    populateYearOptions(candidate.course_year);
                                }
                            }
                            
                            // Show current photo if exists
                            if (candidate.photo_url) {
                                currentPhotoImg.src = '../' + candidate.photo_url;
                                currentPhotoPreview.style.display = 'block';
                            } else {
                                currentPhotoPreview.style.display = 'none';
                            }
                            
                            // Show officer year group for editing officers
                            if (candidate.status === 'elected') {
                                officerYearGroup.style.display = 'block';
                            } else {
                                officerYearGroup.style.display = 'none';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching candidate:', error);
                        showNotification('Error loading candidate data', 'error');
                    });
            } else {
                // ADDING new officer or candidate
                if (type === 'officer') {
                    title.textContent = 'Add New Officer';
                    document.getElementById('candidateStatus').value = 'elected';
                    officerYearGroup.style.display = 'block';
                } else {
                    title.textContent = 'Add New Candidate';
                    document.getElementById('candidateStatus').value = 'active';
                    officerYearGroup.style.display = 'none';
                }
                
                form.reset();
                document.getElementById('candidateId').value = '';
                document.getElementById('candidatePosition').value = '';
                document.getElementById('customPositionGroup').style.display = 'none';
                document.getElementById('officerYear').value = new Date().getFullYear() + '-' + (new Date().getFullYear() + 1);
                currentPhotoPreview.style.display = 'none';
                handlePositionChange(); // Reset position state
            }

            modal.classList.add('show');
        }

        function closeModal(modalId) {
            replacingOfficerId = null;
            document.getElementById(modalId).classList.remove('show');
        }

        // Show Advicer Modal
        function showAdvicerModal() {
            const modal = document.getElementById('advicerModal');
            const advicerForm = document.getElementById('advicerForm');
            const officerYear = document.getElementById('officerYear');
            const advicerYear = document.getElementById('advicerYear');
            if (advicerForm) advicerForm.reset();
            if (officerYear && advicerYear) {
                advicerYear.innerHTML = officerYear.innerHTML;
                advicerYear.value = officerYear.value || `${new Date().getFullYear() - 1}-${new Date().getFullYear()}`;
            }
            modal.classList.add('show');
        }

        // Handle advicer submit
        async function handleAdvicerSubmit(e) {
            e.preventDefault();
            const name = document.getElementById('advicerName').value.trim();
            const year = document.getElementById('advicerYear').value;
            if (!name) {
                showNotification('Please enter advicer name', 'error');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('full_name', name);
                formData.append('position', 'SSC Advicer');
                formData.append('status', 'advicer');
                formData.append('officer_year', year);

                console.log('Sending advicer data:', {full_name: name, position: 'SSC Advicer', status: 'advicer', officer_year: year});

                const response = await fetch('ssc_api.php?action=save_candidate', {
                    method: 'POST',
                    body: formData
                });
                const text = await response.text();
                console.log('API response (raw):', text);
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('Invalid JSON response from save_candidate:', text);
                    showNotification('Error saving advicer: server returned invalid response', 'error');
                    return;
                }
                console.log('API response (parsed):', result);
                if (result.success) {
                    closeModal('advicerModal');
                    loadAllCandidates();
                    showNotification('SSC Advicer added');
                } else {
                    showNotification('Error saving advicer: ' + (result.message || 'unknown'), 'error');
                }
            } catch (err) {
                console.error('Error saving advicer', err);
                showNotification('Error saving advicer', 'error');
            }
        }

        // Form Submission
        async function handleCandidateSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            // Handle position - if "other" is selected, use custom position
            const positionSelect = document.getElementById('candidatePosition');
            if (positionSelect.value === 'other') {
                const customPosition = document.getElementById('customPosition').value.trim();
                if (!customPosition) {
                    showNotification('Please enter a custom position', 'error');
                    return;
                }
                formData.set('position', customPosition);
            } else {
                formData.set('position', positionSelect.value);
            }
            
            // Combine course and year into course_year
            const course = formData.get('course');
            const year = formData.get('year');
            let courseYear = '';
            
            if (course && year) {
                courseYear = `${course} ${year}`;
            } else if (course) {
                courseYear = course;
            }
            
            // Add course_year to form data
            formData.set('course_year', courseYear);
            
            // Add officer_year if this is an officer
            const status = formData.get('status');
            if (status === 'elected') {
                const officerYear = document.getElementById('officerYear').value;
                formData.append('officer_year', officerYear);
            }

            try {
                const response = await fetch('ssc_api.php?action=save_candidate', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // If we're replacing an officer, mark the old one as previous
                    if (replacingOfficerId) {
                        try {
                            const updateResponse = await fetch('ssc_api.php?action=save_candidate', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: replacingOfficerId, status: 'previous' })
                            });
                            const updateResult = await updateResponse.json();
                            
                            if (updateResult.success) {
                                replacingOfficerId = null;
                                closeModal('candidateModal');
                                loadAllCandidates();
                                showNotification('Officer replaced successfully!');
                            } else {
                                closeModal('candidateModal');
                                loadAllCandidates();
                                showNotification('New officer added, but failed to move old officer to previous', 'error');
                            }
                        } catch (updateError) {
                            console.error('Error updating old officer status:', updateError);
                            closeModal('candidateModal');
                            loadAllCandidates();
                            showNotification('New officer added, but error moving old officer to previous', 'error');
                        }
                    } else {
                        closeModal('candidateModal');
                        loadAllCandidates();
                        showNotification('Officer saved successfully!');
                    }
                } else {
                    showNotification('Error saving officer: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error saving officer', 'error');
            }
        }

        // Delete Candidate
        async function deleteCandidate(id) {
            try {
                const response = await fetch('ssc_api.php?action=delete_candidate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const result = await response.json();

                if (result.success) {
                    loadAllCandidates();
                    showNotification('Candidate deleted successfully!');
                } else {
                    showNotification('Error deleting candidate: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error deleting candidate', 'error');
            }
        }

        // Withdraw Officer
        async function withdrawOfficer(id) {
            try {
                const response = await fetch('ssc_api.php?action=save_candidate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, status: 'withdrawn' })
                });
                const result = await response.json();

                if (result.success) {
                    loadAllCandidates();
                    showNotification('Officer withdrawn successfully!');
                } else {
                    showNotification('Error withdrawing officer: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error withdrawing officer', 'error');
            }
        }

        // Load Election Date
        async function loadElectionDate() {
            try {
                const response = await fetch('ssc_api.php?action=get_election_date');
                const data = await response.json();
                if (data.election_date) {
                    document.getElementById('electionDate').value = data.election_date;
                    document.getElementById('electionDateDisplay').textContent = `Election date: ${formatDate(data.election_date)}`;
                }
            } catch (error) {
                console.error('Error loading election date:', error);
            }
        }

        // Save Election Date
        async function saveElectionDate() {
            const electionDate = document.getElementById('electionDate').value;
            if (!electionDate) {
                showNotification('Please select an election date', 'error');
                return;
            }

            try {
                const response = await fetch('ssc_api.php?action=save_election_date', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ election_date: electionDate })
                });
                const result = await response.json();

                if (result.success) {
                    document.getElementById('electionDateDisplay').textContent = `Election date: ${formatDate(electionDate)}`;
                    showNotification('Election date saved successfully!');
                } else {
                    showNotification('Error saving election date: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error saving election date', 'error');
            }
        }

        // Format Date Helper
        function formatDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Close modal when clicking outside
        // Switch Candidate Tab Function - matches library_inventory.php pattern
        window.switchTab = function(tabId, event) {
            if (event) {
                event.preventDefault();
            }
            // Update active clinic tab
            document.querySelectorAll('.inventory-tab-nav.clinic-tabs .clinic-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.closest('.clinic-tab').classList.add('active');
            
            // Show active tab content
            document.querySelectorAll('.inventory-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            const activeTab = document.getElementById(tabId);
            if (activeTab) {
                activeTab.classList.add('active');
            }
            
            // Hide search filter section for Election Settings tab
            const searchFilterSection = document.getElementById('searchFilterSection');
            if (tabId === 'election-settings') {
                searchFilterSection.style.display = 'none';
            } else {
                searchFilterSection.style.display = 'flex';
            }
        };

        window.onclick = function(event) {
            const modal = document.getElementById('candidateModal');
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        };

        /* --- Touch swipe handler for candidate tabs (320px - 720px) --- */
        function enableCandidateTabSwipe() {
            const container = document.querySelector('.inventory-tab-nav.clinic-tabs');
            if (!container) return;

            let startX = 0, startY = 0;
            container.addEventListener('touchstart', (e) => {
                if (window.innerWidth > 720 || window.innerWidth < 320) return;
                const t = e.touches[0]; startX = t.clientX; startY = t.clientY;
            }, { passive: true });

            container.addEventListener('touchend', (e) => {
                if (window.innerWidth > 720 || window.innerWidth < 320) return;
                const t = (e.changedTouches && e.changedTouches[0]) || {}; const dx = t.clientX - startX; const dy = t.clientY - startY;
                if (Math.abs(dx) < 50 || Math.abs(dy) > Math.abs(dx)) return;
                const tabs = Array.from(container.querySelectorAll('.clinic-tab'));
                const activeIndex = tabs.findIndex(t => t.classList.contains('active'));
                if (dx < 0 && activeIndex < tabs.length - 1) {
                    tabs[activeIndex + 1].click();
                    tabs[activeIndex + 1].scrollIntoView({ behavior: 'smooth', inline: 'center' });
                } else if (dx > 0 && activeIndex > 0) {
                    tabs[activeIndex - 1].click();
                    tabs[activeIndex - 1].scrollIntoView({ behavior: 'smooth', inline: 'center' });
                }
            }, { passive: true });
        }

        // Initialize candidate tab swipe on load
        window.addEventListener('load', function() {
            enableCandidateTabSwipe();
        });
    </script>
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