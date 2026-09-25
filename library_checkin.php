<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'library') {
    header('Location: /auth/teacher_login.php');
    exit;
}
ensure_library_schema();
$checkinMessage = '';
$closedCount = 0;
if (new DateTime() >= new DateTime('today 17:00')) {
    $closedCount = finalize_library_visits_at_end_of_day();
    if ($closedCount > 0) {
        $checkinMessage = $closedCount . ' active visit(s) were automatically checked out at 5:00 PM.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $visitId = (int)($_POST['visit_id'] ?? 0);

    if ($action === 'approve_entry' && $visitId > 0) {
        if (approve_library_visit($visitId, $user['id'])) {
            $checkinMessage = 'Visitor approved and now marked as IN.';
        } else {
            $checkinMessage = 'Unable to approve this entry. Please try again.';
        }
    }

    if ($action === 'checkout_entry' && $visitId > 0) {
        $visit = get_library_visit_by_id($visitId);
        if ($visit && $visit['status'] === 'in') {
            $timeOut = (new DateTime())->format('Y-m-d H:i:s');
            $started = new DateTime($visit['time_in']);
            $duration = (int)$started->diff(new DateTime())->format('%i') + (int)$started->diff(new DateTime())->format('%h') * 60;
            if (complete_library_visit($visitId, $timeOut, $duration)) {
                $checkinMessage = 'Visitor checked out successfully.';
            } else {
                $checkinMessage = 'Unable to complete checkout. Please try again.';
            }
        } else {
            $checkinMessage = 'This visitor is not currently marked as inside the library.';
        }
    }

    if ($action === 'auto_checkout_all') {
        $closedCount = finalize_library_visits_at_end_of_day();
        if ($closedCount > 0) {
            echo json_encode(['success' => true, 'checked_out' => true, 'message' => $closedCount . ' active visit(s) were automatically checked out at 5:00 PM.']);
        } else {
            echo json_encode(['success' => true, 'checked_out' => false, 'message' => 'No active visits to check out.']);
        }
        exit;
    }

    if ($action === 'manual_checkin') {
        $name = secure_input($_POST['name'] ?? '');
        $visitorType = secure_input($_POST['visitor_type'] ?? 'student');
        $studentNumber = secure_input($_POST['student_number'] ?? '');
        $employeeId = secure_input($_POST['employee_id'] ?? '');
        $courseDepartment = secure_input($_POST['course_department'] ?? '');
        $purpose = secure_input($_POST['purpose'] ?? '');

        if (!$name || !$purpose || ($visitorType === 'student' && (!$studentNumber || !$courseDepartment)) || ($visitorType === 'teacher' && !$employeeId)) {
            $checkinMessage = 'Please complete all required fields.';
        } else {
            $visitData = [
                'user_id' => null,
                'name' => $name,
                'visitor_type' => $visitorType,
                'student_number' => $studentNumber ?: null,
                'employee_id' => $employeeId ?: null,
                'course_department' => $courseDepartment,
                'purpose' => $purpose,
                'checkin_method' => 'manual',
                'time_in' => (new DateTime())->format('Y-m-d H:i:s'),
                'status' => 'in', // Directly approve manual check-ins
                'is_approved' => 1,
            ];

            if (save_library_visit($visitData)) {
                $checkinMessage = 'Manual check-in completed successfully. Visitor is now marked as IN.';
                $todayCheckins = get_today_library_checkins();
                $activeVisits = get_active_library_visits(10);
            } else {
                $checkinMessage = 'Unable to complete manual check-in. Please try again.';
            }
        }
    }

    if ($action === 'search_users') {
        $query = secure_input($_POST['query'] ?? '');
        if (strlen($query) >= 2) {
            $users = search_users($query, 5);
            echo json_encode($users);
        } else {
            echo json_encode([]);
        }
        exit;
    }

    // Individual visit removal
    if ($action === 'remove_visit') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $pdo = get_db();
        if ($visitId > 0) {
            $stmt = $pdo->prepare('DELETE FROM library_visits WHERE id = ?');
            if ($stmt->execute([$visitId])) {
                header('Location: library_checkin.php?tab=recent&msg=removed');
                exit;
            }
        }
        $checkinMessage = 'Unable to remove visit.';
    }

    // Bulk remove multiple visits
    if ($action === 'remove_multiple_visits') {
        $visitIds = $_POST['visit_ids'] ?? [];
        if (!is_array($visitIds)) $visitIds = [];
        $pdo = get_db();
        $removed = 0;
        foreach ($visitIds as $vid) {
            $id = (int)$vid;
            if ($id > 0) {
                $stmt = $pdo->prepare('DELETE FROM library_visits WHERE id = ?');
                if ($stmt->execute([$id])) {
                    $removed++;
                }
            }
        }
        header('Location: library_checkin.php?tab=recent&msg=removed&count=' . $removed);
        exit;
    }

    // Handle GET messages from redirects
    if (isset($_GET['msg'])) {
        $msgType = $_GET['msg'];
        $count = (int)($_GET['count'] ?? 0);
        
        switch ($msgType) {
            case 'removed':
                if ($count > 0) {
                    $checkinMessage = 'Successfully removed ' . $count . ' visit' . ($count === 1 ? '' : 's') . '.';
                } else {
                    $checkinMessage = 'Visit removed successfully.';
                }
                break;
        }
    }
}

