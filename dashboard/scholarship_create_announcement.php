<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if (!($user['role'] === 'admin' || ($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship'))) {
    header('Location: student_home.php');
    exit;
}

require_once __DIR__ . '/../AI CHAT BOT/chat_widget.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Announcements | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/scholar-header.css">
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
        /* Rich Text Editor Styles */
        .rich-text-editor {
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            min-height: 300px;
        }

        .editor-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
            align-items: center;
            overflow-x: auto;
            white-space: nowrap;
        }

        .toolbar-group {
            display: flex;
            gap: 2px;
            margin-right: 10px;
            flex-wrap: nowrap;
        }

        .toolbar-btn {
            background: white;
            border: 1px solid #ccc;
            padding: 6px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            color: #333;
            transition: all 0.2s ease;
        }

        .toolbar-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .toolbar-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .toolbar-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .editor-content {
            padding: 15px;
            min-height: 250px;
            outline: none;
            line-height: 1.6;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            overflow-y: auto;
        }

        .editor-content:empty:before {
            content: "Start typing your announcement content here...";
            color: #999;
            font-style: italic;
        }

        .color-picker, .font-size-select, .font-family-select {
            padding: 4px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: white;
        }

        .font-size-select {
            width: 60px;
        }

        .font-family-select {
            min-width: 140px;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        .topbar-divider { width: 1px; height: 24px; background: #e9ecef; margin: 0 12px; }
        .mic-button { background: none; border: none; cursor: pointer; color: #666; padding: 0 8px; }

        /* Tabbed Interface Styles */
        .content-panel {
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
        }

        .dashboard-intro-left {
            max-width: 900px;
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

        .tab-buttons {
            display: flex;
            background: white;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 0;
        }

        .tab-button {
            flex: 1;
            padding: 16px 24px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-button:hover {
            background: #f8f9fa;
            color: #3498db;
        }

        .tab-button.active {
            color: #3498db;
            border-bottom-color: #3498db;
            background: white;
        }

        .tab-content {
            background: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 24px;
            min-height: 400px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
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
            border: 2px solid #D4AF37;
        }
        .clinic-tab.active i {
            color: #D4AF37;
        }
        .clinic-tab.active:hover {
            background: #660000;
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

        @media (max-width: 720px) {
            .inventory-tab-nav.clinic-tabs {
                display: flex;
                flex-wrap: nowrap;
                overflow-x: auto;
                overflow-y: hidden;
                scroll-behavior: smooth;
                scroll-snap-type: x mandatory;
                gap: 8px;
                padding: 8px;
                margin: -8px 0 1.5rem -8px;
                width: calc(100% + 16px);
                -webkit-overflow-scrolling: touch;
            }

            .inventory-tab-nav.clinic-tabs::-webkit-scrollbar {
                height: 4px;
            }

            .inventory-tab-nav.clinic-tabs::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.05);
            }

            .inventory-tab-nav.clinic-tabs::-webkit-scrollbar-thumb {
                background: #800000;
                border-radius: 4px;
            }

            .clinic-tab {
                flex: 0 0 auto;
                min-width: 150px;
                padding: 0.8rem 1rem;
                white-space: nowrap;
                scroll-snap-align: start;
                scroll-snap-stop: always;
            }

            /* Feedback Tab Mobile Styles */
            #feedback {
                padding: 0 !important;
                width: 100% !important;
            }

            #feedback h2 {
                font-size: 1.2rem !important;
                margin: 0 0 8px 0 !important;
            }

            #feedback > p {
                font-size: 0.85rem !important;
                margin-bottom: 16px !important;
            }

            .inventory-card {
                padding: 12px !important;
                margin: 0 !important;
                background: white !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                overflow-x: hidden !important;
            }

            .borrow-table {
                width: 100% !important;
                display: block !important;
                overflow-x: hidden !important;
            }

            .borrow-table thead {
                display: none !important;
            }

            .borrow-table tbody {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .borrow-table tr {
                display: block !important;
                padding: 14px !important;
                background: #f8fafc !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                margin: 0 !important;
            }

            .borrow-table td {
                display: block !important;
                padding: 0 0 12px 0 !important;
                border: none !important;
                text-align: left !important;
                margin: 0 !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
            }

            .borrow-table td:last-child {
                padding-bottom: 0 !important;
            }

            .borrow-table td::before {
                content: attr(data-label) !important;
                display: block !important;
                font-weight: 700 !important;
                color: #0f172a !important;
                font-size: 0.8rem !important;
                margin-bottom: 4px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                opacity: 0.7 !important;
            }
            }

            .borrow-table td[data-label="Student Name"]::before {
                content: "Student Name" !important;
            }
            .borrow-table td[data-label="Validation ID"]::before {
                content: "Validation ID" !important;
            }
            .borrow-table td[data-label="Process Rating"]::before {
                content: "Process Rating" !important;
            }
            .borrow-table td[data-label="Support Rating"]::before {
                content: "Support Rating" !important;
            }
            .borrow-table td[data-label="Review"]::before {
                content: "Review" !important;
            }
            .borrow-table td[data-label="Date"]::before {
                content: "Date" !important;
            }

            .borrow-table td strong {
                color: #0369a1 !important;
            }

            /* View Announcements Tab Mobile Styles */
            #view {
                padding: 0 !important;
                width: 100% !important;
            }

            #view h2 {
                font-size: 1.2rem !important;
                margin: 0 0 8px 0 !important;
            }

            #view > p {
                font-size: 0.85rem !important;
                margin-bottom: 16px !important;
            }

            .announcements-container {
                margin-top: 16px !important;
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 14px !important;
            }

            .announcement-card {
                padding: 16px !important;
                gap: 12px !important;
                border-radius: 10px !important;
            }

            .announcement-header {
                gap: 12px !important;
                margin-bottom: 10px !important;
                flex-wrap: wrap !important;
            }

            .announcement-header > div {
                width: 100% !important;
            }

            .announcement-title {
                font-size: 1.1rem !important;
                margin: 0 !important;
            }

            .announcement-badge {
                font-size: 0.75rem !important;
                padding: 4px 8px !important;
            }

            .announcement-header button {
                padding: 6px 10px !important;
                font-size: 11px !important;
                width: 100% !important;
            }

            .announcement-meta {
                display: flex !important;
                flex-direction: column !important;
                gap: 6px !important;
                font-size: 0.8rem !important;
            }

            .announcement-meta-item {
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
            }

            .announcement-description {
                font-size: 0.9rem !important;
                line-height: 1.4 !important;
            }

            .announcement-text {
                margin: 0 0 8px 0 !important;
                font-size: 0.85rem !important;
            }

            .toggle-description {
                display: block !important;
                padding: 8px 12px !important;
                font-size: 0.8rem !important;
                width: 100% !important;
            }

            .announcement-images {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 8px !important;
                margin: 0 !important;
            }

            .announcement-images img {
                max-width: 100% !important;
                height: auto !important;
                border-radius: 6px !important;
                cursor: pointer !important;
            }
        }

        .announcements-container {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        @media (max-width: 980px) {
            .announcements-container {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 480px) {
            .announcements-container {
                grid-template-columns: 1fr !important;
            }
        }

        .announcement-card {
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #ffffff;
            border-radius: 12px;
            padding: 22px;
            border-bottom: 4px solid #6f42c1;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            min-height: 100%;
        }

        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .announcement-card.scholarship {
            border-bottom-color: #6f42c1;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 12px;
        }

        .announcement-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
            line-height: 1.2;
        }

        .announcement-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #6f42c1;
            background: #f5f1ff;
            white-space: nowrap;
        }

        .announcement-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 0.88rem;
            color: #666;
        }

        .announcement-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .announcement-description {
            color: #333;
            line-height: 1.7;
            margin-bottom: 12px;
        }

        .announcement-description p {
            margin: 0;
        }

        .announcement-text {
            color: #333;
            line-height: 1.7;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .announcement-text.expanded {
            max-height: none;
        }

        .toggle-description,
        .read-more-btn {
            margin-top: 8px;
            border: none;
            background: transparent;
            color: #6f42c1;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
        }

        .toggle-description:hover,
        .read-more-btn:hover {
            text-decoration: underline;
        }

        .announcement-images {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            width: 100%;
        }

        .announcement-images img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 12px;
            display: block;
            cursor: pointer;
        }

        .announcement-images-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 14px;
            cursor: pointer;
        }

        .announcement-images-grid .announcement-image-row {
            display: grid;
            gap: 10px;
        }

        .announcement-images-grid .announcement-image-row.single-row {
            grid-template-columns: 1fr;
        }

        .announcement-images-grid .announcement-image-row.top-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .announcement-images-grid .announcement-image-row.bottom-row.one-item {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            justify-items: center;
        }

        .announcement-images-grid .announcement-image-row.bottom-row.one-item .announcement-image-wrapper {
            grid-column: 2 / 3;
            width: 100%;
        }

        .announcement-images-grid .announcement-image-row.bottom-row.two-items {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .announcement-images-grid .announcement-image-row.bottom-row.three-items {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .announcement-image-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            aspect-ratio: 16 / 9;
            background: #f3f4f6;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: block;
            width: 100%;
            height: 100%;
            min-height: 80px;
            border: none;
            padding: 0;
            cursor: pointer;
        }

        .announcement-image-wrapper:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.16);
        }

        .announcement-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.35s ease;
        }

        .announcement-images-grid .announcement-image-row.top-row .announcement-image-wrapper {
            aspect-ratio: 16 / 9;
            height: auto;
        }

        .announcement-images-grid .announcement-image-row.bottom-row .announcement-image-wrapper {
            aspect-ratio: 1 / 1;
            height: auto;
        }

        .announcement-image-wrapper:hover img {
            transform: scale(1.05);
        }

        .announcement-image-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            color: white;
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            z-index: 3;
        }

        .image-preview-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }

        .image-preview-item {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 10px;
            background: #fff;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: stretch;
        }

        .image-preview-item img {
            width: 100%;
            height: 130px;
            object-fit: cover;
            border-radius: 8px;
            display: block;
        }

        .image-preview-item button {
            width: 100%;
            padding: 8px 10px;
            border: none;
            background: #d9534f;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .image-preview-item button:hover {
            background: #c9302c;
        }

        .announcement-deadline {
            display: inline-block;
            background: #f5f1ff;
            color: #6f42c1;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .announcement-card {
                flex-direction: column;
                gap: 15px;
            }
            .announcement-images {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .search-results {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            margin-top: 5px;
        }

        .search-result-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .requirements-container {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .requirement-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .requirement-item:last-child {
            margin-bottom: 0;
        }

        .requirement-checkbox {
            width: 18px;
            height: 18px;
        }

        .validation-history-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .validation-history-item:last-child {
            margin-bottom: 0;
        }

        .validation-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: #666;
        }

        .validation-requirements {
            margin-top: 10px;
        }

        .requirement-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .requirement-badge.validated {
            background: #d4edda;
            color: #155724;
        }

        .requirement-badge.not-validated {
            background: #f8d7da;
            color: #721c24;
        }

        /* Feedback Table Styles */
        .inventory-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }

        .borrow-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .borrow-table th,
        .borrow-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 13px;
        }

        .borrow-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #0f172a;
            text-align: left;
        }

        .borrow-table tbody tr:hover {
            background: #f8fafc;
        }

        .borrow-table td strong {
            color: #0369a1;
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
                    <p><?= htmlspecialchars($displayCourse . (!empty($displayYear) && $displayYear !== 'N/A' ? ' - ' . $displayYear : '')) ?></p>
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
                        <a href="scholarship_create_announcement.php" class="active" data-tooltip="Scholarship Announcements">
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
                <section class="dashboard-intro scholar-page-header">
                    <div class="scholar-header__content">
                        <span class="eyebrow">SCHOLARSHIP ANNOUNCEMENTS</span>
                        <h1>Manage Scholarship Announcements</h1>
                        <p class="scholar-header__subtitle">Create new announcements and view existing scholarship opportunities in one clean, organized view.</p>
                    </div>
                    <div class="scholar-header__art" aria-hidden="true"><i class="fa-solid fa-message chat-left"></i><i class="fa-solid fa-comment chat-top"></i><i class="fa-solid fa-address-card profile-card"></i><i class="fa-solid fa-heart heart-check"></i><i class="fa-solid fa-leaf scholar-leaf"></i><i class="fa-solid fa-circle scholar-dot"></i></div>
                </section>
                    <div class="management-page">
                        <div class="management-content">
                            <div class="management-header">
                                <h2>Scholarship Announcements</h2>
                            </div>
                        <div class="inventory-tab-nav clinic-tabs">
                            <button type="button" class="clinic-tab active" onclick="switchTab('create', event)">
                                <i class="fa-solid fa-pen-to-square"></i> Create Announcement
                            </button>
                            <button type="button" class="clinic-tab" onclick="switchTab('view', event)">
                                <i class="fa-solid fa-list"></i> View Announcements
                            </button>
                            <button type="button" class="clinic-tab" onclick="switchTab('validation', event)">
                                <i class="fa-solid fa-clipboard-check"></i> Document Validation
                            </button>
                            <button type="button" class="clinic-tab" onclick="switchTab('feedback', event)">
                                <i class="fa-solid fa-star"></i> Feedback
                            </button>
                        </div>

                        <div class="tab-content">
                            <!-- Create Announcement Tab -->
                            <div id="create" class="inventory-tab-content active">
                                <h2 style="margin: 0 0 8px;">Create New Announcement</h2>
                                <p style="color: #666; margin-bottom: 24px;">Publish a new scholarship announcement with requirements, images, and deadlines.</p>

                                <form id="announcementForm" enctype="multipart/form-data">
                                    <input type="hidden" id="announcementId" name="id">
                                    <div class="form-group">
                                        <label for="announcementTitle">Title *</label>
                                        <input type="text" id="announcementTitle" name="title" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="announcementContent">Content *</label>
                                        <div class="rich-text-editor" id="richTextEditor">
                                            <div class="editor-toolbar">
                                                <div class="toolbar-group">
                                                    <button type="button" class="toolbar-btn" onclick="formatText('bold')" title="Bold">
                                                        <i class="fa-solid fa-bold"></i>
                                                    </button>
                                                    <button type="button" class="toolbar-btn" onclick="formatText('italic')" title="Italic">
                                                        <i class="fa-solid fa-italic"></i>
                                                    </button>
                                                    <button type="button" class="toolbar-btn" onclick="formatText('underline')" title="Underline">
                                                        <i class="fa-solid fa-underline"></i>
                                                    </button>
                                                    <button type="button" class="toolbar-btn" onclick="formatText('strikeThrough')" title="Strikethrough">
                                                        <i class="fa-solid fa-strikethrough"></i>
                                                    </button>
                                                </div>

                                                <div class="toolbar-group">
                                                    <select class="font-family-select" onchange="formatText('fontName', this.value)" title="Font Family">
                                                        <option value="Arial" selected>Arial</option>
                                                        <option value="Tahoma">Tahoma</option>
                                                        <option value="Verdana">Verdana</option>
                                                        <option value="Georgia">Georgia</option>
                                                        <option value="Times New Roman">Times New Roman</option>
                                                        <option value="Courier New">Courier New</option>
                                                    </select>
                                                </div>

                                                <div class="toolbar-group">
                                                    <select class="font-size-select" onchange="formatText('fontSize', this.value)" title="Font Size">
                                                        <option value="1">8pt</option>
                                                        <option value="2">10pt</option>
                                                        <option value="3" selected>12pt</option>
                                                        <option value="4">14pt</option>
                                                        <option value="5">18pt</option>
                                                        <option value="6">24pt</option>
                                                        <option value="7">36pt</option>
                                                    </select>
                                                </div>

                                                <div class="toolbar-group">
                                                    <input type="color" class="color-picker" onchange="formatText('foreColor', this.value)" value="#000000" title="Text Color">
                                                    <input type="color" class="color-picker" onchange="formatText('hiliteColor', this.value)" value="#ffffff" title="Background Color">
                                                </div>

                                                <div class="toolbar-group">
                                                    <button type="button" class="toolbar-btn" onclick="formatText('justifyLeft')" title="Align Left">
                                                        <i class="fa-solid fa-align-left"></i>
                                                    </button>
                                                    <button type="button" class="toolbar-btn" onclick="formatText('justifyCenter')" title="Align Center">
                                                        <i class="fa-solid fa-align-center"></i>
                                                    </button>
                                                    <button type="button" class="toolbar-btn" onclick="formatText('justifyRight')" title="Align Right">
                                                        <i class="fa-solid fa-align-right"></i>
                                                    </button>
                                                    <button type="button" class="toolbar-btn" onclick="formatText('justifyFull')" title="Justify">
                                                        <i class="fa-solid fa-align-justify"></i>
                                                    </button>
                                                </div>

                                                <div class="toolbar-group">
                                                    <button type="button" class="toolbar-btn" onclick="formatText('insertUnorderedList')" title="Bullet List">
                                                        <i class="fa-solid fa-list-ul"></i>
                                                    </button>
                                                    <button type="button" class="toolbar-btn" onclick="formatText('insertOrderedList')" title="Numbered List">
                                                        <i class="fa-solid fa-list-ol"></i>
                                                    </button>
                                                </div>

                                                <div class="toolbar-group">
                                                    <button type="button" class="toolbar-btn" onclick="formatText('indent')" title="Increase Indent">
                                                        <i class="fa-solid fa-indent"></i>
                                                    </button>
                                                    <button type="button" class="toolbar-btn" onclick="formatText('outdent')" title="Decrease Indent">
                                                        <i class="fa-solid fa-outdent"></i>
                                                    </button>
                                                </div>

                                                <div class="toolbar-group">
                                                    <button type="button" class="toolbar-btn" onclick="formatText('undo')" title="Undo">
                                                        <i class="fa-solid fa-undo"></i>
                                                    </button>
                                                    <button type="button" class="toolbar-btn" onclick="formatText('redo')" title="Redo">
                                                        <i class="fa-solid fa-redo"></i>
                                                    </button>
                                                </div>

                                                <div class="toolbar-group">
                                                    <button type="button" class="toolbar-btn" onclick="clearFormatting()" title="Clear Formatting">
                                                        <i class="fa-solid fa-eraser"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="editor-content" id="editorContent" contenteditable="true"></div>
                                        </div>
                                        <input type="hidden" id="announcementContent" name="content" value="">
                                        <small>Use the toolbar above to format your text. All formatting will be preserved when saved.</small>
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
                                    <div class="form-group" id="otherProgramGroup" style="display: none;">
                                        <label for="otherProgramText">Specify Program Type</label>
                                        <input type="text" id="otherProgramText" name="program_type_other" placeholder="Enter program type or description">
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
                                    <div class="form-group" id="existingImagesContainer" style="display: none;">
                                        <label>Existing Images</label>
                                        <div id="existingImagesList" class="image-preview-list"></div>
                                    </div>
                                    <div class="form-group" id="imagePreviewContainer" style="display: none;">
                                        <label>Selected Images</label>
                                        <div id="imagePreviewList" class="image-preview-list"></div>
                                    </div>
                                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                                        <button type="submit" class="btn-primary">Save Announcement</button>
                                        <button type="button" class="btn-secondary" onclick="resetForm()">Clear Form</button>
                                    </div>
                                </form>
                            </div>

                            <!-- View Announcements Tab -->
                            <div id="view" class="inventory-tab-content">
                                <h2 style="margin: 0 0 8px;">Existing Announcements</h2>
                                <p style="color: #666; margin-bottom: 24px;">Latest scholarship opportunities, deadlines, and program updates.</p>

                                <div class="announcements-container" id="announcements-container">
                                    <!-- Announcements will be loaded here -->
                                </div>
                            </div>

                            <!-- Document Validation Tab -->
                            <div id="validation" class="inventory-tab-content">
                                <h2 style="margin: 0 0 8px;">Document Validation</h2>
                                <p style="color: #666; margin-bottom: 24px;">Track physical document submissions from students for scholarship applications.</p>

                                <div class="form-group">
                                    <label for="studentSearch">Search Student/Teacher (Name or ID)</label>
                                    <input type="text" id="studentSearch" placeholder="Enter student name or ID">
                                    <div id="studentSearchResults" class="search-results" style="display: none;"></div>
                                </div>

                                <div class="form-group">
                                    <label for="announcementSelect">Select Scholarship Announcement</label>
                                    <select id="announcementSelect" disabled>
                                        <option value="">Select a student first</option>
                                    </select>
                                </div>

                                <div id="validationForm" style="display: none;">
                                    <div class="form-group">
                                        <label for="validationDate">Validation Date</label>
                                        <input type="date" id="validationDate" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="validationNotes">Notes (Optional)</label>
                                        <textarea id="validationNotes" rows="3" placeholder="Additional notes about the validation"></textarea>
                                    </div>

                                    <button type="button" id="validateSubmissionBtn" class="btn-primary">Validate Submission</button>
                                </div>

                                <div id="validationHistory" style="margin-top: 30px;">
                                    <h3>Recent Validations</h3>
                                    <div id="validationHistoryContainer">
                                        <!-- Validation history will be loaded here -->
                                    </div>
                                </div>
                            </div>

                            <!-- Feedback Tab -->
                            <div id="feedback" class="inventory-tab-content">
                                <h2 style="margin: 0 0 8px;">Validation Feedback & Ratings</h2>
                                <p style="color: #666; margin-bottom: 24px;">View all student feedback, ratings, and reviews for scholarship validations.</p>
                                <div class="inventory-card">
                                    <table class="borrow-table">
                                        <thead>
                                            <tr>
                                                <th>Student Name</th>
                                                <th>Validation ID</th>
                                                <th>Process Rating</th>
                                                <th>Support Rating</th>
                                                <th>Review</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody id="feedback-table-body">
                                            <tr><td colspan="6" style="text-align: center; color: #999; padding: 30px;">Loading feedback...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="notification-container" class="notification-container"></div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        // Custom Rich Text Editor Functions
        let editorContent = document.getElementById('editorContent');
        let announcementContent = document.getElementById('announcementContent');

        // Initialize editor
        function initRichTextEditor() {
            if (!editorContent) return;

            // Sync content to hidden input on input/change
            editorContent.addEventListener('input', syncContent);
            editorContent.addEventListener('keyup', syncContent);
            editorContent.addEventListener('paste', function(e) {
                setTimeout(syncContent, 10);
            });

            // Update toolbar button states
            editorContent.addEventListener('keyup', updateToolbarStates);
            editorContent.addEventListener('mouseup', updateToolbarStates);
            editorContent.addEventListener('focus', updateToolbarStates);

            // Handle paste events to clean HTML
            editorContent.addEventListener('paste', function(e) {
                e.preventDefault();
                let text = (e.originalEvent || e).clipboardData.getData('text/plain');
                document.execCommand('insertText', false, text);
            });
        }

        function syncContent() {
            announcementContent.value = editorContent.innerHTML;
        }

        function formatText(command, value = null) {
            document.execCommand(command, false, value);
            editorContent.focus();
            updateToolbarStates();
            syncContent();
        }

        function clearFormatting() {
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                const selectedText = range.toString();

                if (selectedText) {
                    // Replace selected text with plain text
                    range.deleteContents();
                    const textNode = document.createTextNode(selectedText);
                    range.insertNode(textNode);
                    range.selectNodeContents(textNode);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }
            editorContent.focus();
            syncContent();
        }

        function updateToolbarStates() {
            const commands = ['bold', 'italic', 'underline', 'strikeThrough', 'justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'];

            commands.forEach(command => {
                const button = document.querySelector(`[onclick*="${command}"]`);
                if (button) {
                    if (document.queryCommandState(command)) {
                        button.classList.add('active');
                    } else {
                        button.classList.remove('active');
                    }
                }
            });
        }

        // Initialize editor when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initRichTextEditor();
        });

        // Tab switching functionality
        function switchTab(tabName, event) {
            if (event) {
                event.preventDefault();
            }
            
            // Update active clinic tab
            document.querySelectorAll('.inventory-tab-nav.clinic-tabs .clinic-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Find and activate the correct tab button
            const tabButton = document.querySelector(`.clinic-tab[onclick*="switchTab('${tabName}'"]`);
            if (tabButton) {
                tabButton.classList.add('active');
            }
            
            // Show active tab content
            document.querySelectorAll('.inventory-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            const activeTab = document.getElementById(tabName);
            if (activeTab) {
                activeTab.classList.add('active');
            }

            if (tabName === 'view') {
                loadAnnouncements();
            }
            if (tabName === 'feedback') {
                loadFeedback();
            }

            // Trigger tab activation event
            document.dispatchEvent(new CustomEvent('tabActivated', { detail: { tabName } }));

            window.location.hash = tabName;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const requestedTab = window.location.hash.replace('#', '');
            if (requestedTab === 'view') {
                switchTab('view');
            } else if (requestedTab === 'validation') {
                switchTab('validation');
            } else if (requestedTab === 'feedback') {
                switchTab('feedback');
            } else {
                switchTab('create');
            }
        });

        // Load and display scholarship validation feedback
        function loadFeedback() {
            fetch('../includes/api_scholarship_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_all', validation_id: 0}),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('feedback-table-body');
                if (!tbody) return;
                
                if (!data.success || !data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #999; padding: 30px;">No feedback submitted yet.</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.data.map(fb => `
                    <tr>
                        <td data-label="Student Name"><strong>${fb.full_name}</strong></td>
                        <td data-label="Validation ID">#${fb.validation_id}</td>
                        <td data-label="Process Rating" style="text-align: center;"><strong style="color: #3b82f6;">${fb.process_rating}/5 ★</strong></td>
                        <td data-label="Support Rating" style="text-align: center;"><strong style="color: #10b981;">${fb.support_rating}/5 ★</strong></td>
                        <td data-label="Review" style="max-width: 200px; word-wrap: break-word; font-size: 12px;">${fb.review_text ? fb.review_text.substring(0, 80) + (fb.review_text.length > 80 ? '...' : '') : '—'}</td>
                        <td data-label="Date">${new Date(fb.created_at).toLocaleDateString()}</td>
                    </tr>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading feedback:', error);
                const tbody = document.getElementById('feedback-table-body');
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Error loading feedback</td></tr>';
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initRichTextEditor();

            // Student search functionality
            document.getElementById('studentSearch').addEventListener('input', function() {
                const query = this.value.trim();
                if (query.length < 2) {
                    document.getElementById('studentSearchResults').style.display = 'none';
                    return;
                }

                fetch('scholarship_api.php?action=search_students&q=' + encodeURIComponent(query), {
                    credentials: 'same-origin'
                })
                    .then(response => response.json())
                    .then(data => {
                        displayStudentSearchResults(data.students || []);
                    })
                    .catch(error => {
                        console.error('Error searching students:', error);
                    });
            });
        });

        function displayStudentSearchResults(students) {
            const resultsContainer = document.getElementById('studentSearchResults');
            if (students.length === 0) {
                resultsContainer.style.display = 'none';
                return;
            }

            resultsContainer.innerHTML = students.map(student => `
                <div class="search-result-item" onclick="selectStudent(${student.id}, '${student.full_name}', '${student.student_id}', '${student.role}')">
                    <div>
                        <strong>${student.full_name}</strong><br>
                        <small>ID: ${student.student_id} | Course: ${student.course_year} | Role: ${student.role}</small>
                    </div>
                </div>
            `).join('');
            resultsContainer.style.display = 'block';
        }

        function selectStudent(id, name, studentId, role) {
            selectedStudent = { id, name, studentId, role };
            document.getElementById('studentSearch').value = name + ' (' + studentId + ') - ' + role;
            document.getElementById('studentSearchResults').style.display = 'none';
            document.getElementById('announcementSelect').disabled = false;
            loadAnnouncementsForValidation();
        }

        function loadAnnouncementsForValidation() {
            fetch('/THESIS/SUPPORTSERVICESYSTEM/dashboard/scholarship_api.php?action=get_announcements', {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('announcementSelect');
                    select.innerHTML = '<option value="">Select a scholarship announcement</option>';
                    (data.announcements || []).forEach(ann => {
                        select.innerHTML += `<option value="${ann.id}">${ann.title}</option>`;
                    });
                })
                .catch(error => {
                    console.error('Error loading announcements:', error);
                });
        }

        document.getElementById('announcementSelect').addEventListener('change', function() {
            const announcementId = this.value;
            if (announcementId && selectedStudent) {
                selectedAnnouncement = announcementId;
                loadValidationRequirements(announcementId);
                document.getElementById('validationForm').style.display = 'block';
            } else {
                document.getElementById('validationForm').style.display = 'none';
            }
        });

        function loadValidationRequirements(announcementId) {
            // No requirement checklist needed; just set today's date for validation.
            document.getElementById('validationDate').valueAsDate = new Date();
        }

        document.getElementById('validateSubmissionBtn').addEventListener('click', function() {
            if (!selectedStudent || !selectedAnnouncement) {
                showNotification('Please select a student and announcement first.', 'error');
                return;
            }

            const validationDate = document.getElementById('validationDate').value;
            if (!validationDate) {
                showNotification('Please select a validation date.', 'error');
                return;
            }

            const notes = document.getElementById('validationNotes').value;

            const validationData = {
                student_id: selectedStudent.id,
                announcement_id: selectedAnnouncement,
                requirements: [],
                validation_date: validationDate,
                notes: notes
            };

            fetch('/THESIS/SUPPORTSERVICESYSTEM/dashboard/scholarship_api.php?action=save_validation', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(validationData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Document validation saved successfully!', 'success');
                    loadValidationHistory();
                    // Reset form
                    resetValidationForm();
                } else {
                    showNotification(data.message || 'Error saving validation.', 'error');
                }
            })
            .catch(error => {
                console.error('Error saving validation:', error);
                showNotification('Error saving validation.', 'error');
            });
        });

        function resetValidationForm() {
            selectedStudent = null;
            selectedAnnouncement = null;
            document.getElementById('studentSearch').value = '';
            document.getElementById('announcementSelect').disabled = true;
            document.getElementById('announcementSelect').value = '';
            document.getElementById('validationForm').style.display = 'none';
            document.getElementById('validationNotes').value = '';
        }

        function loadValidationHistory() {
            fetch('/THESIS/SUPPORTSERVICESYSTEM/dashboard/scholarship_api.php?action=get_validations', {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    displayValidationHistory(data.validations || []);
                })
                .catch(error => {
                    console.error('Error loading validation history:', error);
                });
        }

        function displayValidationHistory(validations) {
            const container = document.getElementById('validationHistoryContainer');
            if (validations.length === 0) {
                container.innerHTML = '<p>No validations recorded yet.</p>';
                return;
            }

            container.innerHTML = validations.map(validation => `
                <div class="validation-history-item">
                    <div class="validation-meta">
                        <span><strong>${validation.student_name}</strong> (${validation.student_id})</span>
                        <span>${new Date(validation.validated_at).toLocaleDateString()}</span>
                    </div>
                    <div>
                        <strong>Announcement:</strong> ${validation.announcement_title}<br>
                        <strong>Validated by:</strong> ${validation.validator_name}
                    </div>
                    <div class="validation-requirements">
                        ${validation.requirements.map(req => `<span class="requirement-badge validated">${req}</span>`).join(' ')}
                    </div>
                    ${validation.notes ? `<div><strong>Notes:</strong> ${validation.notes}</div>` : ''}
                </div>
            `).join('');
        }

        // Load validation history when validation tab is activated
        document.addEventListener('tabActivated', function(e) {
            if (e.detail.tabName === 'validation') {
                loadValidationHistory();
            }
        });

        // Form submission
        document.getElementById('announcementForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            if (programTypeSelect.value === 'other' && otherProgramText.value.trim() !== '') {
                formData.set('program_type', otherProgramText.value.trim());
            }

            // Add existing images to keep
            existingImages.forEach((img, idx) => {
                formData.append(`keep_images[]`, img.path);
            });

            try {
                const response = await fetch('/THESIS/SUPPORTSERVICESYSTEM/dashboard/scholarship_api.php?action=save_announcement', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    const isUpdate = document.getElementById('announcementId').value !== '';
                    showNotification(`Announcement ${isUpdate ? 'updated' : 'created'} successfully!`, 'success');
                    this.reset();
                    editingAnnouncementId = null;
                    selectedImageFiles = [];
                    existingImages = [];
                    updateImagePreview();
                    updateExistingImagePreview();
                    updateOtherProgramField();
                    // Switch to view tab after successful creation
                    setTimeout(() => switchTab('view'), 1500);
                } else {
                    showNotification(result.message || 'Error saving announcement', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error creating announcement', 'error');
            }
        });

        // Load announcements for view tab
        async function loadAnnouncements() {
            try {
                const response = await fetch('/THESIS/SUPPORTSERVICESYSTEM/dashboard/scholarship_api.php?action=get_announcements', {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                renderAnnouncements(data.announcements || []);
            } catch (error) {
                console.error('Error loading announcements:', error);
                showNotification('Error loading announcements', 'error');
            }
        }

        // Render announcements
        function renderAnnouncements(announcements) {
            const container = document.getElementById('announcements-container');
            if (!announcements || announcements.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:#999; margin-top:40px;">No announcements available.</p>';
                return;
            }

            container.innerHTML = announcements.map(ann => {
                const content = ann.content;
                const plainText = content.replace(/<[^>]*>/g, '');
                const isLongContent = plainText.length > 300;
                const truncatedContent = isLongContent ? plainText.substring(0, 300) + '...' : content;
                const imageGrid = renderAnnouncementImageGrid(ann.images || []);

                return `
                    <div class="announcement-card scholarship">
                        <div class="announcement-header">
                            <div style="display:flex; flex-direction:column; gap:8px; flex:1;">
                                <h3 class="announcement-title">${escapeHtml(ann.title)}</h3>
                                <span class="announcement-badge scholarship">Scholarship</span>
                            </div>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                <button type="button" class="btn-secondary" style="font-size:12px; padding:6px 12px; white-space:nowrap;" onclick="editAnnouncement(${ann.id})">Edit</button>
                                <button type="button" class="btn-danger" style="font-size:12px; padding:6px 12px; white-space:nowrap; background:#dc3545; color:#fff; border:1px solid #dc3545;" onclick="removeAnnouncement(${ann.id})">Remove</button>
                            </div>
                        </div>
                        <div class="announcement-meta">
                            <span class="announcement-meta-item"><i class="fa-solid fa-graduation-cap"></i> ${escapeHtml(ann.program_type || 'General')}</span>
                            <span class="announcement-meta-item"><i class="fa-solid fa-clock"></i> ${new Date(ann.created_at).toLocaleDateString()}</span>
                            ${ann.deadline ? `<span class="announcement-meta-item"><i class="fa-solid fa-hourglass-end"></i> Deadline: ${new Date(ann.deadline).toLocaleDateString()}</span>` : ''}
                        </div>
                        <div class="announcement-description${isLongContent ? ' collapsed' : ''}">
                            <p class="announcement-text" id="content-${ann.id}">${isLongContent ? escapeHtml(truncatedContent) : content}</p>
                            ${isLongContent ? `<button class="toggle-description" onclick="toggleContent(${ann.id}, '${content.replace(/'/g, "\\'").replace(/"/g, '&quot;')}')" id="read-more-${ann.id}">See More</button>` : ''}
                        </div>
                        ${imageGrid}
                    </div>
                `;
            }).join('');
        }

        async function removeAnnouncement(id) {
            if (!confirm('Remove this announcement? It will no longer appear on the school announcements page.')) {
                return;
            }

            try {
                const response = await fetch('/THESIS/SUPPORTSERVICESYSTEM/dashboard/scholarship_api.php?action=delete_announcement', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to remove announcement');
                }

                showNotification('Announcement removed successfully', 'success');
                await loadAnnouncements();
            } catch (error) {
                console.error('Error removing announcement:', error);
                showNotification(error.message || 'Error removing announcement', 'error');
            }
        }

        function renderAnnouncementImageGrid(images) {
            const normalized = (images || []).filter(Boolean);
            if (!normalized.length) {
                return '';
            }

            if (normalized.length === 1) {
                return `
                    <div class="announcement-images-grid" data-images='${JSON.stringify(normalized)}'>
                        <div class="announcement-image-row single-row">
                            <button type="button" class="announcement-image-wrapper" data-image-index="0" onclick="openImageModal(${JSON.stringify(normalized)}, 0)">
                                <img src="${escapeHtml(normalized[0])}" alt="Announcement image">
                            </button>
                        </div>
                    </div>
                `;
            }

            const topImages = normalized.slice(0, Math.min(2, normalized.length));
            const bottomImages = normalized.slice(2, Math.min(5, normalized.length));
            const bottomClass = bottomImages.length === 1 ? 'one-item' : bottomImages.length === 2 ? 'two-items' : 'three-items';

            let html = `
                <div class="announcement-images-grid" data-images='${JSON.stringify(normalized)}'>
                    <div class="announcement-image-row top-row">
                        ${topImages.map((src, idx) => `
                            <button type="button" class="announcement-image-wrapper" data-image-index="${idx}" onclick="openImageModal(${JSON.stringify(normalized)}, ${idx})">
                                <img src="${escapeHtml(src)}" alt="Announcement image">
                            </button>
                        `).join('')}
                    </div>
            `;

            if (bottomImages.length) {
                html += `
                    <div class="announcement-image-row bottom-row ${bottomClass}">
                        ${bottomImages.map((src, idx) => {
                            const imageIndex = idx + topImages.length;
                            return `
                                <button type="button" class="announcement-image-wrapper" data-image-index="${imageIndex}" onclick="openImageModal(${JSON.stringify(normalized)}, ${imageIndex})">
                                    <img src="${escapeHtml(src)}" alt="Announcement image">
                                    ${normalized.length > 5 && idx === bottomImages.length - 1 ? `<div class="announcement-image-overlay">+${normalized.length - 5}</div>` : ''}
                                </button>
                            `;
                        }).join('')}
                    </div>
                `;
            }

            html += '</div>';
            return html;
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Content toggle function for see more/less (Facebook-style)
        function toggleContent(announcementId, fullContent) {
            const contentDiv = document.getElementById(`content-${announcementId}`);
            const readMoreBtn = document.getElementById(`read-more-${announcementId}`);

            if (contentDiv.classList.contains('expanded')) {
                // Collapse content
                contentDiv.classList.remove('expanded');
                const plainText = fullContent.replace(/<[^>]*>/g, '');
                const truncatedContent = plainText.substring(0, 300) + '...';
                contentDiv.innerHTML = truncatedContent;
                contentDiv.style.maxHeight = '100px';
                contentDiv.style.overflow = 'hidden';
                readMoreBtn.textContent = 'See More';
            } else {
                // Expand content
                contentDiv.classList.add('expanded');
                contentDiv.innerHTML = fullContent;
                contentDiv.style.maxHeight = 'none';
                contentDiv.style.overflow = 'visible';
                readMoreBtn.textContent = 'See Less';
            }
        }

        // Image modal functions
        function openImageModal(imageList, startIndex = 0) {
            const images = Array.isArray(imageList) ? imageList.filter(Boolean) : [imageList].filter(Boolean);
            if (!images.length) {
                return;
            }

            let modal = document.getElementById('imageModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'imageModal';
                modal.className = 'image-modal';
                modal.innerHTML = `
                    <div class="image-modal-content">
                        <button class="modal-close" onclick="closeImageModal()">&times;</button>
                        <button class="modal-nav modal-prev" type="button" onclick="changeImageModal(-1)">&#10094;</button>
                        <img id="modalImage" src="" alt="Full size image">
                        <button class="modal-nav modal-next" type="button" onclick="changeImageModal(1)">&#10095;</button>
                        <div id="modalImageCounter" class="modal-image-counter"></div>
                    </div>
                `;
                document.body.appendChild(modal);

                if (!document.getElementById('imageModalStyles')) {
                    const style = document.createElement('style');
                    style.id = 'imageModalStyles';
                    style.textContent = `
                        .image-modal {
                            display: none;
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0, 0, 0, 0.9);
                            z-index: 9999;
                            align-items: center;
                            justify-content: center;
                        }
                        .image-modal.show {
                            display: flex;
                        }
                        .image-modal-content {
                            position: relative;
                            max-width: 90%;
                            max-height: 90%;
                            animation: zoomIn 0.3s ease;
                        }
                        @keyframes zoomIn {
                            from { opacity: 0; transform: scale(0.8); }
                            to { opacity: 1; transform: scale(1); }
                        }
                        #modalImage {
                            max-width: 100%;
                            max-height: 80vh;
                            object-fit: contain;
                            border-radius: 12px;
                        }
                        .modal-nav {
                            position: absolute;
                            top: 50%;
                            transform: translateY(-50%);
                            background: rgba(255, 255, 255, 0.2);
                            color: white;
                            border: none;
                            font-size: 28px;
                            width: 44px;
                            height: 44px;
                            border-radius: 50%;
                            cursor: pointer;
                        }
                        .modal-prev { left: -18px; }
                        .modal-next { right: -18px; }
                        .modal-image-counter {
                            position: absolute;
                            left: 50%;
                            bottom: -34px;
                            transform: translateX(-50%);
                            color: white;
                            font-size: 14px;
                            background: rgba(0, 0, 0, 0.3);
                            padding: 6px 12px;
                            border-radius: 999px;
                        }
                        .image-modal .modal-close {
                            position: absolute;
                            top: -40px;
                            right: 0;
                            background: rgba(255, 255, 255, 0.2);
                            color: white;
                            border: none;
                            font-size: 36px;
                            width: 50px;
                            height: 50px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            cursor: pointer;
                            transition: background 0.3s ease;
                            border-radius: 50%;
                        }
                        .image-modal .modal-close:hover {
                            background: rgba(255, 255, 255, 0.4);
                        }
                    `;
                    document.head.appendChild(style);
                }

                modal.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        closeImageModal();
                    }
                });

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && modal.classList.contains('show')) {
                        closeImageModal();
                    }
                    if (event.key === 'ArrowRight' && modal.classList.contains('show')) {
                        changeImageModal(1);
                    }
                    if (event.key === 'ArrowLeft' && modal.classList.contains('show')) {
                        changeImageModal(-1);
                    }
                });
            }

            modal.dataset.imageList = JSON.stringify(images);
            modal.dataset.currentIndex = String(Math.min(Math.max(startIndex, 0), images.length - 1));
            updateImageModal();
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function updateImageModal() {
            const modal = document.getElementById('imageModal');
            if (!modal) return;

            const images = JSON.parse(modal.dataset.imageList || '[]');
            const currentIndex = Number(modal.dataset.currentIndex || 0);
            const img = document.getElementById('modalImage');
            const counter = document.getElementById('modalImageCounter');

            img.src = images[currentIndex];
            if (counter) {
                counter.textContent = `${currentIndex + 1} / ${images.length}`;
            }
        }

        function changeImageModal(direction) {
            const modal = document.getElementById('imageModal');
            if (!modal) return;

            const images = JSON.parse(modal.dataset.imageList || '[]');
            let currentIndex = Number(modal.dataset.currentIndex || 0);
            currentIndex = (currentIndex + direction + images.length) % images.length;
            modal.dataset.currentIndex = String(currentIndex);
            updateImageModal();
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        }

        const programTypeSelect = document.getElementById('programType');
        const otherProgramGroup = document.getElementById('otherProgramGroup');
        const otherProgramText = document.getElementById('otherProgramText');
        const announcementImages = document.getElementById('announcementImages');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const imagePreviewList = document.getElementById('imagePreviewList');
        const existingImagesContainer = document.getElementById('existingImagesContainer');
        const existingImagesList = document.getElementById('existingImagesList');
        let selectedImageFiles = [];
        let editingAnnouncementId = null;
        let existingImages = [];

        programTypeSelect.addEventListener('change', updateOtherProgramField);
        announcementImages.addEventListener('change', handleImageSelection);

        function updateOtherProgramField() {
            if (programTypeSelect.value === 'other') {
                otherProgramGroup.style.display = 'block';
                otherProgramText.required = true;
            } else {
                otherProgramGroup.style.display = 'none';
                otherProgramText.required = false;
                otherProgramText.value = '';
            }
        }

        function handleImageSelection() {
            const files = Array.from(announcementImages.files);
            if (files.length === 0) {
                selectedImageFiles = [];
            } else {
                selectedImageFiles = selectedImageFiles.concat(files);
            }
            updateFileInput();
            updateImagePreview();
        }

        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            selectedImageFiles.forEach(file => dataTransfer.items.add(file));
            announcementImages.files = dataTransfer.files;
        }

        function updateImagePreview() {
            imagePreviewList.innerHTML = '';
            if (selectedImageFiles.length === 0) {
                imagePreviewContainer.style.display = 'none';
                return;
            }
            imagePreviewContainer.style.display = 'block';
            selectedImageFiles.forEach((file, index) => {
                const previewCard = document.createElement('div');
                previewCard.className = 'image-preview-item';

                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = file.name;

                const label = document.createElement('div');
                label.textContent = file.name;
                label.style = 'font-size:12px; color:#333; margin-bottom:8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.textContent = 'Remove';
                removeBtn.addEventListener('click', () => removeSelectedImage(index));

                previewCard.appendChild(img);
                previewCard.appendChild(label);
                previewCard.appendChild(removeBtn);
                imagePreviewList.appendChild(previewCard);
            });
        }

        function removeSelectedImage(index) {
            selectedImageFiles.splice(index, 1);
            updateFileInput();
            updateImagePreview();
        }

        function resetForm() {
            document.getElementById('announcementForm').reset();
            document.getElementById('announcementId').value = '';
            editingAnnouncementId = null;
            selectedImageFiles = [];
            existingImages = [];
            updateImagePreview();
            updateExistingImagePreview();
            updateOtherProgramField();

            // Clear rich text editor content
            if (editorContent) {
                editorContent.innerHTML = '';
                syncContent();
            }
        }

        async function editAnnouncement(id) {
            try {
                const response = await fetch(`/THESIS/SUPPORTSERVICESYSTEM/dashboard/scholarship_api.php?action=get_announcements&id=${id}`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data.success && data.announcements && data.announcements.length > 0) {
                    const ann = data.announcements[0];
                    editingAnnouncementId = id;
                    document.getElementById('announcementId').value = ann.id;
                    document.getElementById('announcementTitle').value = ann.title;

                    // Set content in rich text editor
                    if (editorContent) {
                        editorContent.innerHTML = ann.content;
                        syncContent();
                    } else {
                        document.getElementById('announcementContent').value = ann.content;
                    }

                    const programType = ann.program_type;
                    const standardTypes = ['TDP', 'TES', 'financial_aid'];
                    if (standardTypes.includes(programType)) {
                        document.getElementById('programType').value = programType;
                        document.getElementById('otherProgramText').value = '';
                    } else {
                        document.getElementById('programType').value = 'other';
                        document.getElementById('otherProgramText').value = programType;
                    }
                    document.getElementById('deadline').value = ann.deadline ? ann.deadline.split(' ')[0] : '';
                    updateOtherProgramField();

                    // Display existing images
                    existingImages = (ann.images || []).map((path, idx) => ({ path, id: `existing_${idx}` }));
                    updateExistingImagePreview();

                    switchTab('create');
                } else {
                    showNotification('Error loading announcement for editing', 'error');
                }
            } catch (error) {
                console.error('Error editing announcement:', error);
                showNotification('Error loading announcement', 'error');
            }
        }

        function updateExistingImagePreview() {
            if (existingImages.length === 0) {
                existingImagesContainer.style.display = 'none';
                return;
            }
            existingImagesContainer.style.display = 'block';
            existingImagesList.innerHTML = existingImages.map((img) => `
                <div class="image-preview-item">
                    <img src="${escapeHtml(img.path)}" alt="Existing image">
                    <button type="button" onclick="removeExistingImage('${img.id}')">Remove</button>
                </div>
            `).join('');
        }

        function removeExistingImage(imageId) {
            existingImages = existingImages.filter(img => img.id !== imageId);
            updateExistingImagePreview();
        }

        function showNotification(message, type) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }
    </script>
    <script>
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

        // Enable swipe navigation for scholarship tabs on mobile
        function enableScholarshipTabSwipe() {
            const tabNav = document.querySelector('.inventory-tab-nav.clinic-tabs');
            if (!tabNav) return;

            let startX = 0;
            let currentX = 0;
            let isDragging = false;

            tabNav.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                isDragging = true;
            }, false);

            tabNav.addEventListener('touchmove', (e) => {
                if (!isDragging) return;
                currentX = e.touches[0].clientX;
            }, false);

            tabNav.addEventListener('touchend', (e) => {
                if (!isDragging) return;
                isDragging = false;

                const diff = startX - currentX;
                const threshold = 50;

                if (Math.abs(diff) < threshold) return;

                const tabs = document.querySelectorAll('.clinic-tab');
                let currentTabIndex = -1;

                tabs.forEach((tab, index) => {
                    if (tab.classList.contains('active')) {
                        currentTabIndex = index;
                    }
                });

                if (diff > 0 && currentTabIndex < tabs.length - 1) {
                    // Swiped left, go to next tab
                    const nextTab = tabs[currentTabIndex + 1];
                    const tabName = nextTab.onclick.toString().match(/'([^']+)'/)[1];
                    switchTab(tabName);
                } else if (diff < 0 && currentTabIndex > 0) {
                    // Swiped right, go to previous tab
                    const prevTab = tabs[currentTabIndex - 1];
                    const tabName = prevTab.onclick.toString().match(/'([^']+)'/)[1];
                    switchTab(tabName);
                }
            }, false);
        }

        // Initialize swipe after page load
        document.addEventListener('DOMContentLoaded', function() {
            enableScholarshipTabSwipe();
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
