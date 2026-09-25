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
    <title>Manage SSC Events & Proposals | PASS Support System</title>
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
            margin-top: 14px;
        }

        .btn-add:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .events-list {
            display: grid;
            gap: 16px;
        }

        .event-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            transition: all 0.3s ease;
        }

        .event-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #3498db;
        }

        .event-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .event-item-title {
            margin: 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .event-item-actions {
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
            display: flex;
            align-items: center;
            gap: 4px;
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

        .event-item-details {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .event-item-details p {
            margin: 4px 0;
        }

        .event-status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            background: #3498db;
            color: white;
        }

        .status-upcoming { background: #3498db; }
        .status-ongoing { background: #f39c12; }
        .status-completed { background: #27ae60; }
        .status-cancelled { background: #e74c3c; }

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

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .image-preview-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .image-preview-card {
            position: relative;
            width: 90px;
            height: 90px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e9ecef;
            background: #fff;
        }

        .image-preview-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-preview-remove {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: none;
            background: rgba(0,0,0,0.6);
            color: white;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Main Section Tabs */
        .main-tabs-container {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 24px;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }

        .main-tab-link {
            flex: 1;
            padding: 16px 24px;
            border: none;
            background: none;
            cursor: pointer;
            color: #666;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .main-tab-link:hover {
            color: #3498db;
            background: rgba(52, 152, 219, 0.1);
        }

        .main-tab-link.active {
            color: #3498db;
            background: white;
            border-bottom-color: #3498db;
        }

        .main-tab-content {
            display: none;
        }

        .main-tab-content.active {
            display: block;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding: 20px;
            background: white;
            border-radius: 0 8px 8px 8px;
            border: 1px solid #e9ecef;
            border-top: none;
        }

        .section-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.25rem;
        }

        /* Initiatives/Proposals Styles */
        .initiatives-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .initiative-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .initiative-item:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-color: #3498db;
            transform: translateY(-2px);
        }

        .initiative-header {
            margin-bottom: 12px;
        }

        .initiative-title {
            margin: 0;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .initiative-category {
            color: #3498db;
            font-weight: 500;
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .initiative-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
            margin: 12px 0;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .initiative-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .status-planning { background: #fff3cd; color: #856404; }
        .status-active { background: #d4edda; color: #155724; }
        .status-completed { background: #cfe2ff; color: #084298; }
        .status-cancelled { background: #f8f9fa; color: #666; }

        .initiative-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

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
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 12px;
            width: 100%;
            max-width: 600px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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
        .form-group textarea,
        .form-group select {
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

        .form-group textarea {
            resize: vertical;
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

        @media (max-width: 768px) {
            .management-page {
                flex-direction: column;
            }

            .tabs-container {
                flex-wrap: wrap;
            }

            .tab-link {
                padding: 10px 12px;
                font-size: 0.85rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .initiatives-grid {
                grid-template-columns: 1fr;
            }

            .main-tabs-container {
                flex-direction: column;
            }

            .main-tab-link {
                padding: 12px 16px;
            }
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
            margin-bottom: 10px;
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
        /* Swipe + small-screen improvements for tabs */
        @media (max-width: 720px) {
            .main-tabs-container,
            .inventory-tab-nav.clinic-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scroll-snap-type: x mandatory;
                white-space: nowrap;
            }

            .main-tab-link,
            .inventory-tab-nav.clinic-tabs .clinic-tab {
                scroll-snap-align: center;
                min-width: 110px;
                flex: 0 0 auto;
            }

            .main-tabs-container::-webkit-scrollbar,
            .inventory-tab-nav.clinic-tabs::-webkit-scrollbar {
                height: 6px;
            }

            .main-tabs-container::-webkit-scrollbar-thumb,
            .inventory-tab-nav.clinic-tabs::-webkit-scrollbar-thumb {
                background: rgba(0,0,0,0.12);
                border-radius: 6px;
            }
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
    <style>
        .management-content,
        .event-item,
        .section-header,
        .modal-content {
            color: #3d3030;
        }

        .main-tabs-container,
        .tabs-container {
            border-color: #eadbd2;
        }

        .main-tab-link:hover,
        .tab-link:hover {
            color: #8f0011;
            background: #fbf0e8;
        }

        .main-tab-link.active,
        .tab-link.active {
            color: #8f0011;
            border-bottom-color: #8f0011;
        }

        .btn-add,
        .btn-edit,
        .tab-link.active .tab-badge {
            background: #8f0011 !important;
            color: #ffffff !important;
        }

        .btn-add:hover,
        .btn-edit:hover {
            background: #68151c !important;
        }

        .event-item,
        .initiative-item {
            background: #fffaf5;
            border-color: #eadbd2;
        }

        .event-item:hover,
        .initiative-item:hover {
            border-color: #c4877e;
            box-shadow: 0 8px 24px rgba(104, 21, 28, .1);
        }

        .event-item-title,
        .initiative-title,
        .management-header h2,
        .section-header h3,
        .empty-state h3 {
            color: #4d2427;
        }

        .initiative-category {
            color: #a24a49;
        }

        .event-status-badge,
        .status-upcoming {
            background: #c4877e !important;
            color: #ffffff !important;
        }

        .status-completed {
            background: #d8e7d8;
            color: #396044;
        }

        .btn-primary {
            background: #8f0011 !important;
        }

        .btn-primary:hover {
            background: #68151c !important;
        }

        .btn-secondary {
            background: #fbf0e8 !important;
            color: #68151c !important;
            border: 1px solid #d8b8a8 !important;
        }

        .btn-secondary:hover {
            background: #f4e2d9 !important;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #a24a49 !important;
            box-shadow: 0 0 0 2px rgba(162, 74, 73, .18) !important;
        }

        .empty-state i {
            color: #c4877e;
        }

        .feedback-summary-card {
            background: #fbf0e8 !important;
            border-color: #eadbd2 !important;
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
                        <a href="ssc_manage_events.php" class="active" data-tooltip="SSC Events">
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
                        <span class="eyebrow">SSC EVENTS</span>
                        <h1>Manage SSC Events and Proposals</h1>
                        <p class="scholar-header__subtitle">Track upcoming events, manage project proposals, and view event details in one clean, organized view.</p>
                    </div>
                    <div class="scholar-header__art" aria-hidden="true"><i class="fa-solid fa-message chat-left"></i><i class="fa-solid fa-comment chat-top"></i><i class="fa-solid fa-address-card profile-card"></i><i class="fa-solid fa-heart heart-check"></i><i class="fa-solid fa-leaf scholar-leaf"></i><i class="fa-solid fa-circle scholar-dot"></i></div>
                </section>
                    <div class="management-page">
                        <div class="management-content">
                            <div class="management-header">
                                <h2>SSC Events & Project Proposals</h2>
                            </div>

                            <!-- Main Section Tabs -->
                            <div class="main-tabs-container">
                                <a href="#events-section" class="main-tab-link active" data-main-tab="events-section" onclick="switchMainTab('events-section', event)">
                                    <i class="fa-solid fa-calendar-days"></i> Announcements
                                </a>
                                <a href="#proposals-section" class="main-tab-link" data-main-tab="proposals-section" onclick="switchMainTab('proposals-section', event)">
                                    <i class="fa-solid fa-lightbulb"></i> Project Proposals
                                </a>
                                <a href="#feedback-section" class="main-tab-link" data-main-tab="feedback-section" onclick="switchMainTab('feedback-section', event)">
                                    <i class="fa-solid fa-comments"></i> Feedback & Ratings
                                </a>
                            </div>

                            <!-- Events Section -->
                            <div id="events-section" class="main-tab-content active">
                                <div class="section-header" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                                    <h3>Announcements</h3>
                                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                                        <button class="btn-add" onclick="showEventModal()">
                                            <i class="fa-solid fa-plus"></i> Add Event
                                        </button>
                                        <button class="btn-add" onclick="showAnnouncementModal()">
                                            <i class="fa-solid fa-bullhorn"></i> Add Announcement
                                        </button>
                                    </div>
                                </div>

                            <!-- Event Tabs -->
                            <div class="inventory-tab-nav clinic-tabs">
                                <button type="button" class="clinic-tab active" onclick="switchTab('all-events', event)">
                                    <i class="fa-solid fa-list"></i> All Events
                                    <span class="tab-badge" id="badge-all">0</span>
                                </button>
                                <button type="button" class="clinic-tab" onclick="switchTab('announcements', event)">
                                    <i class="fa-solid fa-bullhorn"></i> Announcements
                                    <span class="tab-badge" id="badge-announcements">0</span>
                                </button>
                                <button type="button" class="clinic-tab" onclick="switchTab('upcoming', event)">
                                    <i class="fa-solid fa-calendar-check"></i> Upcoming
                                    <span class="tab-badge" id="badge-upcoming">0</span>
                                </button>
                                <button type="button" class="clinic-tab" onclick="switchTab('completed', event)">
                                    <i class="fa-solid fa-circle-check"></i> Completed
                                    <span class="tab-badge" id="badge-completed">0</span>
                                </button>
                            </div>

                            <!-- Events List -->
                            <div id="all-events" class="inventory-tab-content active">
                                <div id="events-all" class="events-list">
                                    <!-- Events will be loaded here -->
                                </div>
                            </div>

                            <div id="announcements" class="inventory-tab-content">
                                <div id="events-announcements" class="events-list">
                                    <!-- Announcements will be loaded here -->
                                </div>
                            </div>

                            <div id="upcoming" class="inventory-tab-content">
                                <div id="events-upcoming" class="events-list">
                                    <!-- Upcoming events will be loaded here -->
                                </div>
                            </div>

                            <div id="completed" class="inventory-tab-content">
                                <div id="events-completed" class="events-list">
                                    <!-- Completed events will be loaded here -->
                                </div>
                            </div>
                            </div>

                            <!-- Proposals Section -->
                            <div id="proposals-section" class="main-tab-content">
                                <div class="section-header">
                                    <h3>Project Proposals</h3>
                                    <button class="btn-add" onclick="showInitiativeModal()">
                                        <i class="fa-solid fa-plus"></i> Add Proposal
                                    </button>
                                </div>

                                <!-- Proposals Tabs -->
                                <div class="inventory-tab-nav clinic-tabs">
                                    <button type="button" class="clinic-tab active" onclick="switchTab('planning', event)">
                                        <i class="fa-solid fa-clock"></i> Pending Approval
                                        <span class="tab-badge" id="badge-planning">0</span>
                                    </button>
                                    <button type="button" class="clinic-tab" onclick="switchTab('active', event)">
                                        <i class="fa-solid fa-check-circle"></i> Approved
                                        <span class="tab-badge" id="badge-active">0</span>
                                    </button>
                                    <button type="button" class="clinic-tab" onclick="switchTab('completed-proposals', event)">
                                        <i class="fa-solid fa-circle-check"></i> Completed
                                        <span class="tab-badge" id="badge-completed-proposals">0</span>
                                    </button>
                                    <button type="button" class="clinic-tab" onclick="switchTab('cancelled-proposals', event)">
                                        <i class="fa-solid fa-times-circle"></i> Rejected
                                        <span class="tab-badge" id="badge-cancelled-proposals">0</span>
                                    </button>
                                </div>

                                <!-- Proposals Content -->
                                <div id="planning" class="inventory-tab-content active">
                                    <div id="initiatives-planning" class="initiatives-grid">
                                        <!-- Planning proposals will be loaded here -->
                                    </div>
                                </div>

                                <div id="active" class="inventory-tab-content">
                                    <div id="initiatives-active" class="initiatives-grid">
                                        <!-- Active proposals will be loaded here -->
                                    </div>
                                </div>

                                <div id="completed-proposals" class="inventory-tab-content">
                                    <div id="initiatives-completed" class="initiatives-grid">
                                        <!-- Completed proposals will be loaded here -->
                                    </div>
                                </div>

                                <div id="cancelled-proposals" class="inventory-tab-content">
                                    <div id="initiatives-cancelled" class="initiatives-grid">
                                        <!-- Cancelled proposals will be loaded here -->
                                    </div>
                                </div>
                            </div>

                            <!-- Feedback Section -->
                            <div id="feedback-section" class="main-tab-content">
                                <div class="section-header">
                                    <h3>Student Feedback & Ratings</h3>
                                </div>

                                <!-- Feedback Statistics -->
                                <div class="feedback-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                                    <div style="background: linear-gradient(135deg, #68151c, #a24a49); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(104,21,28,0.16);">
                                        <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 8px;" id="feedback-avg-rating">0</div>
                                        <div style="font-size: 0.95rem; opacity: 0.9;">Average Rating</div>
                                        <div style="font-size: 1.5rem; margin-top: 10px; color: #fbbf24;" id="feedback-stars-display">☆☆☆☆☆</div>
                                    </div>
                                    <div style="background: linear-gradient(135deg, #27ae60, #229954); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                        <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 8px;" id="feedback-total-count">0</div>
                                        <div style="font-size: 0.95rem; opacity: 0.9;">Total Feedback Received</div>
                                        <div style="font-size: 0.85rem; margin-top: 10px; opacity: 0.8;">From students</div>
                                    </div>
                                    <div style="background: linear-gradient(135deg, #e67e22, #d35400); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                        <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 8px;" id="feedback-excellent-count">0</div>
                                        <div style="font-size: 0.95rem; opacity: 0.9;">Excellent (5 Stars)</div>
                                        <div style="font-size: 0.85rem; margin-top: 10px; opacity: 0.8;">Positive feedback</div>
                                    </div>
                                </div>

                                <!-- Feedback Table -->
                                <div style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e9ecef; overflow-x: auto;">
                                    <h4>Recent Feedback from Students</h4>
                                    <table id="manage-feedback-table" style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: #f8f9fa; border-bottom: 2px solid #e9ecef;">
                                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #2c3e50;">Student Name</th>
                                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #2c3e50;">Email</th>
                                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #2c3e50;">Rating</th>
                                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #2c3e50;">Comments</th>
                                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #2c3e50;">Submitted</th>
                                            </tr>
                                        </thead>
                                        <tbody id="manage-feedback-tbody">
                                            <tr>
                                                <td colspan="5" style="padding: 20px; text-align: center; color: #6b7280;">Loading feedback...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div id="manage-feedback-empty" style="display: none; text-align: center; padding: 40px; color: #6b7280;">
                                        <i class="fa-solid fa-inbox" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px; display: block;"></i>
                                        <p style="font-size: 16px;">No feedback received yet. Students can submit feedback from their dashboard.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="eventModalTitle">Add New Event</h3>
                <button class="modal-close" onclick="closeModal('eventModal')">&times;</button>
            </div>
            <form id="eventForm" enctype="multipart/form-data">
                <div style="padding: 20px;">
                    <input type="hidden" id="eventId" name="id">
                    <div class="form-group">
                        <label for="eventTitle">Event Title *</label>
                        <input type="text" id="eventTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="eventDescription">Description</label>
                        <textarea id="eventDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="eventDate">Event Date</label>
                            <input type="date" id="eventDate" name="event_date">
                        </div>
                        <div class="form-group">
                            <label for="eventTime">Event Time</label>
                            <input type="time" id="eventTime" name="event_time">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="eventLocation">Location</label>
                            <input type="text" id="eventLocation" name="location">
                        </div>
                        <div class="form-group">
                            <label for="eventOrganizer">Organizer</label>
                            <input type="text" id="eventOrganizer" name="organizer">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="eventPhotos">Event Images</label>
                        <input type="file" id="eventPhotos" name="event_photos[]" multiple accept="image/png,image/jpeg,image/webp">
                        <small style="display:block; margin-top:8px; color:#64748b;">Optional: upload multiple images for the event gallery.</small>
                    </div>
                    <div class="form-group" id="selectedPhotosPreview" style="display:none;">
                        <label>Selected Images</label>
                        <div id="selectedPhotosContainer" class="image-preview-list"></div>
                    </div>
                    <div class="form-group" id="currentPhotosPreview" style="display:none;">
                        <label>Current Images</label>
                        <div id="currentPhotosContainer" class="image-preview-list"></div>
                        <div id="currentPhotoRemovalInputs"></div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('eventModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Announcement</h3>
                <button class="modal-close" onclick="closeModal('announcementModal')">&times;</button>
            </div>
            <form id="announcementForm" enctype="multipart/form-data">
                <div style="padding: 20px;">
                    <input type="hidden" id="announcementDate" name="event_date">
                    <div class="form-group">
                        <label for="announcementTitle">Announcement Title *</label>
                        <input type="text" id="announcementTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="announcementDescription">Description</label>
                        <textarea id="announcementDescription" name="description" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="announcementImages">Announcement Images</label>
                        <input type="file" id="announcementImages" name="event_photos[]" multiple accept="image/png,image/jpeg,image/webp,image/gif">
                        <small style="display:block; margin-top:8px; color:#64748b;">Upload announcement images only.</small>
                    </div>
                    <div class="form-group" id="announcementSelectedPhotosPreview" style="display:none;">
                        <label>Selected Images</label>
                        <div id="announcementSelectedPhotosContainer" class="image-preview-list"></div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('announcementModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Proposal Modal -->
    <div id="initiativeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="initiativeModalTitle">Add Project Proposal</h3>
                <button class="modal-close" onclick="closeModal('initiativeModal')">&times;</button>
            </div>
            <form id="initiativeForm">
                <div style="padding: 20px;">
                    <input type="hidden" id="initiativeId" name="id">
                    <div class="form-group">
                        <label for="initiativeTitle">Title *</label>
                        <input type="text" id="initiativeTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="initiativeCategory">Category</label>
                        <input type="text" id="initiativeCategory" name="category" placeholder="e.g., Academic, Social, Environmental">
                    </div>
                    <div class="form-group">
                        <label for="initiativeDescription">Description *</label>
                        <textarea id="initiativeDescription" name="description" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="initiativeStatus">Status</label>
                        <select id="initiativeStatus" name="status">
                            <option value="planning">Pending Approval</option>
                            <option value="active">Approved</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('initiativeModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Proposal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notification System -->
    <div id="notification-container" class="notification-container"></div>

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
    <script>
        // Main Tab Switching
        document.querySelectorAll('.main-tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const mainTabName = this.dataset.mainTab;

                // Update active main tab link
                document.querySelectorAll('.main-tab-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');

                // Show active main tab content
                document.querySelectorAll('.main-tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(mainTabName).classList.add('active');
            });
        });

        // Event Tab Switching
        document.querySelectorAll('.event-tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tabName = this.dataset.tab;

                // Update active event tab link
                document.querySelectorAll('.event-tab-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');

                // Show active event tab content
                document.querySelectorAll('.event-tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabName).classList.add('active');
            });
        });



        // Notification System
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;

            container.appendChild(notification);

            setTimeout(() => {
                container.removeChild(notification);
            }, 5000);
        }

        // Modal Functions
        function showEventModal(eventId = null) {
            const modal = document.getElementById('eventModal');
            const form = document.getElementById('eventForm');
            const title = document.getElementById('eventModalTitle');
            const photoInput = document.getElementById('eventPhotos');

            photoInput.value = '';
            handleEventPhotosChange();
            clearCurrentPhotoRemovals();

            if (eventId) {
                title.textContent = 'Edit Event';
                const event = (window.sscEvents || []).find(evt => Number(evt.id) === Number(eventId));
                if (event) {
                    document.getElementById('eventId').value = event.id;
                    document.getElementById('eventTitle').value = event.title || '';
                    document.getElementById('eventDescription').value = event.description || '';
                    document.getElementById('eventDate').value = event.event_date || '';
                    document.getElementById('eventTime').value = event.event_time || '';
                    document.getElementById('eventLocation').value = event.location || '';
                    document.getElementById('eventOrganizer').value = event.organizer || '';
                    renderCurrentPhotos(event.photos || []);
                } else {
                    form.reset();
                    document.getElementById('eventId').value = eventId;
                    renderCurrentPhotos([]);
                }
            } else {
                title.textContent = 'Add New Event';
                form.reset();
                document.getElementById('eventId').value = '';
                renderCurrentPhotos([]);
            }

            modal.classList.add('show');
        }

        function showAnnouncementModal() {
            const modal = document.getElementById('announcementModal');
            const form = document.getElementById('announcementForm');
            const announcementDate = document.getElementById('announcementDate');
            const today = new Date();
            const formattedDate = today.toISOString().split('T')[0];

            form.reset();
            announcementDate.value = formattedDate;
            document.getElementById('announcementImages').value = '';
            clearAnnouncementModalPhotoSelection();
            modal.classList.add('show');
        }

        async function handleAnnouncementSubmit(event) {
            event.preventDefault();

            const form = document.getElementById('announcementForm');
            const formData = new FormData(form);
            
            // Get the title value, default to "Announcement" if empty
            const title = document.getElementById('announcementTitle').value.trim();
            const description = document.getElementById('announcementDescription').value.trim();
            
            console.log('Announcement submission started:', { title, description });
            
            // Clear and set the proper values
            formData.set('title', title || 'Announcement');
            formData.set('description', description);
            formData.set('event_type', 'others'); // Mark as announcement
            
            // Set event_date to today if not already set
            if (!formData.get('event_date')) {
                const today = new Date().toISOString().split('T')[0];
                formData.set('event_date', today);
            }
            
            console.log('FormData prepared with:', {
                title: formData.get('title'),
                description: formData.get('description'),
                event_type: formData.get('event_type'),
                event_date: formData.get('event_date')
            });
            
            // Ensure these fields are set for announcements
            if (!formData.has('event_time') || formData.get('event_time') === '') {
                formData.delete('event_time');
            }
            if (!formData.has('location') || formData.get('location') === '') {
                formData.delete('location');
            }
            if (!formData.has('organizer') || formData.get('organizer') === '') {
                formData.delete('organizer');
            }

            try {
                const response = await fetch('ssc_api.php?action=save_event', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                console.log('API Response:', result);
                if (result.success) {
                    showNotification('Announcement saved successfully!', 'success');
                    closeModal('announcementModal');
                    await loadAllEvents();
                    console.log('Announcement reloading events...');
                } else {
                    showNotification(result.message || 'Error saving announcement', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error saving announcement', 'error');
            }
        }

        function handleEventPhotosChange() {
            const input = document.getElementById('eventPhotos');
            const previewSection = document.getElementById('selectedPhotosPreview');
            const previewContainer = document.getElementById('selectedPhotosContainer');
            const files = Array.from(input.files || []);

            if (files.length === 0) {
                previewSection.style.display = 'none';
                previewContainer.innerHTML = '';
                return;
            }

            previewSection.style.display = 'block';
            previewContainer.innerHTML = '';

            files.forEach((file, index) => {
                const fileReader = new FileReader();
                const card = document.createElement('div');
                card.className = 'image-preview-card';
                card.draggable = true;
                card.dataset.index = index;

                const img = document.createElement('img');
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'image-preview-remove';
                removeBtn.textContent = '×';
                removeBtn.title = 'Remove selected image';
                removeBtn.addEventListener('click', () => removeSelectedImage(index));

                card.appendChild(img);
                card.appendChild(removeBtn);
                previewContainer.appendChild(card);
                attachDragAndDropReordering(card, previewContainer, input, handleEventPhotosChange);

                fileReader.onload = (e) => {
                    img.src = e.target.result;
                };
                fileReader.readAsDataURL(file);
            });
        }

        function removeSelectedImage(fileIndex) {
            const input = document.getElementById('eventPhotos');
            const dataTransfer = new DataTransfer();
            const files = Array.from(input.files || []);

            files.forEach((file, index) => {
                if (index !== fileIndex) {
                    dataTransfer.items.add(file);
                }
            });

            input.files = dataTransfer.files;
            handleEventPhotosChange();
        }

        function attachDragAndDropReordering(card, previewContainer, input, onReorder) {
            card.addEventListener('dragstart', (e) => {
                e.dataTransfer.effectAllowed = 'move';
                card.classList.add('dragging');
                e.dataTransfer.setData('text/plain', card.dataset.index);
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
            });

            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                card.classList.add('drag-over');
            });

            card.addEventListener('dragleave', () => {
                card.classList.remove('drag-over');
            });

            card.addEventListener('drop', (e) => {
                e.preventDefault();
                card.classList.remove('drag-over');
                const fromIndex = parseInt(e.dataTransfer.getData('text/plain'), 10);
                const toIndex = parseInt(card.dataset.index, 10);
                if (!Number.isNaN(fromIndex) && fromIndex !== toIndex) {
                    reorderFilesInInput(input, fromIndex, toIndex);
                    if (typeof onReorder === 'function') {
                        onReorder();
                    }
                }
            });
        }

        function reorderFilesInInput(input, fromIndex, toIndex) {
            const files = Array.from(input.files || []);
            if (fromIndex < 0 || fromIndex >= files.length || toIndex < 0 || toIndex >= files.length) {
                return;
            }

            const [movedFile] = files.splice(fromIndex, 1);
            files.splice(toIndex, 0, movedFile);
            const dataTransfer = new DataTransfer();
            files.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
        }

        function handleAnnouncementModalPhotosChange() {
            const input = document.getElementById('announcementImages');
            const previewSection = document.getElementById('announcementSelectedPhotosPreview');
            const previewContainer = document.getElementById('announcementSelectedPhotosContainer');
            const files = Array.from(input.files || []);

            if (files.length === 0) {
                previewSection.style.display = 'none';
                previewContainer.innerHTML = '';
                return;
            }

            previewSection.style.display = 'block';
            previewContainer.innerHTML = '';

            files.forEach((file, index) => {
                const fileReader = new FileReader();
                const card = document.createElement('div');
                card.className = 'image-preview-card';
                card.draggable = true;
                card.dataset.index = index;

                const img = document.createElement('img');
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'image-preview-remove';
                removeBtn.textContent = '×';
                removeBtn.title = 'Remove selected image';
                removeBtn.addEventListener('click', () => removeAnnouncementSelectedImage(index));

                card.appendChild(img);
                card.appendChild(removeBtn);
                previewContainer.appendChild(card);
                attachDragAndDropReordering(card, previewContainer, input, handleAnnouncementModalPhotosChange);

                fileReader.onload = (e) => {
                    img.src = e.target.result;
                };
                fileReader.readAsDataURL(file);
            });
        }

        function removeAnnouncementSelectedImage(fileIndex) {
            const input = document.getElementById('announcementImages');
            const dataTransfer = new DataTransfer();
            const files = Array.from(input.files || []);

            files.forEach((file, index) => {
                if (index !== fileIndex) {
                    dataTransfer.items.add(file);
                }
            });

            input.files = dataTransfer.files;
            handleAnnouncementModalPhotosChange();
        }

        function clearAnnouncementModalPhotoSelection() {
            const previewSection = document.getElementById('announcementSelectedPhotosPreview');
            const previewContainer = document.getElementById('announcementSelectedPhotosContainer');
            previewSection.style.display = 'none';
            previewContainer.innerHTML = '';
        }

        function resolveEventImageUrl(photoUrl) {
            if (!photoUrl) {
                return '';
            }
            if (photoUrl.startsWith('http://') || photoUrl.startsWith('https://') || photoUrl.startsWith('/')) {
                return photoUrl;
            }
            return '../' + photoUrl.replace(/^\/*/, '');
        }

        function renderCurrentPhotos(photos) {
            const previewSection = document.getElementById('currentPhotosPreview');
            const container = document.getElementById('currentPhotosContainer');
            const removalInputs = document.getElementById('currentPhotoRemovalInputs');
            container.innerHTML = '';
            removalInputs.innerHTML = '';

            if (!photos || photos.length === 0) {
                previewSection.style.display = 'none';
                return;
            }

            previewSection.style.display = 'block';

            photos.forEach((photo, index) => {
                const photoUrl = typeof photo === 'string' ? photo : (photo.url || photo.photo_url || '');
                const card = document.createElement('div');
                card.className = 'image-preview-card';

                const img = document.createElement('img');
                img.src = resolveEventImageUrl(photoUrl);
                img.alt = 'Current event image';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'image-preview-remove';
                removeBtn.textContent = '×';
                removeBtn.title = 'Remove current image';
                removeBtn.addEventListener('click', () => removeCurrentPhoto(photoUrl, index));

                card.appendChild(img);
                card.appendChild(removeBtn);
                container.appendChild(card);
            });
        }

        function removeCurrentPhoto(photoUrl, index) {
            const removalInputs = document.getElementById('currentPhotoRemovalInputs');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'removed_photos[]';
            input.value = photoUrl;
            removalInputs.appendChild(input);

            const currentPhotosPreview = document.getElementById('currentPhotosPreview');
            const cards = currentPhotosPreview.querySelectorAll('.image-preview-card');
            if (cards[index]) {
                cards[index].remove();
            }
            if (!currentPhotosPreview.querySelector('.image-preview-card')) {
                currentPhotosPreview.style.display = 'none';
            }
        }

        function clearCurrentPhotoRemovals() {
            const removalInputs = document.getElementById('currentPhotoRemovalInputs');
            removalInputs.innerHTML = '';
        }

        function showInitiativeModal(initiativeId = null) {
            const modal = document.getElementById('initiativeModal');
            const form = document.getElementById('initiativeForm');
            const title = document.getElementById('initiativeModalTitle');

            if (initiativeId) {
                title.textContent = 'Edit Project Proposal';

                // Fetch initiative data
                fetch('ssc_api.php?action=get_initiatives')
                    .then(response => response.json())
                    .then(data => {
                        const initiative = data.initiatives.find(i => i.id == initiativeId);
                        if (initiative) {
                            document.getElementById('initiativeId').value = initiative.id;
                            document.getElementById('initiativeTitle').value = initiative.title;
                            document.getElementById('initiativeCategory').value = initiative.category || '';
                            document.getElementById('initiativeDescription').value = initiative.description || '';
                            document.getElementById('initiativeStatus').value = initiative.status;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching proposal:', error);
                        showNotification('Error loading proposal data', 'error');
                    });
            } else {
                title.textContent = 'Add Project Proposal';
                form.reset();
                document.getElementById('initiativeId').value = '';
            }

            modal.classList.add('show');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
        }

        // Events Functions
        async function loadAllEvents() {
            try {
                const response = await fetch('ssc_api.php?action=get_events');
                const data = await response.json();
                window.sscEvents = normalizeEvents(data.events || []);
                displayEventsByStatus(window.sscEvents);
            } catch (error) {
                console.error('Error loading events:', error);
                showNotification('Error loading events', 'error');
            }
        }

        function getEventStatus(event) {
            const now = new Date();
            let eventDateTime;
            if (event.event_date) {
                const [year, month, day] = event.event_date.split('-').map(Number);
                if (event.event_time) {
                    const [hour, minute] = event.event_time.split(':').map(Number);
                    eventDateTime = new Date(year, month - 1, day, hour, minute, 0, 0);
                } else {
                    eventDateTime = new Date(year, month - 1, day, 23, 59, 59, 999);
                }
            } else {
                return event.status || 'upcoming';
            }

            if (event.status === 'completed') {
                return 'completed';
            }
            if (eventDateTime <= now) {
                return 'completed';
            }
            return 'upcoming';
        }

        function normalizeEvents(events) {
            return events.map(event => ({ ...event, status: getEventStatus(event) }));
        }

        function displayEventsByStatus(events) {
            const normalized = normalizeEvents(events);
            
            // Separate announcements from events
            const announcements = normalized.filter(e => e.event_type === 'others');
            const regularEvents = normalized.filter(e => e.event_type !== 'others');

            // Update badges
            document.getElementById('badge-all').textContent = regularEvents.length;
            document.getElementById('badge-announcements').textContent = announcements.length;
            document.getElementById('badge-upcoming').textContent = regularEvents.filter(e => e.status === 'upcoming').length;
            document.getElementById('badge-completed').textContent = regularEvents.filter(e => e.status === 'completed').length;

            // Display events in each tab
            displayEvents(regularEvents, 'all-events', 'events-all');
            displayEvents(announcements, 'announcements', 'events-announcements');
            displayEvents(regularEvents.filter(e => e.status === 'upcoming'), 'upcoming', 'events-upcoming');
            displayEvents(regularEvents.filter(e => e.status === 'completed'), 'completed', 'events-completed');
        }

        function displayEvents(events, tabId, containerId) {
            const container = document.getElementById(containerId);

            if (events.length === 0) {
                let emptyMessage = 'No events found';
                if (tabId === 'announcements') emptyMessage = 'No announcements';
                else if (tabId === 'upcoming') emptyMessage = 'No upcoming events';
                else if (tabId === 'completed') emptyMessage = 'No completed events';

                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-calendar-days"></i>
                        <h3>${emptyMessage}</h3>
                        <p>Events will appear here when available.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = events.map(event => `
                <div class="event-item">
                    <div class="event-item-header">
                        <div>
                            <h3 class="event-item-title">${event.title}</h3>
                            <p class="event-item-details">
                                <span><i class="fa-solid fa-calendar"></i> ${new Date(event.event_date).toLocaleDateString()}</span>
                                ${event.event_time ? `<span><i class="fa-solid fa-clock"></i> ${event.event_time}</span>` : ''}
                                ${event.location ? `<span><i class="fa-solid fa-map-marker-alt"></i> ${event.location}</span>` : ''}
                            </p>
                        </div>
                        <div class="event-item-actions">
                            <button class="btn-sm btn-edit" onclick="showEventModal(${event.id})">
                                <i class="fa-solid fa-edit"></i> Edit
                            </button>
                            <button class="btn-sm btn-delete" onclick="deleteEvent(${event.id})">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    ${event.description ? `<p class="event-item-details">${event.description}</p>` : ''}
                    <span class="event-status-badge status-${event.status}">${event.status}</span>
                </div>
            `).join('');
        }

        // Proposals Functions
        async function loadAllInitiatives() {
            try {
                const response = await fetch('ssc_api.php?action=get_initiatives');
                const data = await response.json();
                displayInitiativesByStatus(data.initiatives || []);
            } catch (error) {
                console.error('Error loading proposals:', error);
                showNotification('Error loading proposals', 'error');
            }
        }

        function displayInitiativesByStatus(initiatives) {
            // Update badges
            document.getElementById('badge-planning').textContent = initiatives.filter(i => i.status === 'planning').length;
            document.getElementById('badge-active').textContent = initiatives.filter(i => i.status === 'active').length;
            document.getElementById('badge-completed-proposals').textContent = initiatives.filter(i => i.status === 'completed').length;
            document.getElementById('badge-cancelled-proposals').textContent = initiatives.filter(i => i.status === 'cancelled').length;

            // Display proposals in each tab
            displayInitiatives(initiatives.filter(i => i.status === 'planning'), 'planning', 'initiatives-planning');
            displayInitiatives(initiatives.filter(i => i.status === 'active'), 'active', 'initiatives-active');
            displayInitiatives(initiatives.filter(i => i.status === 'completed'), 'completed-proposals', 'initiatives-completed');
            displayInitiatives(initiatives.filter(i => i.status === 'cancelled'), 'cancelled-proposals', 'initiatives-cancelled');
        }

        function displayInitiatives(initiatives, status, containerId) {
            const container = document.getElementById(containerId);

            if (initiatives.length === 0) {
                let emptyMessage, emptyText;
                if (status === 'planning') {
                    emptyMessage = 'No pending proposals';
                    emptyText = 'Proposals awaiting admin approval will appear here.';
                } else if (status === 'active') {
                    emptyMessage = 'No approved proposals';
                    emptyText = 'Approved proposals will appear here.';
                } else if (status === 'completed-proposals') {
                    emptyMessage = 'No completed proposals';
                    emptyText = 'Completed proposals will appear here.';
                } else {
                    emptyMessage = 'No rejected proposals';
                    emptyText = 'Rejected proposals will appear here.';
                }
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-inbox"></i>
                        <h3>${emptyMessage}</h3>
                        <p>${emptyText}</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = initiatives.map(initiative => `
                <div class="initiative-item">
                    <div class="initiative-header">
                        <h3 class="initiative-title">${initiative.title}</h3>
                        ${initiative.category ? `<p class="initiative-category"><i class="fa-solid fa-tag"></i> ${initiative.category}</p>` : ''}
                    </div>
                    ${initiative.description ? `<p class="initiative-description">${initiative.description}</p>` : ''}

                    <span class="initiative-status status-${initiative.status}">${initiative.status}</span>

                    <div class="initiative-actions">
                        <button class="btn-sm btn-edit" onclick="showInitiativeModal(${initiative.id})">
                            <i class="fa-solid fa-edit"></i> Edit
                        </button>
                        <button class="btn-sm btn-delete" onclick="deleteInitiative(${initiative.id})">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Form Submissions
        async function handleEventSubmit(event) {
            event.preventDefault();

            const form = document.getElementById('eventForm');
            const formData = new FormData(form);

            try {
                const response = await fetch('ssc_api.php?action=save_event', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('Event saved successfully!', 'success');
                    closeModal('eventModal');
                    loadAllEvents();
                } else {
                    showNotification(result.message || 'Error saving event', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error saving event', 'error');
            }
        }

        async function handleInitiativeSubmit(event) {
            event.preventDefault();

            const form = document.getElementById('initiativeForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            try {
                const response = await fetch('ssc_api.php?action=save_initiative', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('Proposal saved successfully!', 'success');
                    closeModal('initiativeModal');
                    loadAllInitiatives();
                } else {
                    showNotification(result.message || 'Error saving proposal', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error saving proposal', 'error');
            }
        }

        // Delete Functions
        async function deleteEvent(eventId) {
            if (!confirm('Are you sure you want to delete this event?')) {
                return;
            }

            try {
                const response = await fetch('ssc_api.php?action=delete_event', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: eventId })
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('Event deleted successfully!', 'success');
                    loadAllEvents();
                } else {
                    showNotification(result.message || 'Error deleting event', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error deleting event', 'error');
            }
        }

        async function deleteInitiative(initiativeId) {
            if (!confirm('Are you sure you want to delete this project proposal?')) {
                return;
            }

            try {
                const response = await fetch('ssc_api.php?action=delete_initiative', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: initiativeId })
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('Proposal deleted successfully!', 'success');
                    loadAllInitiatives();
                } else {
                    showNotification(result.message || 'Error deleting proposal', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error deleting proposal', 'error');
            }
        }

        // Page Initialization
        document.addEventListener('DOMContentLoaded', function() {
        // Switch Event Tab Function - matches library_inventory.php pattern
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
        };

        // Main Tab Switching Function
        window.switchMainTab = function(sectionId, event) {
            if (event) {
                event.preventDefault();
            }
            
            // Update active main tab link
            document.querySelectorAll('.main-tab-link').forEach(link => {
                link.classList.remove('active');
            });
            event.target.closest('.main-tab-link').classList.add('active');
            
            // Hide all main tab content
            document.querySelectorAll('.main-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Show active main tab content
            const activeSection = document.getElementById(sectionId);
            if (activeSection) {
                activeSection.classList.add('active');
                
                // Automatically activate the first sub-tab in this section
                const firstTab = activeSection.querySelector('.clinic-tab');
                const tabsContainer = activeSection.querySelector('.inventory-tab-nav.clinic-tabs');
                
                if (firstTab && tabsContainer) {
                    // Remove active class from all tabs in this section
                    tabsContainer.querySelectorAll('.clinic-tab').forEach(tab => {
                        tab.classList.remove('active');
                    });
                    
                    // Add active class to first tab
                    firstTab.classList.add('active');
                    
                    // Hide all tab contents in this section
                    const tabContents = activeSection.querySelectorAll('.inventory-tab-content');
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                    });
                    
                    // Get the tab ID from the first tab's onclick attribute or data attribute
                    const firstTabId = firstTab.getAttribute('onclick')?.match(/switchTab\('([^']+)'/)?.[1];
                    if (firstTabId) {
                        const firstTabContent = activeSection.querySelector('#' + firstTabId);
                        if (firstTabContent) {
                            firstTabContent.classList.add('active');
                        }
                    }
                }
            }
        };

            loadAllEvents();
            loadAllInitiatives();
            document.getElementById('eventForm').addEventListener('submit', handleEventSubmit);
            document.getElementById('announcementForm').addEventListener('submit', handleAnnouncementSubmit);
            document.getElementById('initiativeForm').addEventListener('submit', handleInitiativeSubmit);
            document.getElementById('eventPhotos').addEventListener('change', handleEventPhotosChange);
            document.getElementById('announcementImages').addEventListener('change', handleAnnouncementModalPhotosChange);
            
            // Attach switchMainTab function to main tab links
            document.querySelectorAll('.main-tab-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    window.switchMainTab(this.dataset.mainTab, e);
                });
            });

            // Load feedback when page loads
            loadAllManageSscFeedback();
            loadSscFeedbackManageStats();

            // Reload feedback when Feedback tab is clicked
            const feedbackTabLink = document.querySelector('a[data-main-tab="feedback-section"]');
            if (feedbackTabLink) {
                feedbackTabLink.addEventListener('click', function() {
                    setTimeout(() => {
                        loadAllManageSscFeedback();
                        loadSscFeedbackManageStats();
                    }, 100);
                });
            }

            // Auto-refresh feedback every 5 seconds
            setInterval(() => {
                const feedbackSection = document.getElementById('feedback-section');
                if (feedbackSection && feedbackSection.classList.contains('active')) {
                    loadAllManageSscFeedback();
                    loadSscFeedbackManageStats();
                }
            }, 5000);
        });

        // SSC Feedback Management Functions
        async function loadAllManageSscFeedback() {
            try {
                const response = await fetch('ssc_api.php?action=get_all_feedback', {
                    method: 'GET',
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data && result.data.length > 0) {
                    const tbody = document.getElementById('manage-feedback-tbody');
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
                        const ratingTexts = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                        const ratingLabel = ratingTexts[feedback.rating] || '';

                        const row = `
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td data-label="Student Name" style="padding: 12px; color: #111827;">${feedback.full_name}</td>
                                <td data-label="Email" style="padding: 12px; color: #6b7280; font-size: 0.9em;">${feedback.email}</td>
                                <td data-label="Rating" style="padding: 12px; text-align: center; color: #fbbf24; font-size: 16px;" title="${ratingLabel}">${stars}</td>
                                <td data-label="Comments" style="padding: 12px; color: #374151; max-width: 400px; word-break: break-word;">${feedback.comments || '-'}</td>
                                <td data-label="Submitted" style="padding: 12px; color: #6b7280; font-size: 0.9em;">${date}</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });

                    document.getElementById('manage-feedback-empty').style.display = 'none';
                } else {
                    document.getElementById('manage-feedback-tbody').innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #6b7280;">No feedback received yet.</td></tr>';
                    document.getElementById('manage-feedback-empty').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading feedback:', error);
                document.getElementById('manage-feedback-tbody').innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #991b1b;">Error loading feedback</td></tr>';
            }
        }

        async function loadSscFeedbackManageStats() {
            try {
                // Fetch all feedback to calculate stats
                const response = await fetch('ssc_api.php?action=get_all_feedback', {
                    method: 'GET',
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data) {
                    const feedbackData = result.data;
                    
                    // Calculate average rating
                    const avgRating = feedbackData.length > 0 
                        ? feedbackData.reduce((sum, f) => sum + f.rating, 0) / feedbackData.length 
                        : 0;
                    
                    // Count excellent (5-star) feedback
                    const excellentCount = feedbackData.filter(f => f.rating === 5).length;
                    
                    // Update displays
                    document.getElementById('feedback-avg-rating').textContent = avgRating.toFixed(1);
                    document.getElementById('feedback-total-count').textContent = feedbackData.length;
                    document.getElementById('feedback-excellent-count').textContent = excellentCount;
                    
                    // Display stars based on average
                    const fullStars = Math.round(avgRating);
                    const starsDisplay = '★'.repeat(fullStars) + '☆'.repeat(5 - fullStars);
                    document.getElementById('feedback-stars-display').textContent = starsDisplay;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        /* Feedback mobile styles */
        (function addFeedbackResponsiveStyles(){
            const css = `
            @media (max-width: 720px) {
                #feedback-section .feedback-stats {
                    grid-template-columns: 1fr !important;
                    gap: 10px !important;
                }

                #feedback-section #manage-feedback-table {
                    width: 100% !important;
                    border: none !important;
                }

                #feedback-section #manage-feedback-table thead { display: none !important; }

                #feedback-section #manage-feedback-table tbody,
                #feedback-section #manage-feedback-table tr { display: block !important; width: 100% !important; margin-bottom: 12px !important; border: 1px solid #e9ecef !important; border-radius: 10px !important; background: #fff !important; box-shadow: 0 6px 20px rgba(0,0,0,0.04) !important; padding: 10px !important; }

                #feedback-section #manage-feedback-table td { display: block !important; width: 100% !important; padding: 6px 10px !important; box-sizing: border-box !important; border: none !important; }

                #feedback-section #manage-feedback-table td:before { content: attr(data-label) !important; display: block !important; font-size: 0.78rem !important; font-weight: 700 !important; color: #475569 !important; margin-bottom: 6px !important; text-transform: none !important; }

                #feedback-section #manage-feedback-table td[title] { white-space: normal !important; }
            }
            `;
            const style = document.createElement('style');
            style.appendChild(document.createTextNode(css));
            document.head.appendChild(style);
        })();

        /* --- Swipe handlers for main tabs and clinic sub-tabs (320px - 720px) --- */
        function enableMainTabSwipe() {
            const container = document.querySelector('.main-tabs-container');
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
                const tabs = Array.from(document.querySelectorAll('.main-tab-link'));
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

        function enableClinicTabSwipe() {
            const containers = Array.from(document.querySelectorAll('.inventory-tab-nav.clinic-tabs'));
            containers.forEach(container => {
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
            });
        }

        // Initialize swipe handlers and reinitialize on layout changes
        function initTabSwipes() {
            enableMainTabSwipe();
            enableClinicTabSwipe();
        }

        window.addEventListener('load', function() {
            initTabSwipes();
        });

        window.addEventListener('resize', function() {
            // no-op for now, handlers are tolerant; keep scroll snapping active
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