$todayCheckins = get_today_library_checkins();
$pendingVisits = get_pending_library_visits(20);
$activeVisits = get_active_library_visits(10);
$recentEntries = get_recent_library_entries(10);
$currentPage = basename($_SERVER['PHP_SELF']);
$serviceOpen = false;
$manageOpen = false;
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library QR Check-In | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }

        .library-page-header {
            position: relative;
            min-height: 245px;
            display: flex;
            align-items: center;
            overflow: hidden;
            margin: 0 0 24px;
            padding: 42px 48px 30px;
            background: #f8f1e7;
            border-radius: 0 0 24px 24px;
        }
        .library-page-header::before {
            content: '';
            position: absolute;
            z-index: 0;
            inset: 0 auto 0 0;
            width: 3px;
            background: #861b17;
        }
        .library-page-header > div:first-child {
            position: relative;
            z-index: 2;
            width: 65%;
        }
        .library-page-header .eyebrow {
            margin: 0;
            color: #861b17 !important;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 3px;
            line-height: 1;
            text-transform: uppercase;
        }
        .library-page-header h1 {
            margin: 14px 0 12px;
            color: #111 !important;
            font-family: Georgia, serif;
            font-size: clamp(36px, 4vw, 62px);
            font-weight: 600;
            line-height: 1.05;
        }
        .library-page-header .dashboard-subtitle {
            max-width: 760px;
            margin: 0;
            color: #34302d !important;
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
        }
        .library-header__art {
            position: absolute;
            z-index: 1;
            top: 0;
            right: 0;
            width: 43%;
            height: 100%;
            color: #d7a58d;
            opacity: .78;
        }
        .library-header__art::before {
            content: '';
            position: absolute;
            right: 10%;
            bottom: 4%;
            width: 84%;
            height: 28%;
            background: radial-gradient(ellipse at center, rgba(235, 205, 167, .5) 0 42%, transparent 43%);
            border-radius: 50%;
        }
        .library-header__art::after {
            content: '';
            position: absolute;
            top: 23%;
            left: 3%;
            width: 76%;
            height: 45%;
            border: 1px solid rgba(215, 165, 141, .45);
            border-left-color: transparent;
            border-radius: 50%;
            transform: rotate(-13deg);
        }
        .library-header__art i {
            position: absolute;
            z-index: 2;
            font-family: 'Font Awesome 6 Free';
            font-style: normal;
            font-weight: 900;
        }
        .library-header__art .book-stack {
            top: 16%;
            left: 39%;
            padding: 16px 18px;
            border: 2px solid rgba(215, 165, 141, .58);
            border-radius: 9px;
            box-shadow: 18px 12px 0 -1px #f8f1e7, 18px 12px 0 1px rgba(215, 165, 141, .48), 34px 23px 0 -1px #f8f1e7, 34px 23px 0 1px rgba(215, 165, 141, .42);
            font-size: 36px;
        }
        .library-header__art .open-book {
            top: 42%;
            left: 48%;
            font-size: 53px;
            color: #cf9589;
            transform: rotate(-3deg);
        }
        .library-header__art .library-card {
            top: 61%;
            left: 36%;
            padding: 10px 13px;
            border: 2px solid rgba(215, 165, 141, .65);
            border-radius: 7px;
            background: rgba(248, 241, 231, .75);
            font-size: 22px;
            transform: rotate(-8deg);
        }
        .library-header__art .library-check {
            top: 22%;
            right: 7%;
            padding: 12px;
            border: 2px solid #e3b968;
            border-radius: 50%;
            color: #e3b968;
            font-size: 21px;
        }
        .library-header__art .library-pin {
            bottom: 9%;
            left: 57%;
            width: 31px;
            height: 31px;
            color: transparent;
            background: #cf9589;
            border-radius: 50% 50% 50% 0;
            font-size: 0;
            transform: rotate(-45deg);
        }
        .library-header__art .library-pin::after {
            content: '';
            position: absolute;
            top: 9px;
            left: 9px;
            width: 13px;
            height: 13px;
            background: #f8f1e7;
            border-radius: 50%;
        }
        .library-header__art .library-leaf {
            right: 12%;
            bottom: 11%;
            color: #d7a58d;
            font-size: 28px;
        }
        @media (max-width: 768px) {
            .library-page-header {
                min-height: 245px;
                padding: 32px 24px 110px;
            }
            .library-page-header > div:first-child {
                width: 100%;
            }
            .library-page-header h1 {
                font-size: 36px;
            }
            .library-header__art {
                width: 55%;
                opacity: .35;
            }
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        .summary-card {
            padding: 22px 20px;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.05);
        }
        .summary-card span {
            display: block;
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
        }
        .summary-card strong {
            display: block;
            font-size: 1.7rem;
            line-height: 1.1;
            color: #0f172a;
        }
        .tab-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding: 8px;
            background: #ffffff;
            border-radius: 12px;
            justify-content: space-between;
        }
        .library-tab-button {
            border: 2px solid transparent;
            background: transparent;
            color: var(--text-soft);
            padding: 16px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 16px;
            flex: 1;
            text-align: center;
        }
        .library-tab-button.active,
        .library-tab-button:hover {
            background: var(--deep-maroon);
            color: white;
            border-color: var(--soft-gold);
            box-shadow: 0 4px 16px var(--shadow-medium);
        }
        .library-tab-bar {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto !important;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch !important;
            gap: 12px;
            padding: 4px 0 4px 4px;
            margin-bottom: 20px;
            scroll-snap-type: x mandatory;
            touch-action: pan-x;
            -ms-touch-action: pan-x;
            white-space: nowrap;
        }
        .library-tab-bar::-webkit-scrollbar {
            height: 6px;
        }
        .library-tab-bar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.5);
            border-radius: 999px;
        }
        .library-tab-bar > .library-tab-button {
            scroll-snap-align: start;
            min-width: 180px;
            flex: 0 0 auto;
            white-space: nowrap;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }
        .tabbed-checkin-panel {
            overflow: hidden;
        }
        .management-card {
            overflow-x: auto;
        }
        .management-card table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
        }
        .management-card th,
        .management-card td {
            padding: 16px 14px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .management-card th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .management-card td {
            color: #334155;
            font-size: 0.95rem;
        }
        .management-card tbody tr:hover {
            background: #f8fbff;
        }
        .management-card button {
            padding: 10px 16px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
        }
        .management-card button.approve-btn {
            background: #10b981;
            color: #ffffff;
        }
        .management-card button.checkout-btn {
            background: #ef4444;
            color: #ffffff;
        }
        @media (max-width: 1100px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(200px, 1fr));
            }
        }
        @media (max-width: 720px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .library-tab-bar {
                gap: 10px;
                padding: 8px 0;
            }

            .library-tab-button {
                min-width: 160px;
                padding: 12px 10px;
                font-size: 14px;
            }

            .checkin-filters {
                flex-direction: column;
            }

            .checkin-filters > div {
                width: 100%;
                min-width: 0;
            }

            .tabbed-checkin-panel {
                padding: 18px;
            }

            .management-card {
                overflow-x: hidden;
            }

            .management-card table {
                width: 100%;
                min-width: 0;
                border: none;
            }

            .management-card thead {
                display: none;
            }

            .management-card tbody tr {
                display: block;
                margin-bottom: 16px;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                background: #ffffff;
                overflow: hidden;
            }

            .management-card td {
                display: block;
                width: 100%;
                padding: 12px 14px;
                border: none;
                border-bottom: 1px solid #e2e8f0;
                font-size: 14px;
            }

            .management-card td:last-child {
                border-bottom: none;
            }

            .management-card td::before {
                content: attr(data-label);
                display: block;
                margin-bottom: 8px;
                font-size: 12px;
                font-weight: 700;
                color: #475569;
            }

            .management-card td button {
                width: 100%;
                justify-content: center;
            }

            .management-card td > form {
                margin: 0;
            }

            .tab-content > h2 {
                font-size: 20px;
            }

            .manual-checkin-card {
                padding: 18px;
            }

            .manual-checkin-card form > div {
                grid-template-columns: 1fr !important;
                width: 100%;
            }

            .manual-checkin-card form > div:last-child {
                flex-direction: column;
                gap: 10px;
            }

            .manual-checkin-card form > div:last-child button {
                width: 100%;
            }

            .management-card button {
                width: 100%;
            }
        }
        /* Search results styling */
        #searchResults {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: white;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            max-height: 280px;
            overflow-y: auto;
            z-index: 1000;
        }
        #searchResults .search-result-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        #searchResults .search-result-item:hover {
            background: #f8fafc;
        }
        #searchResults .search-result-item:last-child {
            border-bottom: none;
        }
        #searchResults .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
        }
        #searchResults .user-avatar.student {
            background: #3b82f6;
        }
        #searchResults .user-avatar.teacher {
            background: #10b981;
        }
        #searchResults .user-info strong {
            display: block;
            font-size: 14px;
            color: #1f2937;
            margin-bottom: 2px;
        }
        #searchResults .user-info small {
            font-size: 12px;
            color: #6b7280;
        }
        #searchResults .user-role {
            font-size: 10px;
            background: #e5e7eb;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Form field focus states */
        .form-field input:focus, .form-field select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: white;
        }

        /* Search input focus */
        #searchName:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Button hover effects */
        .primary-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }

        #clearFormBtn:hover {
            border-color: #9ca3af;
            background: #f9fafb;
            color: #374151;
        }

        /* Manual checkin card hover */
        .manual-checkin-card:hover {
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.1);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .manual-checkin-card {
                padding: 16px;
            }

            #searchResults .search-result-item {
                padding: 10px 12px;
            }

            .form-field input, .form-field select {
                padding: 10px 12px;
            }

            /* Stack form fields vertically on mobile */
            .manual-checkin-card form > div:first-child {
                grid-template-columns: 1fr !important;
            }

            .manual-checkin-card form > div:nth-child(2) {
                grid-template-columns: 1fr !important;
            }

            /* Adjust button layout */
            .manual-checkin-card form > div:last-child {
                flex-direction: column;
            }

            .manual-checkin-card form > div:last-child button {
                width: 100%;
            }

            /* Header layout */
            .tab-content[data-tab="manual"] > div:first-child {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .tab-content[data-tab="manual"] > div:first-child > div:last-child {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .manual-checkin-card {
                margin-bottom: 16px;
                padding: 12px;
            }

            .tab-content[data-tab="manual"] > div:first-child {
                margin-bottom: 16px;
            }

            .user-info strong {
                font-size: 13px;
            }

            .user-info small {
                font-size: 11px;
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
<body class="library-checkin swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Library Management</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
               
            </div>
            <div class="nav-section">
                <a href="dashboard/librarian_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="dashboard/school_announcements.php?service=library" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $serviceOpen ? 'true' : 'false' ?>" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $serviceOpen ? ' open' : '' ?>" aria-hidden="<?= $serviceOpen ? 'false' : 'true' ?>">
                        <a href="dashboard/guidance_dashboard.php?service=library" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="dashboard/library_dashboard.php?service=library" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="dashboard/clinic_dashboard.php?service=library" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="dashboard/ssc/ssc_dashboard.php?service=library" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="dashboard/scholarship/scholarship_dashboard.php?service=library" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="dashboard/ssaa_student_home.php?service=library" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
                        </a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $manageOpen ? 'true' : 'false' ?>" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $manageOpen ? ' open' : '' ?>" aria-hidden="<?= $manageOpen ? 'false' : 'true' ?>">
                        <a class="active" href="library_checkin.php" data-tooltip="QR Check-In">
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
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">Library QR</span>
                        </a>
                    </div>
                </div>
                <a href="dashboard/profile.php?service=library" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="dashboard/about.php?service=library" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
            </div>
            <div class="nav-footer">
                <a href="dashboard/librarian_home.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
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
                            <p>Library Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Library Head</span>
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
                        <a class="menu-link menu-footer-link" href="dashboard/about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="dashboard/profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="dashboard/profile.php" class="menu-action">View profile</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="dashboard/about.php">Help center</a>
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
                <section class="dashboard-intro library-page-header">
                    <div>
                        <span class="eyebrow">QR CHECK-IN</span>
                        <h1>Manage Library Entry Logs</h1>
                        <p class="dashboard-subtitle">Track pending approvals, active visitors, and recent scan history in one clean, organized view.</p>
                    </div>
                    <div class="library-header__art" aria-hidden="true">
                        <i class="fa-solid fa-layer-group book-stack"></i>
                        <i class="fa-solid fa-book-open open-book"></i>
                        <i class="fa-solid fa-id-card library-card"></i>
                        <i class="fa-solid fa-circle-check library-check"></i>
                        <i class="fa-solid fa-location-pin library-pin"></i>
                        <i class="fa-solid fa-leaf library-leaf"></i>
                    </div>
                </section>
                    <section class="summary-overview" style="margin-top: 24px;">
                        <div class="summary-grid">
                            <div class="summary-card">
                                <span>Today</span>
                                <strong><?= htmlspecialchars($todayCheckins) ?> check-ins</strong>
                            </div>
                            <div class="summary-card">
                                <span>Pending</span>
                                <strong><?= htmlspecialchars(count($pendingVisits)) ?> requests</strong>
                            </div>
                            <div class="summary-card">
                                <span>Active</span>
                                <strong><?= htmlspecialchars(count($activeVisits)) ?> visitors</strong>
                            </div>
                            <div class="summary-card">
                                <span>Recent</span>
                                <strong><?= htmlspecialchars(count($recentEntries)) ?> entries</strong>
                            </div>
                        </div>
                    </section>
                    <?php if ($checkinMessage): ?>
                        <section class="dashboard-section">
                            <div class="alert success" style="margin-bottom: 20px; padding: 18px; border-radius: 16px; background: #dcfce7; color: #166534;"><?= htmlspecialchars($checkinMessage) ?></div>
                        </section>
                    <?php endif; ?>
                    <section class="dashboard-section">
                        <div class="tabbed-checkin-panel" style="background: #ffffff; border-radius: 20px; padding: 24px; box-shadow: 0 16px 40px rgba(15,23,42,0.06);">
                    <section class="tab-buttons library-tab-bar" role="tablist" aria-label="Check-in navigation">
                        <button type="button" class="library-tab-button active" data-tab="pending">Pending check-in approvals</button>
                        <button type="button" class="library-tab-button" data-tab="active">Active library visits</button>
                        <button type="button" class="library-tab-button" data-tab="recent">Recent scan activity</button>
                        <button type="button" class="library-tab-button" data-tab="manual">Manual check-in</button>
                    </section>
                            <div class="checkin-filters" style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
                                <div style="min-width:180px;">
                                    <label for="visitorTypeFilter">Visitor type</label>
                                    <select id="visitorTypeFilter">
                                        <option value="">All types</option>
                                        <option value="student">Student</option>
                                        <option value="teacher">Teacher</option>
                                    </select>
                                </div>
                                <div style="min-width:240px;">
                                    <label for="courseFilter">Course / department</label>
                                    <select id="courseFilter">
                                        <option value="">All courses</option>
                                        <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                                        <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                                        <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                                        <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                                        <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                                        <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                                        <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                                        <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                        <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                        <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                        <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                                    </select>
                                </div>
                            </div>

                            <div class="tab-content active" data-tab="pending">
                                <h2 style="margin-bottom: 18px;">Pending check-in approvals</h2>
                                <div class="management-card">
                                    <table>
                                        <thead>
                                            <tr><th>Name</th><th>Type</th><th>Department / Course</th><th>Purpose</th><th>Submitted</th><th>Action</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($pendingVisits)): ?>
                                            <tr><td colspan="6">No pending check-ins at the moment.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($pendingVisits as $visit): ?>
                                                    <tr>
                                                        <td data-label="Name"><?= htmlspecialchars($visit['name']) ?></td>
                                                        <td data-label="Type"><?= htmlspecialchars($visit['visitor_type']) ?></td>
                                                        <td data-label="Department / Course"><?= htmlspecialchars($visit['course_department'] ?? 'N/A') ?></td>
                                                        <td data-label="Purpose"><?= htmlspecialchars($visit['purpose']) ?></td>
                                                        <td data-label="Submitted"><?= htmlspecialchars(date('M j, g:i A', strtotime($visit['time_in']))) ?></td>
                                                        <td data-label="Action">
                                                            <form method="post" style="margin:0;">
                                                                <input type="hidden" name="action" value="approve_entry">
                                                                <input type="hidden" name="visit_id" value="<?= (int)$visit['id'] ?>">
                                                                <button type="submit" class="approve-btn">Approve</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-content" data-tab="active" style="display: none;">
                                <h2 style="margin-bottom: 18px;">Active library visits</h2>
                                <div class="management-card">
                                    <table>
                                        <thead>
                                            <tr><th>Name</th><th>Type</th><th>Student / Employee ID</th><th>Department</th><th>Time In</th><th>Time Out</th><th>Action</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($activeVisits)): ?>
                                            <tr><td colspan="6">No active visitors at this time.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($activeVisits as $visit): ?>
                                                    <tr>
                                                        <td data-label="Name"><?= htmlspecialchars($visit['name']) ?></td>
                                                        <td data-label="Type"><?= htmlspecialchars($visit['visitor_type']) ?></td>
                                                        <td data-label="Student / Employee ID"><?= htmlspecialchars($visit['student_number'] ?: $visit['employee_id'] ?: '—') ?></td>
                                                        <td data-label="Department" data-course="true"><?= htmlspecialchars($visit['course_department'] ?? '—') ?></td>
                                                        <td data-label="Time In"><?= htmlspecialchars(date('g:i A', strtotime($visit['time_in']))) ?></td>
                                                        <td data-label="Time Out"><?= $visit['time_out'] ? htmlspecialchars(date('g:i A', strtotime($visit['time_out']))) : '—' ?></td>
                                                        <td data-label="Action">
                                                            <form method="post" style="margin:0;">
                                                                <input type="hidden" name="action" value="checkout_entry">
                                                                <input type="hidden" name="visit_id" value="<?= (int)$visit['id'] ?>">
                                                                <button type="submit" class="checkout-btn">Check Out</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-content" data-tab="recent" style="display: none;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                                    <h2 style="margin: 0;">Recent scan activity</h2>
                                    <div style="display: flex; gap: 8px;">
                                        <button type="button" id="libraryIndividualRemoveToggle" class="button btn-secondary" style="padding: 8px 12px; font-size: 13px;" onclick="toggleLibraryIndividualRemove()">
                                            <i class="fas fa-trash-alt"></i> Remove Individual
                                        </button>
                                        <button type="button" id="libraryBulkRemoveToggle" class="button btn-secondary" style="padding: 8px 12px; font-size: 13px;" onclick="toggleLibraryBulkRemove()">
                                            <i class="fas fa-check-square"></i> Remove Multiple
                                        </button>
                                    </div>
                                </div>
                                <div class="management-card">
                                    <form id="recentScansTableForm" method="post">
                                        <table>
                                            <thead>
                                                <tr><th>Name</th><th>Type</th><th>Department / Course</th><th>Time In</th><th>Time Out</th><th>Status</th><th>Actions</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recentEntries)): ?>
                                                <tr><td colspan="7">No recent entries found.</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($recentEntries as $entry): ?>
                                                        <tr>
                                                            <td data-label="Name"><?= htmlspecialchars($entry['name']) ?></td>
                                                            <td data-label="Type"><?= htmlspecialchars($entry['visitor_type']) ?></td>
                                                            <td data-label="Department / Course" data-course="true"><?= htmlspecialchars($entry['course_department'] ?? '—') ?></td>
                                                            <td data-label="Time In"><?= htmlspecialchars(date('M j, g:i A', strtotime($entry['time_in']))) ?></td>
                                                            <td data-label="Time Out"><?= $entry['time_out'] ? htmlspecialchars(date('g:i A', strtotime($entry['time_out']))) : '—' ?></td>
                                                            <td data-label="Status"><?= ($entry['status'] === 'pending') ? 'Pending' : (($entry['time_out'] === null) ? 'In' : 'Out') ?></td>
                                                            <td data-label="Actions" style="display: flex; gap: 8px; align-items: center;">
                                                                <i class="fas fa-trash-alt library-remove-icon" style="display: none; cursor: pointer; color: #ef4444; font-size: 14px;" onclick="openModal('library_remove_confirm_<?= $entry['id'] ?>')"></i>
                                                                <input type="checkbox" name="visit_ids[]" class="library-bulk-checkbox" value="<?= (int)$entry['id'] ?>" style="display: none;">
                                                            </td>
                                                        </tr>
                                                        <!-- Remove Confirmation Modal -->
                                                        <div class="modal" id="library_remove_confirm_<?= $entry['id'] ?>">
                                                            <div class="modal-content">
                                                                <div class="modal-header">Confirm Remove</div>
                                                                <div style="padding: 12px;">
                                                                    <p>Are you sure you want to remove this library scan record for <?= htmlspecialchars($entry['name']) ?>?</p>
                                                                    <form method="post" style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
                                                                        <input type="hidden" name="action" value="remove_visit">
                                                                        <input type="hidden" name="visit_id" value="<?= (int)$entry['id'] ?>">
                                                                        <button type="submit" class="button btn-danger">Remove</button>
                                                                        <button type="button" class="button btn-secondary" onclick="closeModal('library_remove_confirm_<?= $entry['id'] ?>')">Cancel</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </form>
                                </div>

                                <!-- Bulk Remove Confirmation Modal -->
                                <div class="modal" id="library_bulk_remove_confirm_modal">
                                    <div class="modal-content">
                                        <div class="modal-header">Confirm Bulk Remove</div>
                                        <div style="padding: 12px;">
                                            <p id="libraryBulkRemoveMessage">Are you sure you want to remove the selected scan records?</p>
                                            <form id="libraryBulkRemoveConfirmForm" method="post" style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
                                                <input type="hidden" name="action" value="remove_multiple_visits">
                                                <div id="libraryBulkRemoveCheckboxesContainer" style="display:none;"></div>
                                                <button type="submit" class="button btn-danger">Remove All Selected</button>
                                                <button type="button" class="button btn-secondary" onclick="closeModal('library_bulk_remove_confirm_modal')">Cancel</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content" data-tab="manual" style="display: none;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                                    <div>
                                        <h2 style="margin: 0 0 4px 0; color: #0f172a;">Manual Check-In</h2>
                                        <p style="margin: 0; color: #64748b; font-size: 14px;">Search for existing users or manually enter visitor details</p>
                                    </div>
                                    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 12px 16px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i class="fa-solid fa-info-circle" style="color: #0284c7;"></i>
                                            <span style="font-size: 13px; color: #0369a1; font-weight: 500;">Manual entries are automatically approved</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Search Section -->
                                <div class="manual-checkin-card" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                                        <div style="width: 40px; height: 40px; background: #3b82f6; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-search" style="color: white; font-size: 16px;"></i>
                                        </div>
                                        <div>
                                            <h3 style="margin: 0; font-size: 16px; color: #1e293b; font-weight: 600;">Find Existing User</h3>
                                            <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Search by name, student ID, or employee ID</p>
                                        </div>
                                    </div>
                                    <div style="position: relative;">
                                        <div style="position: relative;">
                                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px;"></i>
                                            <input type="text" id="searchName" placeholder="Start typing to search users..." autocomplete="off"
                                                   style="width: 100%; padding: 14px 14px 14px 44px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 14px; background: white; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                                            <div id="searchLoading" style="display: none; position: absolute; right: 16px; top: 50%; transform: translateY(-50%);">
                                                <i class="fa-solid fa-spinner fa-spin" style="color: #9ca3af;"></i>
                                            </div>
                                        </div>
                                        <div id="searchResults" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); z-index: 1000; max-height: 280px; overflow-y: auto;">
                                            <div id="noResults" style="display: none; padding: 16px; text-align: center; color: #6b7280; font-size: 14px;">
                                                <i class="fa-solid fa-search" style="margin-right: 8px;"></i>
                                                No users found
                                            </div>
                                        </div>
                                    </div>
                                    <div style="margin-top: 12px; font-size: 12px; color: #9ca3af; display: flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid fa-lightbulb"></i>
                                        <span>Type at least 2 characters to start searching</span>
                                    </div>
                                </div>

                                <!-- Manual Entry Form -->
                                <div class="manual-checkin-card" style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                        <div style="width: 40px; height: 40px; background: #10b981; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa-solid fa-user-plus" style="color: white; font-size: 16px;"></i>
                                        </div>
                                        <div>
                                            <h3 style="margin: 0; font-size: 16px; color: #1e293b; font-weight: 600;">Visitor Details</h3>
                                            <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Enter visitor information manually</p>
                                        </div>
                                    </div>

                                    <form method="post" id="manualCheckinForm">
                                        <input type="hidden" name="action" value="manual_checkin">

                                        <!-- Basic Information Row -->
                                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                                            <div class="form-field">
                                                <label for="manualName" style="display: block; font-weight: 600; color: #374151; margin-bottom: 6px; font-size: 13px;">
                                                    Full Name <span style="color: #ef4444;">*</span>
                                                </label>
                                                <input type="text" id="manualName" name="name" required
                                                       style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.2s ease; background: #fafafa;"
                                                       placeholder="Enter full name">
                                            </div>
                                            <div class="form-field">
                                                <label for="manualVisitorType" style="display: block; font-weight: 600; color: #374151; margin-bottom: 6px; font-size: 13px;">
                                                    Visitor Type <span style="color: #ef4444;">*</span>
                                                </label>
                                                <select id="manualVisitorType" name="visitor_type" required
                                                        style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: white; transition: all 0.2s ease; cursor: pointer;">
                                                    <option value="student"><i class="fa-solid fa-user-graduate"></i> Student</option>
                                                    <option value="teacher"><i class="fa-solid fa-chalkboard-teacher"></i> Teacher</option>
                                                </select>
                                            </div>
                                            <div class="form-field">
                                                <label for="manualPurpose" style="display: block; font-weight: 600; color: #374151; margin-bottom: 6px; font-size: 13px;">
                                                    Purpose <span style="color: #ef4444;">*</span>
                                                </label>
                                                <select id="manualPurpose" name="purpose" required
                                                        style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: white; transition: all 0.2s ease; cursor: pointer;">
                                                    <option value="">Select purpose</option>
                                                    <option value="Borrowing"><i class="fa-solid fa-book"></i> Borrowing</option>
                                                    <option value="Research"><i class="fa-solid fa-microscope"></i> Research</option>
                                                    <option value="Reading"><i class="fa-solid fa-book-open"></i> Reading</option>
                                                    <option value="Reservation pickup"><i class="fa-solid fa-calendar-check"></i> Reservation pickup</option>
                                                    <option value="Other"><i class="fa-solid fa-question"></i> Other</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- ID and Course Row -->
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                                            <div id="studentIdGroup" class="form-field">
                                                <label for="manualStudentId" style="display: block; font-weight: 600; color: #374151; margin-bottom: 6px; font-size: 13px;">
                                                    Student ID <span style="color: #ef4444;">*</span>
                                                </label>
                                                <input type="text" id="manualStudentId" name="student_number" required
                                                       style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.2s ease; background: #fafafa;"
                                                       placeholder="e.g., 21-12345">
                                            </div>
                                            <div id="teacherIdGroup" class="form-field" style="display: none;">
                                                <label for="manualEmployeeId" style="display: block; font-weight: 600; color: #374151; margin-bottom: 6px; font-size: 13px;">
                                                    Employee ID <span style="color: #ef4444;">*</span>
                                                </label>
                                                <input type="text" id="manualEmployeeId" name="employee_id"
                                                       style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.2s ease; background: #fafafa;"
                                                       placeholder="e.g., EMP001">
                                            </div>
                                            <div class="form-field" style="grid-column: span 1;">
                                                <label for="manualCourse" style="display: block; font-weight: 600; color: #374151; margin-bottom: 6px; font-size: 13px;">
                                                    Course / Department <span style="color: #ef4444;">*</span>
                                                </label>
                                                <input type="text" id="manualCourse" name="course_department" required
                                                       style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.2s ease; background: #fafafa;"
                                                       placeholder="e.g., Bachelor of Science in Computer Science">
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                                            <button type="button" id="clearFormBtn"
                                                    style="padding: 12px 24px; border: 2px solid #d1d5db; border-radius: 8px; background: white; color: #6b7280; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">
                                                <i class="fa-solid fa-rotate-left" style="margin-right: 8px;"></i>
                                                Clear Form
                                            </button>
                                            <button type="submit" class="primary-button"
                                                    style="padding: 12px 32px; border: none; border-radius: 8px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; font-weight: 600; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                                                <i class="fa-solid fa-check" style="margin-right: 8px;"></i>
                                                Check In Visitor
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </section>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.library-tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            const visitorTypeFilter = document.getElementById('visitorTypeFilter');
            const courseFilter = document.getElementById('courseFilter');

            function courseMatchesFilter(courseText, selectedCourse) {
                if (!selectedCourse) {
                    return true;
                }
                const normalized = (courseText || '').toLowerCase();
                const courseKey = selectedCourse.toLowerCase();
                const aliasGroups = {
                    'bachelor of science in computer science': ['bachelor of science in computer science', 'associate in computer technology'],
                    'bachelor of science in business administration': ['bachelor of science in business administration', 'associate in business knowledge'],
                    'bachelor of science in hospitality management': ['bachelor of science in hospitality management', 'associate in hospitality management'],
                    'bachelor of science in tourism management': ['bachelor of science in tourism management', 'associate in tourism management'],
                };
                const variants = aliasGroups[courseKey] || [selectedCourse];
                return variants.some(course => normalized.includes(course.toLowerCase()));
            }

            function filterCheckinRows() {
                const selectedType = visitorTypeFilter.value;
                const selectedCourse = courseFilter.value;

                const activeRows = document.querySelectorAll('[data-tab="active"] table tbody tr');
                const recentRows = document.querySelectorAll('[data-tab="recent"] table tbody tr');

                [activeRows, recentRows].forEach(rows => {
                    rows.forEach(row => {
                        const typeCell = row.children[1];
                        const courseCell = row.querySelector('[data-course]');
                        const rowType = typeCell ? typeCell.textContent.trim().toLowerCase() : '';
                        const rowCourse = courseCell ? courseCell.textContent.trim().toLowerCase() : '';

                        const matchesType = !selectedType || rowType === selectedType;
                        const matchesCourse = !selectedCourse || courseMatchesFilter(rowCourse, selectedCourse);

                        row.style.display = matchesType && matchesCourse ? '' : 'none';
                    });
                });
            }

            visitorTypeFilter.addEventListener('change', filterCheckinRows);
            courseFilter.addEventListener('change', filterCheckinRows);

            function selectLibraryTab(selectedTab, clickedButton) {
                tabButtons.forEach(btn => {
                    btn.classList.toggle('active', btn === clickedButton);
                });

                tabContents.forEach(content => {
                    content.style.display = content.dataset.tab === selectedTab ? 'block' : 'none';
                });
            }

            function attachLibraryTabSwipe() {
                const tabBar = document.querySelector('.library-tab-bar');
                if (!tabBar) return;

                let startX = 0;
                let currentX = 0;
                let isTouching = false;

                tabBar.addEventListener('touchstart', function(event) {
                    if (event.touches.length !== 1) return;
                    startX = event.touches[0].clientX;
                    currentX = startX;
                    isTouching = true;
                }, { passive: true });

                tabBar.addEventListener('touchmove', function(event) {
                    if (!isTouching || event.touches.length !== 1) return;
                    currentX = event.touches[0].clientX;
                }, { passive: true });

                tabBar.addEventListener('touchend', function() {
                    if (!isTouching) return;
                    const delta = startX - currentX;
                    const threshold = 60;
                    if (Math.abs(delta) >= threshold) {
                        const buttons = Array.from(tabButtons);
                        const activeIndex = buttons.findIndex(btn => btn.classList.contains('active'));
                        if (activeIndex !== -1) {
                            const nextIndex = delta > 0 ? Math.min(buttons.length - 1, activeIndex + 1) : Math.max(0, activeIndex - 1);
                            if (nextIndex !== activeIndex) {
                                buttons[nextIndex].click();
                                buttons[nextIndex].scrollIntoView({ behavior: 'smooth', inline: 'center' });
                            }
                        }
                    }
                    isTouching = false;
                }, { passive: true });
            }

            tabButtons.forEach(button => {
                button.addEventListener('click', () => selectLibraryTab(button.dataset.tab, button));
            });

            attachLibraryTabSwipe();

            filterCheckinRows();

            // Manual check-in search functionality
            const searchInput = document.getElementById('searchName');
            const searchResults = document.getElementById('searchResults');
            const searchLoading = document.getElementById('searchLoading');
            const noResults = document.getElementById('noResults');
            const manualName = document.getElementById('manualName');
            const manualVisitorType = document.getElementById('manualVisitorType');
            const manualStudentId = document.getElementById('manualStudentId');
            const manualEmployeeId = document.getElementById('manualEmployeeId');
            const manualCourse = document.getElementById('manualCourse');
            const studentIdGroup = document.getElementById('studentIdGroup');
            const teacherIdGroup = document.getElementById('teacherIdGroup');
            const clearFormBtn = document.getElementById('clearFormBtn');

            let searchTimeout;

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();

                if (query.length >= 2) {
                    searchLoading.style.display = 'block';
                    searchTimeout = setTimeout(() => performSearch(query), 300);
                } else {
                    searchResults.style.display = 'none';
                    searchLoading.style.display = 'none';
                }
            });

            function performSearch(query) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=search_users&query=' + encodeURIComponent(query)
                })
                .then(response => response.json())
                .then(users => {
                    searchLoading.style.display = 'none';
                    displaySearchResults(users);
                })
                .catch(error => {
                    searchLoading.style.display = 'none';
                    console.error('Search failed:', error);
                    showSearchError();
                });
            }

            function displaySearchResults(users) {
                // Clear previous results
                const existingItems = searchResults.querySelectorAll('.search-result-item');
                existingItems.forEach(item => item.remove());

                if (users.length === 0) {
                    noResults.style.display = 'block';
                    searchResults.style.display = 'block';
                    return;
                }

                noResults.style.display = 'none';

                users.forEach(user => {
                    const div = document.createElement('div');
                    div.className = 'search-result-item';

                    const avatarClass = user.role === 'student' ? 'student' : 'teacher';
                    const avatarIcon = user.role === 'student' ? 'fa-user-graduate' : 'fa-chalkboard-teacher';
                    const userId = user.student_id || user.employee_id || 'No ID';
                    const status = user.status || 'Student';
                    
                    // Color code status
                    let statusColor = '#6b7280'; // default gray
                    if (status === 'Alumni') {
                        statusColor = '#7c3aed'; // purple
                    } else if (status.includes('1st Year')) {
                        statusColor = '#3b82f6'; // blue
                    } else if (status.includes('2nd Year')) {
                        statusColor = '#10b981'; // green
                    } else if (status.includes('3rd Year')) {
                        statusColor = '#f59e0b'; // amber
                    } else if (status.includes('4th Year')) {
                        statusColor = '#ef4444'; // red
                    }

                    div.innerHTML = `
                        <div class="user-avatar ${avatarClass}"><i class="fa-solid ${avatarIcon}"></i></div>
                        <div class="user-info" style="flex: 1;">
                            <strong>${user.full_name}</strong>
                            <small>${userId} • ${user.course_year || 'No course'}</small>
                        </div>
                        <span class="user-role" style="background-color: ${statusColor}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">${status}</span>
                    `;

                    div.addEventListener('click', () => {
                        fillFormWithUser(user);
                        searchResults.style.display = 'none';
                        searchInput.value = '';
                        // Add success feedback
                        searchInput.style.borderColor = '#10b981';
                        searchInput.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
                        setTimeout(() => {
                            searchInput.style.borderColor = '#e5e7eb';
                            searchInput.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';
                        }, 2000);
                    });

                    searchResults.appendChild(div);
                });

                searchResults.style.display = 'block';
            }

            function showSearchError() {
                const existingItems = searchResults.querySelectorAll('.search-result-item');
                existingItems.forEach(item => item.remove());
                noResults.innerHTML = '<i class="fa-solid fa-exclamation-triangle" style="margin-right: 8px; color: #ef4444;"></i>Search failed - please try again';
                noResults.style.display = 'block';
                searchResults.style.display = 'block';
            }

            function fillFormWithUser(user) {
                manualName.value = user.full_name;
                manualVisitorType.value = user.role;

                if (user.role === 'student') {
                    manualStudentId.value = user.student_id || '';
                    manualEmployeeId.value = '';
                    studentIdGroup.style.display = 'block';
                    teacherIdGroup.style.display = 'none';
                    manualStudentId.required = true;
                    manualEmployeeId.required = false;
                } else {
                    manualEmployeeId.value = user.employee_id || '';
                    manualStudentId.value = '';
                    studentIdGroup.style.display = 'none';
                    teacherIdGroup.style.display = 'block';
                    manualStudentId.required = false;
                    manualEmployeeId.required = true;
                }

                manualCourse.value = user.course_year || '';

                // Highlight filled fields
                [manualName, manualStudentId, manualEmployeeId, manualCourse].forEach(field => {
                    if (field.value) {
                        field.style.borderColor = '#10b981';
                        field.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
                        setTimeout(() => {
                            field.style.borderColor = '#e5e7eb';
                            field.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';
                        }, 2000);
                    }
                });
            }

            // Clear form functionality
            clearFormBtn.addEventListener('click', function() {
                manualName.value = '';
                manualStudentId.value = '';
                manualEmployeeId.value = '';
                manualCourse.value = '';
                manualPurpose.value = '';
                manualVisitorType.value = 'student';
                studentIdGroup.style.display = 'block';
                teacherIdGroup.style.display = 'none';
                manualStudentId.required = true;
                manualEmployeeId.required = false;

                // Reset all field styles
                [manualName, manualStudentId, manualEmployeeId, manualCourse, manualPurpose, manualVisitorType].forEach(field => {
                    field.style.borderColor = '#e5e7eb';
                    field.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';
                });

                // Show success feedback
                this.innerHTML = '<i class="fa-solid fa-check" style="margin-right: 8px;"></i>Form Cleared!';
                this.style.background = '#10b981';
                this.style.borderColor = '#10b981';
                this.style.color = 'white';

                setTimeout(() => {
                    this.innerHTML = '<i class="fa-solid fa-rotate-left" style="margin-right: 8px;"></i>Clear Form';
                    this.style.background = 'white';
                    this.style.borderColor = '#d1d5db';
                    this.style.color = '#6b7280';
                }, 1500);
            });

            // Handle visitor type change
            manualVisitorType.addEventListener('change', function() {
                if (this.value === 'student') {
                    studentIdGroup.style.display = 'block';
                    teacherIdGroup.style.display = 'none';
                    manualStudentId.required = true;
                    manualEmployeeId.required = false;
                    manualEmployeeId.value = '';
                } else {
                    studentIdGroup.style.display = 'none';
                    teacherIdGroup.style.display = 'block';
                    manualStudentId.required = false;
                    manualEmployeeId.required = true;
                    manualStudentId.value = '';
                }
            });

            // Hide search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });

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

            // Form submission feedback
            const manualForm = document.getElementById('manualCheckinForm');
            manualForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right: 8px;"></i>Checking In...';
                submitBtn.disabled = true;

                // Re-enable after 3 seconds (in case of error)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            });
        });

        // Automatic checkout at 5:00 PM
        function scheduleAutoCheckout() {
            const runAutoCheckoutIfDue = () => {
                const now = new Date();
                const todayKey = now.toISOString().slice(0, 10);
                const checkoutTime = new Date(now);
                checkoutTime.setHours(17, 0, 0, 0); // 5:00 PM

                if (now >= checkoutTime && localStorage.getItem('libraryAutoCheckoutDate') !== todayKey) {
                    localStorage.setItem('libraryAutoCheckoutDate', todayKey);
                    performAutoCheckout();
                }
            };

            runAutoCheckoutIfDue();

            const now = new Date();
            const nextCheck = new Date(now);
            nextCheck.setHours(17, 0, 0, 0);

            if (now >= nextCheck) {
                nextCheck.setDate(nextCheck.getDate() + 1);
            }

            const timeUntilNextCheck = Math.max(1000, nextCheck - now);

            setTimeout(function repeatDailyCheck() {
                runAutoCheckoutIfDue();
                setInterval(runAutoCheckoutIfDue, 24 * 60 * 60 * 1000); // Every 24 hours
            }, timeUntilNextCheck);

            // Re-check when the page becomes active again, in case timers were throttled.
            document.addEventListener('visibilitychange', runAutoCheckoutIfDue);
            window.addEventListener('focus', runAutoCheckoutIfDue);

            // Poll every 30 seconds while the page is open so the 5 PM cutoff is not missed.
            setInterval(runAutoCheckoutIfDue, 30 * 1000);
        }

        function performAutoCheckout() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=auto_checkout_all'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.checked_out) {
                    // Only show alert and reload if visitors were actually checked out
                    alert(data.message);
                    location.reload();
                }
                // If no visitors were checked out, silently continue without alert
            })
            .catch(error => {
                console.error('Auto checkout failed:', error);
            });
        }

        // Start the auto checkout scheduler
        scheduleAutoCheckout();

        // Setup library recent scans remove interactions
        const libraryBulkBtn = document.querySelector('button[id="libraryBulkRemoveToggle"]');
        if (libraryBulkBtn) {
            // Find the "Remove All Selected" button inside the modal form
            const modalForm = document.getElementById('libraryBulkRemoveConfirmForm');
            if (modalForm) {
                const removeAllBtn = modalForm.querySelector('button[type="submit"]');
                if (removeAllBtn) {
                    removeAllBtn.addEventListener('click', function(e) {
                        // Allow form to submit naturally
                    });
                }
            }
        }

        // When any checkbox changes, toggle the bulk remove button visibility
        document.querySelectorAll('.library-bulk-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const anyChecked = Array.from(document.querySelectorAll('.library-bulk-checkbox')).some(c => c.checked);
                const btn = libraryBulkBtn;
                if (btn) btn.style.opacity = anyChecked ? '1' : '0.5';
            });
        });

        // Setup bulk remove button handler
        if (libraryBulkBtn) {
            libraryBulkBtn.addEventListener('click', function(e) {
                e.preventDefault();
                // Get all checked checkboxes
                const checkedBoxes = Array.from(document.querySelectorAll('.library-bulk-checkbox:checked'));
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one record to remove.');
                    return;
                }
                // Update confirmation message
                const msg = document.getElementById('libraryBulkRemoveMessage');
                if (msg) msg.textContent = 'Are you sure you want to remove ' + checkedBoxes.length + ' selected scan record(s)?';
                // Copy checkboxes to confirmation form
                const container = document.getElementById('libraryBulkRemoveCheckboxesContainer');
                if (container) {
                    container.innerHTML = '';
                    checkedBoxes.forEach(cb => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'visit_ids[]';
                        input.value = cb.value;
                        container.appendChild(input);
                    });
                }
                // Show confirmation modal
                openModal('library_bulk_remove_confirm_modal');
            });
        }
    </script>
    <script>
        // Toggle functions for library recent scans remove UI
        function toggleLibraryIndividualRemove() {
            const icons = document.querySelectorAll('.library-remove-icon');
            const checkboxes = document.querySelectorAll('.library-bulk-checkbox');
            // hide checkboxes when entering individual mode
            checkboxes.forEach(c => c.style.display = 'none');
            icons.forEach(i => {
                i.style.display = (i.style.display === 'inline-block') ? 'none' : 'inline-block';
            });
            // hide bulk remove button and uncheck all
            const bulkBtn = document.querySelector('button[id="libraryBulkRemoveToggle"]');
            if (bulkBtn) bulkBtn.style.opacity = '0.5';
            checkboxes.forEach(c => c.checked = false);
            // close bulk confirmation modal
            closeModal('library_bulk_remove_confirm_modal');
        }

        function toggleLibraryBulkRemove() {
            const icons = document.querySelectorAll('.library-remove-icon');
            const checkboxes = document.querySelectorAll('.library-bulk-checkbox');
            // hide remove icons when entering bulk mode
            icons.forEach(i => i.style.display = 'none');
            // toggle checkbox visibility
            const anyHidden = Array.from(checkboxes).some(c => c.style.display === 'none');
            checkboxes.forEach(c => c.style.display = anyHidden ? 'inline-block' : 'none');
            // ensure bulk button visible
            const bulkBtn = document.querySelector('button[id="libraryBulkRemoveToggle"]');
            if (bulkBtn) bulkBtn.style.opacity = '1';
            checkboxes.forEach(c => c.checked = false);
            // close any open individual remove modals
            document.querySelectorAll('[id^="library_remove_confirm_"]').forEach(modal => closeModal(modal.id));
        }
    </script>
    <script src="assets/js/app.js" defer></script>
    <?php include 'AI CHAT BOT/chat_widget.php'; ?>
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
