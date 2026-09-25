<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'clinic') {
    header('Location: /auth/teacher_login.php');
    exit;
}

// Initialize clinic schema
ensure_clinic_schema();

// Manage section remains collapsed by default on clinic management pages
$is_manage_open = false;

// Get visit data
$currentVisits = get_current_clinic_visits();
$todayVisits = get_today_clinic_visits();
$pendingLogoutApprovals = get_pending_logout_approvals();
$clinicRecentVisits = get_recent_clinic_visits(50);
$clinicUsers = get_clinic_users();
// Normalize course display and mark alumni where applicable
foreach ($clinicUsers as &$__cu) {
    $__cu['course_display'] = format_course_for_display($__cu['course_year'] ?? '', $__cu['id'] ?? null);
}
unset($__cu);
$activeVisitTab = $_POST['active_tab'] ?? $_GET['active_tab'] ?? 'manual-logout';

// Get all visits with patient info
$pdo = get_db();
$stmt = $pdo->prepare('SELECT cv.*, u.full_name as patient_name, u.role, u.course_year FROM clinic_visits cv LEFT JOIN users u ON cv.user_id = u.id ORDER BY cv.time_in DESC LIMIT 100');
$stmt->execute();
$allVisits = $stmt->fetchAll();

// Handle form submissions
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_visit'])) {
        $visitId = (int)$_POST['visit_id'];
        if (delete_clinic_visit($visitId)) {
            $message = 'Visit activity deleted successfully.';
            $clinicRecentVisits = get_recent_clinic_visits(50);
            $stmt->execute();
            $allVisits = $stmt->fetchAll();
        } else {
            $message = 'Failed to delete visit activity.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['update_remarks'])) {
        $visitId = (int)$_POST['visit_id'];
        $remarks = trim($_POST['remarks'] ?? '');
        $completionStatus = trim($_POST['completion_status'] ?? 'pending');
        
        if (empty($remarks)) {
            $message = 'Remarks cannot be empty.';
            $messageType = 'error';
        } elseif (update_clinic_visit_remarks($visitId, $remarks, $completionStatus)) {
            $message = 'Remarks and status updated successfully.';
            $clinicRecentVisits = get_recent_clinic_visits(50);
            $stmt->execute();
            $allVisits = $stmt->fetchAll();
        } else {
            $message = 'Failed to update remarks and status.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['complete_visit'])) {
        $visitId = (int)$_POST['visit_id'];
        $timeOut = $_POST['time_out'];
        $duration = (int)$_POST['duration_minutes'];

        if (complete_clinic_visit($visitId, $timeOut, $duration)) {
            $message = 'Visit completed successfully!';
            $currentVisits = get_current_clinic_visits();
            $todayVisits = get_today_clinic_visits();
            // Refresh all visits
            $stmt->execute();
            $allVisits = $stmt->fetchAll();
        } else {
            $message = 'Failed to complete visit.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['submit_clinic_visit_logout'])) {
        $visitorType = $_POST['visitor_type'] ?? 'visitor';
        $courseDepartment = '';

        // Build course_department based on visitor type
        if ($visitorType === 'student') {
            $course = trim($_POST['student_course'] ?? '');
            $ladderizeCourse = trim($_POST['ladderize_course'] ?? '');
            $year = trim($_POST['student_year'] ?? '');
            if (!empty($ladderizeCourse)) {
                $courseDepartment = $ladderizeCourse;
            } elseif (!empty($course) && !empty($year)) {
                $courseDepartment = trim($course . ' ' . $year);
            } elseif (!empty($course)) {
                $courseDepartment = $course;
            }
        } elseif ($visitorType === 'teacher') {
            $courseDepartment = trim($_POST['teacher_course'] ?? '');
        }
        // For visitors, course_department remains empty

        $visitorReason = trim($_POST['visitor_reason_choice'] ?? '');
        if ($visitorReason === 'Other') {
            $visitorReason = trim($_POST['visitor_reason_other'] ?? '');
        }

        // Handle remarks (with support for "others" option)
        $visitorRemarks = trim($_POST['visitor_remarks'] ?? '');
        if ($visitorRemarks === 'others') {
            $visitorRemarks = trim($_POST['visitor_remarks_other'] ?? '');
        }

        // Get completion status
        $completionStatus = trim($_POST['visitor_completion_status'] ?? 'completed');

        $visitData = [
            'user_id' => null,
            'name' => trim($_POST['visitor_name'] ?? ''),
            'visitor_type' => $visitorType,
            'course_department' => $courseDepartment,
            'reason_for_visit' => $visitorReason,
            'checkin_method' => 'manual',
            'time_in' => date('Y-m-d H:i:s'),
            'time_out' => date('Y-m-d H:i:s'),
            'duration_minutes' => 0,
            'status' => 'completed',
            'priority' => 'low',
            'remarks' => $visitorRemarks,
            'completion_status' => $completionStatus
        ];

        if ($visitorType === 'student' && empty($course) && empty($ladderizeCourse)) {
            $message = 'For student visitors, choose either Course or Ladderize Course.';
            $messageType = 'error';
        } else {
            if (!empty($visitData['name'])) {
                if (save_clinic_visit($visitData)) {
                    $message = 'Clinic visitor has been logged and checked out successfully.';
                    $clinicRecentVisits = get_recent_clinic_visits(50);
                    $activeVisitTab = 'search-autofill';
                } else {
                    $message = 'Failed to log visitor.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Visitor name is required.';
                $messageType = 'error';
            }
        }
    }

    if (isset($_POST['approve_logout'])) {
        $visitId = (int)$_POST['visit_id'];
        if (approve_clinic_logout($visitId)) {
            $message = 'Logout request approved and completed.';
            $pendingLogoutApprovals = get_pending_logout_approvals();
            $clinicRecentVisits = get_recent_clinic_visits(50);
            $stmt->execute();
            $allVisits = $stmt->fetchAll();
        } else {
            $message = 'Failed to approve logout request.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['manual_checkin_from_search'])) {
        $selectedUserId = (int)($_POST['selected_user_id'] ?? 0);
        $userSearchQuery = trim($_POST['user_search'] ?? '');

        if (empty($selectedUserId) || empty($userSearchQuery)) {
            $message = 'Please select a user from the search suggestions.';
            $messageType = 'error';
        } else {
            $selectedUser = null;
            foreach ($clinicUsers as $cuser) {
                if ($cuser['id'] === $selectedUserId) {
                    $selectedUser = $cuser;
                    break;
                }
            }

            if ($selectedUser) {
                $searchReason = trim($_POST['search_reason_choice'] ?? '');
                if ($searchReason === 'Other') {
                    $searchReason = trim($_POST['search_reason_other'] ?? '');
                }

                // Handle remarks (with support for "others" option)
                $searchRemarks = trim($_POST['search_remarks'] ?? '');
                if ($searchRemarks === 'others') {
                    $searchRemarks = trim($_POST['search_remarks_other'] ?? '');
                }

                // Get completion status
                $completionStatus = trim($_POST['search_completion_status'] ?? 'completed');

                $visitData = [
                    'user_id' => $selectedUser['id'],
                    'name' => $selectedUser['full_name'],
                    'visitor_type' => $selectedUser['role'],
                    'course_department' => $selectedUser['course_year'] ?? '',
                    'reason_for_visit' => $searchReason,
                    'checkin_method' => 'search_autofill',
                    'time_in' => date('Y-m-d H:i:s'),
                    'time_out' => date('Y-m-d H:i:s'),
                    'duration_minutes' => 0,
                    'status' => 'completed',
                    'priority' => 'low',
                    'remarks' => $searchRemarks,
                    'completion_status' => $completionStatus
                ];

                if (save_clinic_visit($visitData)) {
                    $message = 'Visitor logged and checked out via search successfully.';
                    $clinicRecentVisits = get_recent_clinic_visits(50);
                } else {
                    $message = 'Failed to log selected visitor.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Selected user not found.';
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visit Logs Management | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/clinic-header.css">
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

        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-light: #f8f9fa;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--clinic-maroon);
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background: var(--clinic-maroon);
            color: white;
        }

        .btn-primary:hover {
            transform: scale(1.05);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .alert-card {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-card.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-card.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .data-table th {
            background: var(--clinic-light);
            color: var(--clinic-maroon);
            font-weight: 600;
        }

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0;
            color: var(--clinic-maroon);
        }

        .section-subtitle {
            margin-top: 10px;
            color: #4b5563;
            font-size: 0.95rem;
            line-height: 1.6;
            max-width: 760px;
        }

        .page-header-panel {
            margin-bottom: 48px;
        }

        .status-waiting {
            color: #ffc107;
            font-weight: bold;
        }

        .status-in-consultation {
            color: #007bff;
            font-weight: bold;
        }

        .status-completed {
            color: #28a745;
            font-weight: bold;
        }

        .clinic-subtabs,
        .inventory-tab-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding: 10px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            justify-content: space-between;
        }

        /* Small-screen swipeable tabs + responsive search/autofill */
        @media (max-width: 720px) {
            .clinic-subtabs,
            .inventory-tab-bar {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scroll-snap-type: x mandatory;
                padding-bottom: 8px;
            }

            .clinic-subtabs::-webkit-scrollbar,
            .inventory-tab-bar::-webkit-scrollbar { display: none; }

            .clinic-subtab {
                flex: 0 0 auto;
                min-width: 170px;
                margin: 0;
                scroll-snap-align: start;
            }

            /* Make Search & Auto-Fill form stack nicely */
            #search-autofill form,
            #manual-logout form,
            .recent-filters {
                display: block !important;
            }

            #search-autofill .form-group,
            #manual-logout .form-group,
            .recent-filters .form-group {
                width: 100% !important;
                min-width: 0 !important;
                margin-bottom: 12px !important;
            }

            /* Ensure suggestions box stays within viewport */
            .user-suggestions {
                position: absolute;
                width: calc(100% - 2px);
                left: 0;
            }

            /* Recent Activity: convert table to stacked cards */
            #recent-activity .data-table {
                display: block !important;
                width: 100% !important;
                border: none !important;
                margin: 0 !important;
            }

            #recent-activity .data-table thead { display: none !important; }

            #recent-activity .data-table tbody,
            #recent-activity .data-table tr {
                display: block !important;
                width: 100% !important;
                margin-bottom: 12px !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 12px !important;
                padding: 12px !important;
                background: #fff !important;
                box-shadow: 0 2px 8px rgba(15,23,42,0.04) !important;
            }

            #recent-activity .data-table td {
                display: block !important;
                width: 100% !important;
                padding: 6px 0 !important;
                border: none !important;
                box-sizing: border-box !important;
            }

            #recent-activity .data-table td:before {
                content: attr(data-label) !important;
                display: block !important;
                font-size: 0.78rem !important;
                font-weight: 700 !important;
                color: #475569 !important;
                margin-bottom: 6px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.02em !important;
            }

            #recent-activity .data-table td[data-label="Actions"] > div {
                display: flex !important;
                flex-direction: column !important;
                gap: 8px !important;
            }

            #recent-activity .data-table td .btn {
                width: 100% !important;
            }
        }

        .clinic-subtab,
        .library-tab-button {
            border: 2px solid transparent;
            background: transparent;
            color: #334155;
            padding: 14px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 150px;
            text-align: center;
            flex: 1;
        }

        .clinic-subtab:hover,
        .library-tab-button:hover {
            background: var(--clinic-maroon);
            color: white;
            border-color: var(--clinic-gold);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .clinic-subtab.active,
        .library-tab-button.active {
            background: var(--clinic-maroon);
            color: white;
            border-color: var(--clinic-gold);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .clinic-subtab-content {
            padding: 20px;
            background: white;
            border-radius: 8px;
            margin-top: 1rem;
        }

        /* User Search Suggestions */
        .user-suggestions {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }

        .user-suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .user-suggestion-item:hover {
            background-color: #f8f9fa;
        }

        .user-suggestion-item:last-child {
            border-bottom: none;
        }

        .user-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .user-details {
            font-size: 0.9rem;
            color: #666;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background: var(--clinic-maroon);
            color: white;
        }

        .btn-primary:hover {
            transform: scale(1.05);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

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
            margin: 10% auto;
            padding: 20px;
            border-radius: 12px;
            width: 80%;
            max-width: 500px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid var(--clinic-maroon);
        }

        .stat-card h4 {
            margin: 0 0 10px 0;
            color: var(--clinic-maroon);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--clinic-maroon);
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            color: #666;
            font-size: 0.9rem;
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
                    <p>Clinic Head</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="nurse_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="school_announcements.php?service=clinic" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
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
                            <span class="nav-text">SSAA</span>
                        </a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $is_manage_open ? 'true' : 'false' ?>" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu <?= $is_manage_open ? 'open' : '' ?>" aria-hidden="<?= $is_manage_open ? 'false' : 'true' ?>">
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
                        <a href="clinic_visit_logs.php" class="active" data-tooltip="Visit Logs">
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
                    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Clinic Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Clinic Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                </div>
            </header>
            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                <?php if ($message): ?>
                    <div class="alert-card <?= $messageType ?>">
                        <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <section class="clinic-page-header">
                    <div class="clinic-header__content">
                        <span class="eyebrow">CLINIC VISITS</span>
                        <h1>Clinic Visit Management</h1>
                        <p class="clinic-header__subtitle">Review patient visits, monitor workflow, and keep clinic records accurate.</p>
                    </div>
                    <div class="clinic-header__art" aria-hidden="true"><i class="fa-solid fa-clipboard medical-clipboard"></i><i class="fa-solid fa-plus medical-cross"></i><i class="fa-solid fa-circle-check medical-check"></i><i class="fa-solid fa-stethoscope medical-stethoscope"></i><i class="fa-solid fa-location-pin medical-pin"></i><i class="fa-solid fa-leaf medical-leaf"></i></div>
                </section>

                <!-- Visit Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h4><i class="fa-solid fa-users"></i> Current Patients</h4>
                        <div class="stat-value"><?= count($currentVisits) ?></div>
                        <div class="stat-label">In clinic now</div>
                    </div>
                    <div class="stat-card">
                        <h4><i class="fa-solid fa-calendar-day"></i> Today's Visits</h4>
                        <div class="stat-value"><?= count($todayVisits) ?></div>
                        <div class="stat-label">Completed today</div>
                    </div>
                    <div class="stat-card">
                        <h4><i class="fa-solid fa-clock"></i> Pending Approvals</h4>
                        <div class="stat-value"><?= count($pendingLogoutApprovals) ?></div>
                        <div class="stat-label">Awaiting logout approval</div>
                    </div>
                </div>

                <!-- Clinic Visit Management Tabs -->
                <div class="clinic-subtabs inventory-tab-bar" role="tablist" aria-label="Visit navigation">
                            <button class="clinic-subtab library-tab-button active" type="button" data-tab="manual-logout" onclick="switchVisitTab('manual-logout', this)">Quick Logout (Manual)</button>
                            <button class="clinic-subtab library-tab-button" type="button" data-tab="recent-activity" onclick="switchVisitTab('recent-activity', this)">Recent Activity</button>
                            <button class="clinic-subtab library-tab-button" type="button" data-tab="search-autofill" onclick="switchVisitTab('search-autofill', this)">Search & Auto-Fill</button>
                        </div>

                        <div id="manual-logout" class="clinic-subtab-content" style="display: block;">
                            <h3>Quick Exit (No login required, immediate logout)</h3>
                            <form method="POST">
                                <input type="hidden" name="submit_clinic_visit_logout" value="1" />
                                <input type="hidden" name="active_tab" value="<?= htmlspecialchars($activeVisitTab) ?>" id="manualLogoutActiveTab" />
                                <div class="form-group">
                                    <label for="visitor_name">Name</label>
                                    <input type="text" id="visitor_name" name="visitor_name" placeholder="Full name" required autocapitalize="words" onblur="formatTitleCase(this)">
                                </div>
                                <div class="form-group">
                                    <label for="visitor_type">Type</label>
                                    <select id="visitor_type" name="visitor_type" required>
                                        <option value="visitor">Visitor</option>
                                        <option value="student">Student</option>
                                        <option value="teacher">Teacher</option>
                                    </select>
                                </div>

                                <!-- Student Fields -->
                                <div id="studentFields" style="display: none;">
                                    <div class="form-group">
                                        <label for="student_course">Course</label>
                                        <select id="student_course" name="student_course">
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
                                        <label for="ladderize_course">Ladderize Courses</label>
                                        <select id="ladderize_course" name="ladderize_course">
                                            <option value="">Select ladderize course</option>
                                            <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                            <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                            <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                            <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="student_year">Year Level</label>
                                        <select id="student_year" name="student_year">
                                            <option value="">Select year</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="font-size: 0.9rem; color: #555; margin-top: -10px; margin-bottom: 10px;">
                                        Choose only one: course or ladderize course.
                                    </div>
                                </div>

                                <!-- Teacher Fields -->
                                <div id="teacherFields" style="display: none;">
                                    <div class="form-group">
                                        <label for="teacher_course">Department / Course</label>
                                        <input type="text" id="teacher_course" name="teacher_course" placeholder="Department or course">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="visitor_reason_choice">Reason for Visit</label>
                                    <select id="visitor_reason_choice" name="visitor_reason_choice" required onchange="toggleOtherReason('visitor')">
                                        <option value="">Select reason</option>
                                        <option value="Common Cold">Common Cold</option>
                                        <option value="Headache">Headache</option>
                                        <option value="Fever">Fever</option>
                                        <option value="Stomach Pain">Stomach Pain</option>
                                        <option value="Allergies">Allergies</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group" id="visitor_reason_other_group" style="display: none;">
                                    <label for="visitor_reason_other">Other Reason</label>
                                    <input type="text" id="visitor_reason_other" name="visitor_reason_other" placeholder="Describe other reason">
                                </div>
                                <div class="form-group">
                                    <label for="visitor_remarks">Remarks</label>
                                    <select id="visitor_remarks" name="visitor_remarks" required onchange="toggleOtherRemarks('visitor')">
                                        <option value="">Select remarks</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Given Medication / Rested">Given Medication / Rested</option>
                                        <option value="Returned to Class/Work">Returned to Class/Work</option>
                                        <option value="Sent Home (Fit to Leave)">Sent Home (Fit to Leave)</option>
                                        <option value="Referred to Hospital">Referred to Hospital</option>
                                        <option value="Endorsed to Guardian/Parent (Hospitalization)">Endorsed to Guardian/Parent (Hospitalization)</option>
                                        <option value="Dispatched via Ambulance">Dispatched via Ambulance</option>
                                        <option value="For Further Medical Evaluation">For Further Medical Evaluation</option>
                                        <option value="For Observation">For Observation</option>
                                        <option value="Subject for Follow-Up">Subject for Follow-Up</option>
                                        <option value="others">Others</option>
                                    </select>
                                </div>
                                <div class="form-group" id="visitor_remarks_other_group" style="display: none;">
                                    <label for="visitor_remarks_other">Other Remarks</label>
                                    <input type="text" id="visitor_remarks_other" name="visitor_remarks_other" placeholder="Describe other remarks">
                                </div>
                                <div class="form-group">
                                    <label for="visitor_completion_status">Status</label>
                                    <select id="visitor_completion_status" name="visitor_completion_status" required>
                                        <option value="completed">Completed</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-sign-out-alt"></i> Log Out Visitor</button>
                            </form>
                        </div>


                        <div id="recent-activity" class="clinic-subtab-content" style="display: none;">
                            <h3>Recent Clinic Activity</h3>
                            <div class="recent-filters" style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
                                <div class="form-group" style="min-width: 190px; margin-bottom: 0;">
                                    <label for="recent_type_filter">Type</label>
                                    <select id="recent_type_filter" onchange="filterRecentActivity()">
                                        <option value="">All types</option>
                                    </select>
                                </div>
                                <div class="form-group" style="min-width: 220px; margin-bottom: 0;">
                                    <label for="recent_course_filter">Course / Department</label>
                                    <select id="recent_course_filter" onchange="filterRecentActivity()">
                                        <option value="">All courses</option>
                                    </select>
                                </div>
                                <div class="form-group" style="min-width: 220px; margin-bottom: 0;">
                                    <label for="recent_reason_filter">Reason</label>
                                    <select id="recent_reason_filter" onchange="filterRecentActivity()">
                                        <option value="">All reasons</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <?php if (!empty($clinicRecentVisits)): ?>
                                <div class="table-responsive">
                                    <table class="data-table" id="recentActivityTable">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Course / Department</th>
                                                <th>Reason</th>
                                                <th>Time Out</th>
                                                <th>Remarks</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($clinicRecentVisits as $visit): ?>
                                            <tr>
                                                <td data-label="Name"><?= htmlspecialchars($visit['name'] ?? $visit['patient_name']) ?></td>
                                                <td data-label="Type"><?= htmlspecialchars(ucfirst($visit['visitor_type'] ?? $visit['role'] ?? 'visitor')) ?></td>
                                                <td data-label="Course / Department"><?= htmlspecialchars(format_course_for_display($visit['course_department'] ?? $visit['course_year'] ?? '', $visit['user_id'] ?? null) ?: '') ?></td>
                                                <td data-label="Reason"><?= htmlspecialchars($visit['reason_for_visit']) ?></td>
                                                <td data-label="Time Out"><?= $visit['time_out'] ? htmlspecialchars(date('M d, Y H:i', strtotime($visit['time_out']))) : '-' ?></td>
                                                <td data-label="Remarks"><?= htmlspecialchars($visit['remarks'] ?? '-') ?></td>
                                                <td data-label="Status"><?= htmlspecialchars(ucfirst($visit['completion_status'] ?? 'pending')) ?></td>
                                                <td data-label="Actions">
                                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                                        <button type="button" class="btn btn-sm" style="background: #0066cc; color: white; padding: 5px 10px; margin-right: 4px; border-radius: 4px; border: none; cursor: pointer;" onclick="openEditRemarksModal(<?= (int)$visit['id'] ?>, '<?= htmlspecialchars(addslashes($visit['remarks'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars($visit['completion_status'] ?? 'pending', ENT_QUOTES) ?>')"><i class="fas fa-edit"></i> Edit</button>
                                                        <form method="POST" style="display:inline-block;">
                                                            <input type="hidden" name="delete_visit" value="1" />
                                                            <input type="hidden" name="visit_id" value="<?= (int)$visit['id'] ?>" />
                                                            <input type="hidden" name="active_tab" value="<?= htmlspecialchars($activeVisitTab) ?>" />
                                                            <button type="submit" class="btn btn-sm" style="background: #dc3545; color: white; padding: 5px 10px; border-radius: 4px; border: none; cursor: pointer;" onclick="return confirm('Are you sure you want to delete this activity?');"><i class="fas fa-trash"></i> Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>No recent visits recorded.</p>
                            <?php endif; ?>
                        </div>

                        <div id="search-autofill" class="clinic-subtab-content" style="display: none;">
                            <h3>Search & Auto-Fill (Account users)</h3>
                            <?php if ($activeVisitTab === 'search-autofill' && $message): ?>
                                <div class="alert-card <?= $messageType ?>">
                                    <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($message) ?>
                                </div>
                            <?php endif; ?>
                            <form method="POST" style="margin-bottom: 1rem;">
                                <input type="hidden" name="manual_checkin_from_search" value="1" />
                                <input type="hidden" name="active_tab" value="<?= htmlspecialchars($activeVisitTab) ?>" id="searchAutoFillActiveTab" />
                                <div class="form-group">
                                    <label for="user_search">Search User</label>
                                    <div style="position: relative;">
                                        <input type="text" id="user_search" name="user_search" placeholder="Type name, role, or course..." autocomplete="off" required>
                                        <input type="hidden" id="selected_user_id" name="selected_user_id" value="">
                                        <div id="user_suggestions" class="user-suggestions" style="display: none; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white; position: absolute; width: 100%; z-index: 1000;"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="search_reason_choice">Reason for Visit</label>
                                    <select id="search_reason_choice" name="search_reason_choice" required onchange="toggleOtherReason('search')">
                                        <option value="">Select reason</option>
                                        <option value="Common Cold">Common Cold</option>
                                        <option value="Headache">Headache</option>
                                        <option value="Fever">Fever</option>
                                        <option value="Stomach Pain">Stomach Pain</option>
                                        <option value="Allergies">Allergies</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group" id="search_reason_other_group" style="display: none;">
                                    <label for="search_reason_other">Other Reason</label>
                                    <input type="text" id="search_reason_other" name="search_reason_other" placeholder="Describe other reason">
                                </div>
                                <div class="form-group">
                                    <label for="search_remarks">Remarks</label>
                                    <select id="search_remarks" name="search_remarks" required onchange="toggleOtherRemarks('search')">
                                        <option value="">Select remarks</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Given Medication / Rested">Given Medication / Rested</option>
                                        <option value="Returned to Class/Work">Returned to Class/Work</option>
                                        <option value="Sent Home (Fit to Leave)">Sent Home (Fit to Leave)</option>
                                        <option value="Referred to Hospital">Referred to Hospital</option>
                                        <option value="Endorsed to Guardian/Parent (Hospitalization)">Endorsed to Guardian/Parent (Hospitalization)</option>
                                        <option value="Dispatched via Ambulance">Dispatched via Ambulance</option>
                                        <option value="For Further Medical Evaluation">For Further Medical Evaluation</option>
                                        <option value="For Observation">For Observation</option>
                                        <option value="Subject for Follow-Up">Subject for Follow-Up</option>
                                        <option value="others">Others</option>
                                    </select>
                                </div>
                                <div class="form-group" id="search_remarks_other_group" style="display: none;">
                                    <label for="search_remarks_other">Other Remarks</label>
                                    <input type="text" id="search_remarks_other" name="search_remarks_other" placeholder="Describe other remarks">
                                </div>
                                <div class="form-group">
                                    <label for="search_completion_status">Status</label>
                                    <select id="search_completion_status" name="search_completion_status" required>
                                        <option value="completed">Completed</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-user-check"></i> Log Visit & Logout</button>
                            </form>
                            <p style="font-size: 0.9rem; color: #6c757d;">Search for a user by name, role, or course. Select from suggestions to auto-fill details and mark the visit completed immediately.</p>
                        </div>
                    </div>
            </div>
        </main>
    </div>

    <!-- Complete Visit Modal -->
    <div id="completeVisitModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCompleteVisitModal()">&times;</span>
            <h2>Complete Patient Visit</h2>
            <form method="POST" id="completeVisitForm">
                <input type="hidden" name="active_tab" id="completeVisitActiveTab" value="<?= htmlspecialchars($activeVisitTab) ?>">
                <input type="hidden" name="visit_id" id="modal_visit_id">

                <div class="form-group">
                    <label for="modal_patient_name">Patient Name</label>
                    <input type="text" id="modal_patient_name" readonly>
                </div>

                <div class="form-group">
                    <label for="time_out">Time Out</label>
                    <input type="time" name="time_out" id="time_out" required>
                </div>

                <div class="form-group">
                    <label for="duration_minutes">Duration (minutes)</label>
                    <input type="number" name="duration_minutes" id="duration_minutes" min="1" required>
                </div>

                <button type="submit" name="complete_visit" class="btn btn-primary">
                    <i class="fa-solid fa-check"></i> Complete Visit
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Remarks Modal -->
    <div id="editRemarksModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditRemarksModal()">&times;</span>
            <h2>Edit Remarks & Status</h2>
            <form method="POST" id="editRemarksForm">
                <input type="hidden" name="update_remarks" value="1" />
                <input type="hidden" name="active_tab" value="<?= htmlspecialchars($activeVisitTab) ?>" />
                <input type="hidden" name="visit_id" id="edit_visit_id" />

                <div class="form-group">
                    <label for="edit_remarks">Remarks</label>
                    <select id="edit_remarks" name="remarks" required onchange="toggleEditOtherRemarks()">
                        <option value="">Select remarks</option>
                        <option value="Completed">Completed</option>
                        <option value="Given Medication / Rested">Given Medication / Rested</option>
                        <option value="Returned to Class/Work">Returned to Class/Work</option>
                        <option value="Sent Home (Fit to Leave)">Sent Home (Fit to Leave)</option>
                        <option value="Referred to Hospital">Referred to Hospital</option>
                        <option value="Endorsed to Guardian/Parent (Hospitalization)">Endorsed to Guardian/Parent (Hospitalization)</option>
                        <option value="Dispatched via Ambulance">Dispatched via Ambulance</option>
                        <option value="For Further Medical Evaluation">For Further Medical Evaluation</option>
                        <option value="For Observation">For Observation</option>
                        <option value="Subject for Follow-Up">Subject for Follow-Up</option>
                        <option value="others">Others</option>
                    </select>
                </div>

                <div class="form-group" id="edit_remarks_other_group" style="display: none;">
                    <label for="edit_remarks_other">Other Remarks (Custom)</label>
                    <input type="text" id="edit_remarks_other" name="remarks_other" placeholder="Enter custom remarks">
                </div>

                <div class="form-group">
                    <label for="edit_completion_status">Status</label>
                    <select id="edit_completion_status" name="completion_status" required>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js" defer></script>
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
    <script>
        let currentVisitTab = '<?= htmlspecialchars($activeVisitTab) ?>';

        function switchVisitTab(tabId, button) {
            // Hide all visit subtab contents
            const subtabs = document.querySelectorAll('.clinic-subtab-content');
            subtabs.forEach(subtab => subtab.style.display = 'none');

            // Remove active class from all visit subtab buttons
            const buttons = document.querySelectorAll('.clinic-subtab');
            buttons.forEach(btn => btn.classList.remove('active'));

            // Show selected subtab content
            const target = document.getElementById(tabId);
            if (target) {
                target.style.display = 'block';
            }

            // Add active class to clicked button
            if (button) {
                button.classList.add('active');
            } else {
                const defaultButton = document.querySelector(`.clinic-subtab[data-tab="${tabId}"]`);
                if (defaultButton) defaultButton.classList.add('active');
            }

            currentVisitTab = tabId;
            document.querySelectorAll('input[name="active_tab"]').forEach(input => input.value = tabId);
            window.history.replaceState(null, null, '#' + tabId);
        }

        function fillUserData() {
            // Pre-fill is automatic via select, no extra action needed
            const select = document.getElementById('selected_user_id');
            if (select.value) {
                const option = select.options[select.selectedIndex];
                // Data attributes are available in option, can be used if needed in future
            }
        }

        function formatTitleCase(element) {
            if (!element || !element.value) return;
            element.value = element.value
                .toLowerCase()
                .split(' ')
                .filter(part => part.length > 0)
                .map(part => part.charAt(0).toUpperCase() + part.slice(1))
                .join(' ');
        }

        function toggleOtherReason(prefix) {
            const choiceElement = document.getElementById(prefix + '_reason_choice');
            const otherGroup = document.getElementById(prefix + '_reason_other_group');
            const otherInput = document.getElementById(prefix + '_reason_other');
            if (!choiceElement || !otherGroup || !otherInput) return;
            if (choiceElement.value === 'Other') {
                otherGroup.style.display = 'block';
                otherInput.required = true;
            } else {
                otherGroup.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
        }

        function toggleOtherRemarks(prefix) {
            const remarksElement = document.getElementById(prefix + '_remarks');
            const otherGroup = document.getElementById(prefix + '_remarks_other_group');
            const otherInput = document.getElementById(prefix + '_remarks_other');
            if (!remarksElement || !otherGroup || !otherInput) return;
            if (remarksElement.value === 'others') {
                otherGroup.style.display = 'block';
                otherInput.required = true;
            } else {
                otherGroup.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
        }

        // Search and Auto-fill functionality
        let clinicUsers = <?php echo json_encode($clinicUsers); ?>;
        let searchTimeout;

        function initUserSearch() {
            const searchInput = document.getElementById('user_search');
            const suggestionsDiv = document.getElementById('user_suggestions');

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim().toLowerCase();

                if (query.length < 2) {
                    suggestionsDiv.style.display = 'none';
                    return;
                }

                searchTimeout = setTimeout(() => {
                    showUserSuggestions(query);
                }, 300);
            });

            searchInput.addEventListener('focus', function() {
                const query = this.value.trim().toLowerCase();
                if (query.length >= 2) {
                    showUserSuggestions(query);
                }
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                    suggestionsDiv.style.display = 'none';
                }
            });
        }

        function showUserSuggestions(query) {
            const suggestionsDiv = document.getElementById('user_suggestions');
                const filteredUsers = clinicUsers.filter(user => {
                const fullName = user.full_name.toLowerCase();
                const role = (user.role || '').toLowerCase();
                const course = (user.course_display || '').toLowerCase();
                return fullName.includes(query) || role.includes(query) || course.includes(query);
            });

            if (filteredUsers.length === 0) {
                suggestionsDiv.style.display = 'none';
                return;
            }

                suggestionsDiv.innerHTML = filteredUsers.map(user => `
                <div class="user-suggestion-item" onclick="selectUser(${user.id}, '${user.full_name.replace(/'/g, "\\'")}', '${user.role}', '${(user.course_display || '').replace(/'/g, "\\'")}')">
                    <div class="user-name">${user.full_name}</div>
                    <div class="user-details">${(user.role || '').charAt(0).toUpperCase() + (user.role || '').slice(1)} ${user.course_display ? '- ' + user.course_display : ''}</div>
                </div>
            `).join('');

            suggestionsDiv.style.display = 'block';
        }

        function selectUser(userId, fullName, role, course) {
            document.getElementById('user_search').value = fullName;
            document.getElementById('selected_user_id').value = userId;
            document.getElementById('user_suggestions').style.display = 'none';

            // Optional: Show selected user info
            console.log(`Selected: ${fullName} (${role}) - ${course}`);
        }

        // Initialize search when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initUserSearch();
            initVisitorTypeFields();
            populateRecentActivityFilters();
            switchVisitTab(currentVisitTab, document.querySelector(`[data-tab="${currentVisitTab}"]`));
            enableVisitTabSwipe();
        });

        // Visitor type field management
        function initVisitorTypeFields() {
            const visitorTypeSelect = document.getElementById('visitor_type');
            const studentFields = document.getElementById('studentFields');
            const teacherFields = document.getElementById('teacherFields');
            const studentCourseSelect = document.getElementById('student_course');
            const ladderizeCourseSelect = document.getElementById('ladderize_course');
            const studentYearSelect = document.getElementById('student_year');

            const twoYearCourses = ['ACT', 'ABK', 'AHM', 'ATM'];

            function populateYearOptions() {
                const selectedLadderize = ladderizeCourseSelect.value;
                const selectedCourse = studentCourseSelect.value;
                const courseToCheck = selectedLadderize || selectedCourse;
                const years = twoYearCourses.includes(courseToCheck) ? [1, 2] : [1, 2, 3, 4];
                studentYearSelect.innerHTML = '<option value="">Select year</option>' + years.map(year => {
                    return `<option value="${year}">${year}</option>`;
                }).join('');
            }

            function handleMutuallyExclusiveCourses() {
                if (studentCourseSelect.value) {
                    ladderizeCourseSelect.value = '';
                    ladderizeCourseSelect.disabled = true;
                } else {
                    ladderizeCourseSelect.disabled = false;
                }

                if (ladderizeCourseSelect.value) {
                    studentCourseSelect.value = '';
                    studentCourseSelect.disabled = true;
                } else {
                    studentCourseSelect.disabled = false;
                }
            }

            function updateFields() {
                const visitorType = visitorTypeSelect.value;
                const isStudent = visitorType === 'student';
                const isTeacher = visitorType === 'teacher';

                studentFields.style.display = isStudent ? 'block' : 'none';
                teacherFields.style.display = isTeacher ? 'block' : 'none';

                // Set required attributes
                studentCourseSelect.required = isStudent;
                ladderizeCourseSelect.required = false;
                studentYearSelect.required = isStudent;
                document.getElementById('teacher_course').required = isTeacher;

                // Reset fields when changing type
                if (!isStudent) {
                    studentCourseSelect.value = '';
                    ladderizeCourseSelect.value = '';
                    studentYearSelect.innerHTML = '<option value="">Select year</option>';
                }
                if (!isTeacher) {
                    document.getElementById('teacher_course').value = '';
                }

                handleMutuallyExclusiveCourses();
                if (studentCourseSelect.value || ladderizeCourseSelect.value) {
                    populateYearOptions();
                }
            }

            visitorTypeSelect.addEventListener('change', updateFields);
            studentCourseSelect.addEventListener('change', function() {
                handleMutuallyExclusiveCourses();
                populateYearOptions();
            });
            ladderizeCourseSelect.addEventListener('change', function() {
                handleMutuallyExclusiveCourses();
                populateYearOptions();
            });

            // Initialize
            updateFields();
        }

        function populateRecentActivityFilters() {
            const table = document.getElementById('recentActivityTable');
            if (!table) return;

            const typeSelect = document.getElementById('recent_type_filter');
            const courseSelect = document.getElementById('recent_course_filter');
            const reasonSelect = document.getElementById('recent_reason_filter');

            const types = new Set();
            const courses = new Set();
            const fixedReasons = ['common cold', 'headache', 'fever', 'stomach pain', 'allergies'];

            typeSelect.innerHTML = '<option value="">All types</option>';
            courseSelect.innerHTML = '<option value="">All courses</option>';
            reasonSelect.innerHTML = '<option value="">All reasons</option>';

            table.querySelectorAll('tbody tr').forEach(row => {
                const type = row.cells[1].textContent.trim();
                const course = row.cells[2].textContent.trim();

                if (type) types.add(type);
                if (course) courses.add(course);
            });

            Array.from(types).sort().forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                typeSelect.appendChild(option);
            });

            Array.from(courses).sort().forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                courseSelect.appendChild(option);
            });

            ['Common Cold', 'Headache', 'Fever', 'Stomach Pain', 'Allergies', 'Other'].forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                reasonSelect.appendChild(option);
            });
        }

        function filterRecentActivity() {
            const typeFilter = document.getElementById('recent_type_filter').value;
            const courseFilter = document.getElementById('recent_course_filter').value;
            const reasonFilter = document.getElementById('recent_reason_filter').value;
            const table = document.getElementById('recentActivityTable');
            if (!table) return;

            const standardReasons = new Set(['common cold', 'headache', 'fever', 'stomach pain', 'allergies']);

            table.querySelectorAll('tbody tr').forEach(row => {
                const type = row.cells[1].textContent.trim();
                const course = row.cells[2].textContent.trim();
                const reason = row.cells[3].textContent.trim();
                const normalizedReason = reason.toLowerCase();
                let show = true;

                if (typeFilter && type !== typeFilter) {
                    show = false;
                }
                if (courseFilter && course !== courseFilter) {
                    show = false;
                }
                if (reasonFilter) {
                    if (reasonFilter === 'Other') {
                        show = !standardReasons.has(normalizedReason) && normalizedReason !== '';
                    } else {
                        show = normalizedReason === reasonFilter.toLowerCase();
                    }
                }

                row.style.display = show ? '' : 'none';
            });
        }

        function openCompleteVisitModal(visitId, patientName) {
            document.getElementById('modal_visit_id').value = visitId;
            document.getElementById('modal_patient_name').value = patientName;

            // Set current time as default
            const now = new Date();
            const timeString = now.toTimeString().slice(0, 5);
            document.getElementById('time_out').value = timeString;

            document.getElementById('completeVisitModal').style.display = 'block';
        }

        function closeCompleteVisitModal() {
            document.getElementById('completeVisitModal').style.display = 'none';
        }

        function openEditRemarksModal(visitId, remarks, status) {
            document.getElementById('edit_visit_id').value = visitId;
            const remarksSelect = document.getElementById('edit_remarks');
            const statusSelect = document.getElementById('edit_completion_status');
            
            // Try to match existing remarks value
            let foundMatch = false;
            for (let option of remarksSelect.options) {
                if (option.value === remarks) {
                    remarksSelect.value = remarks;
                    foundMatch = true;
                    break;
                }
            }
            
            // If no match found, treat as "others"
            if (!foundMatch) {
                remarksSelect.value = 'others';
                document.getElementById('edit_remarks_other').value = remarks;
                document.getElementById('edit_remarks_other_group').style.display = 'block';
            } else {
                document.getElementById('edit_remarks_other').value = '';
                document.getElementById('edit_remarks_other_group').style.display = 'none';
            }
            
            statusSelect.value = status;
            document.getElementById('editRemarksModal').style.display = 'block';
        }

        function closeEditRemarksModal() {
            document.getElementById('editRemarksModal').style.display = 'none';
        }

        function toggleEditOtherRemarks() {
            const remarksSelect = document.getElementById('edit_remarks');
            const otherGroup = document.getElementById('edit_remarks_other_group');
            const otherInput = document.getElementById('edit_remarks_other');
            
            if (remarksSelect.value === 'others') {
                otherGroup.style.display = 'block';
                otherInput.required = true;
            } else {
                otherGroup.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
        }

        // Handle form submission for edit remarks
        document.getElementById('editRemarksForm').addEventListener('submit', function(e) {
            const remarksSelect = document.getElementById('edit_remarks');
            const otherInput = document.getElementById('edit_remarks_other');
            
            // If "others" is selected, use custom input
            if (remarksSelect.value === 'others') {
                if (otherInput.value.trim() === '') {
                    e.preventDefault();
                    alert('Please enter custom remarks.');
                    return false;
                }
                // Create hidden input to override the select value
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'remarks';
                hiddenInput.value = otherInput.value.trim();
                this.appendChild(hiddenInput);
                // Remove the select from submission
                remarksSelect.name = '';
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const completeModal = document.getElementById('completeVisitModal');
            const editModal = document.getElementById('editRemarksModal');
            if (event.target == completeModal) {
                completeModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
        }

        /* Swipe handling for visit tabs (only active between 320px and 720px) */
        function enableVisitTabSwipe() {
            const minW = 320, maxW = 720;
            const tabsOrder = ['manual-logout','recent-activity','search-autofill'];
            let startX = 0, startY = 0, tracking = false;

            function onTouchStart(e) {
                if (window.innerWidth > maxW || window.innerWidth < minW) return;
                const t = e.touches ? e.touches[0] : e;
                startX = t.clientX;
                startY = t.clientY;
                tracking = true;
            }

            function onTouchMove(e) {
                if (!tracking) return;
                // no-op; we inspect delta on end
            }

            function onTouchEnd(e) {
                if (!tracking) return;
                const t = (e.changedTouches && e.changedTouches[0]) || e;
                const dx = t.clientX - startX;
                const dy = t.clientY - startY;
                tracking = false;

                // ignore mostly-vertical gestures
                if (Math.abs(dy) > Math.abs(dx)) return;

                const threshold = 50; // px
                if (dx <= -threshold) {
                    // swipe left -> next tab
                    navigateVisitTab(1);
                } else if (dx >= threshold) {
                    // swipe right -> previous tab
                    navigateVisitTab(-1);
                }
            }

            function navigateVisitTab(direction) {
                const idx = tabsOrder.indexOf(currentVisitTab);
                if (idx === -1) return;
                let next = idx + direction;
                if (next < 0) next = 0;
                if (next >= tabsOrder.length) next = tabsOrder.length - 1;
                if (next === idx) return;
                const nextId = tabsOrder[next];
                const btn = document.querySelector(`.clinic-subtab[data-tab="${nextId}"]`);
                switchVisitTab(nextId, btn);
                // also scroll tab buttons into view
                if (btn && btn.scrollIntoView) btn.scrollIntoView({behavior:'smooth', inline:'center'});
            }

            const scroller = document.querySelector('.main-scroll') || document.body;
            scroller.addEventListener('touchstart', onTouchStart, {passive:true});
            scroller.addEventListener('touchmove', onTouchMove, {passive:true});
            scroller.addEventListener('touchend', onTouchEnd, {passive:true});
        }
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
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