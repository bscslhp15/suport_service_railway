<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

if ($user['role'] !== 'teacher' || $user['head_service'] !== 'guidance') {
    header('Location: guidance_home.php');
    exit;
}

// AJAX: Fetch student case history - MUST be before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_student_cases'])) {
    require_once __DIR__ . '/../includes/functions.php';
    header('Content-Type: application/json');
    $pdo = get_db();
    $studentId = (int)($_GET['student_id'] ?? 0);
    
    if ($studentId > 0) {
        // Get student info
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            // Normalize course display for client-side use
            $student['course_display'] = format_course_for_display($student['course_year'] ?? '', $student['id'] ?? null);
            // Get cases
            $stmt = $pdo->prepare("
                SELECT gc.*, u_reporter.full_name as reporter_name
                FROM guidance_cases gc
                LEFT JOIN users u_reporter ON gc.reporter_user_id = u_reporter.id
                WHERE gc.reported_student_id = ?
                ORDER BY gc.created_at DESC
            ");
            $stmt->execute([$studentId]);
            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'student' => $student,
                'cases' => $cases
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    }
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
ensure_guidance_schema();

require_once __DIR__ . '/../AI CHAT BOT/chat_widget.php';

$pdo = get_db();
$message = '';
$messageType = 'success';
$studentSearchResult = null;
$studentCases = [];

// Search for student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_student'])) {
    $studentSearch = trim($_POST['student_search'] ?? '');
    if (!empty($studentSearch)) {
        // Prioritize exact student_id matches first, then full_name matches
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE role = 'student' AND (
                student_id LIKE ? OR 
                full_name LIKE ?
            )
            ORDER BY 
                CASE 
                    WHEN student_id = ? THEN 1
                    WHEN student_id LIKE ? THEN 2  
                    WHEN full_name LIKE ? THEN 3
                    ELSE 4
                END,
                LENGTH(full_name) ASC
            LIMIT 1
        ");
        $exactMatch = $studentSearch;
        $partialMatch = '%' . $studentSearch . '%';
        $stmt->execute([$partialMatch, $partialMatch, $exactMatch, $exactMatch, $partialMatch]);
        $studentSearchResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($studentSearchResult) {
            // Get all cases for this student (both solved and unsolved)
            $stmt = $pdo->prepare("
                SELECT gc.*, u_reporter.full_name as reporter_name
                FROM guidance_cases gc
                LEFT JOIN users u_reporter ON gc.reporter_user_id = u_reporter.id
                WHERE gc.reported_student_id = ?
                ORDER BY gc.created_at DESC
            ");
            $stmt->execute([$studentSearchResult['id']]);
            $studentCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $message = 'Student not found.';
            $messageType = 'error';
        }
    }
}

// Generate Good Moral Certificate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_gmc'])) {
    $studentId = (int)($_POST['student_id'] ?? 0);
    if ($studentId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            // Check guidance status
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as flagged FROM guidance_cases
                WHERE reported_student_id = ? AND status NOT IN ('closed')
                AND internal_resolution IN ('escalated_case', 'under_monitoring')
            ");
            $stmt->execute([$studentId]);
            $flagged = (int)$stmt->fetchColumn();

            $gmcStatus = $flagged > 0 ? 'flagged' : 'eligible';

            // Store GMC status
            $stmt = $pdo->prepare("
                INSERT INTO good_moral_records (student_id, issued_by, status, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE status = ?, issued_by = ?, updated_at = NOW()
            ");
            $stmt->execute([$studentId, $user['id'], $gmcStatus, $gmcStatus, $user['id']]);

            $message = 'Good Moral Certificate status: ' . strtoupper($gmcStatus);
            $messageType = $gmcStatus === 'eligible' ? 'success' : 'error';

            // Prepare email data for client-side JavaScript
            $emailData = [
                'send' => true,
                'user_name' => $student['full_name'],
                'email' => $student['email'] ?? '',
                'portal_link' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/guidance_dashboard.php',
                'request_id' => 'GMC-' . time() . '-' . $studentId,
            ];

            // Refresh case history after GMC generation
            $stmt = $pdo->prepare("
                SELECT gc.*, u_reporter.full_name as reporter_name
                FROM guidance_cases gc
                LEFT JOIN users u_reporter ON gc.reporter_user_id = u_reporter.id
                WHERE gc.reported_student_id = ?
                ORDER BY gc.created_at DESC
            ");
            $stmt->execute([$studentId]);
            $studentCases = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Update notification state for student dashboard
            $stmt = $pdo->prepare("UPDATE good_moral_records SET notification_status = 'approved', notification_sent_at = NOW(), notification_read_at = NULL WHERE student_id = ?");
            $stmt->execute([$studentId]);

            $message = 'Good Moral request submitted successfully and the student has been notified by email.';
            $messageType = 'success';
        }
    }
}

// Place Good Moral request on HOLD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hold_request'])) {
    $studentId = (int)($_POST['student_id'] ?? 0);
    if ($studentId > 0) {
        // Mark or create a record with status 'on_hold'
        $stmt = $pdo->prepare("
            INSERT INTO good_moral_records (student_id, issued_by, status, created_at)
            VALUES (?, ?, 'on_hold', NOW())
            ON DUPLICATE KEY UPDATE status = 'on_hold', issued_by = ?, updated_at = NOW()
        ");
        $stmt->execute([$studentId, $user['id'], $user['id']]);

        // Get student details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        // Prepare email data for client-side JavaScript
        $emailData = [
            'send' => true,
            'user_name' => $student['full_name'] ?? '',
            'email' => $student['email'] ?? '',
            'portal_link' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/guidance_dashboard.php',
            'request_id' => 'GMC-HOLD-' . time() . '-' . $studentId,
            'template' => 'hold'
        ];

        // Update notification state for student dashboard
        $stmt = $pdo->prepare("UPDATE good_moral_records SET notification_status = 'hold', notification_sent_at = NOW(), notification_read_at = NULL WHERE student_id = ?");
        $stmt->execute([$studentId]);

        $message = 'Good Moral request has been placed on HOLD and the student has been notified by email.';
        $messageType = 'error';

        // Refresh case history after action
        $stmt = $pdo->prepare("
            SELECT gc.*, u_reporter.full_name as reporter_name
            FROM guidance_cases gc
            LEFT JOIN users u_reporter ON gc.reporter_user_id = u_reporter.id
            WHERE gc.reported_student_id = ?
            ORDER BY gc.created_at DESC
        ");
        $stmt->execute([$studentId]);
        $studentCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get all students with GMC status
$stmt = $pdo->query("
    SELECT u.id, u.full_name, u.student_id, u.course_year,
           g.status as gmc_status, g.created_at as gmc_date
    FROM users u
    LEFT JOIN good_moral_records g ON u.id = g.student_id
    WHERE u.role = 'student'
    ORDER BY u.full_name
");
$allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending good moral requests
$stmt = $pdo->prepare("
    SELECT g.id, g.status, g.created_at, g.updated_at, 
           u.id as user_id, u.full_name, u.student_id, u.course_year, u.email,
           COUNT(gc.id) as active_cases
    FROM good_moral_records g
    JOIN users u ON g.student_id = u.id
    LEFT JOIN guidance_cases gc ON u.id = gc.reported_student_id 
        AND gc.status NOT IN ('closed')
        AND gc.internal_resolution IN ('escalated_case', 'under_monitoring')
    WHERE g.status IN ('pending', 'hold')
    GROUP BY g.id
    ORDER BY g.created_at DESC
");
$stmt->execute();
$pendingGoodMoralRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Good Moral Certificate | Guidance System</title>
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

        .case-page-header { position: relative; min-height: 245px; display: flex; align-items: center; overflow: hidden; margin: 0 0 24px; padding: 42px 48px 30px; background: #f8f1e7; border: 0; border-radius: 0 0 24px 24px; }
        .case-page-header::before { content: ''; position: absolute; z-index: 0; inset: 0 auto 0 0; width: 3px; background: #861b17; }
        .case-page-header > div:first-child { position: relative; z-index: 2; width: 65%; }
        .case-page-header .eyebrow { margin: 0; color: #861b17 !important; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 3px; line-height: 1; text-transform: uppercase; }
        .case-page-header h1 { margin: 14px 0 12px; color: #111 !important; font-family: Georgia, serif; font-size: clamp(36px, 4vw, 62px); font-weight: 600; letter-spacing: 0; line-height: 1.05; }
        .case-page-header .dashboard-subtitle { max-width: 760px; margin: 0; color: #34302d !important; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; }
        .case-header__art { position: absolute; z-index: 1; top: 0; right: 0; width: 43%; height: 100%; color: #d7a58d; opacity: .78; }
        .case-header__art::before { content: ''; position: absolute; right: 13%; bottom: 2%; width: 82%; height: 30%; background: radial-gradient(ellipse at center, rgba(235, 205, 167, .48) 0 42%, transparent 43%); border-radius: 50%; }
        .case-header__art::after { content: ''; position: absolute; top: 23%; left: 4%; width: 76%; height: 42%; border: 1px solid rgba(215, 165, 141, .45); border-left-color: transparent; border-radius: 50%; transform: rotate(-13deg); }
        .case-header__art i { position: absolute; z-index: 2; font-family: 'Font Awesome 6 Free'; font-size: 28px; font-style: normal; font-weight: 900; }
        .case-header__art .art-chat { top: 57%; left: 2%; padding: 12px 14px; border: 2px solid rgba(215, 165, 141, .7); border-radius: 9px; font-size: 15px; }
        .case-header__art .art-file { top: 17%; left: 39%; padding: 16px 19px; border: 2px solid rgba(215, 165, 141, .58); border-radius: 9px; box-shadow: 20px 10px 0 -1px #f8f1e7, 20px 10px 0 1px rgba(215, 165, 141, .48), 36px 20px 0 -1px #f8f1e7, 36px 20px 0 1px rgba(215, 165, 141, .42); font-size: 35px; }
        .case-header__art .art-card { top: 35%; left: 47%; padding: 13px 16px; border: 2px solid rgba(215, 165, 141, .65); border-radius: 8px; background: rgba(248, 241, 231, .7); font-size: 28px; }
        .case-header__art .art-check { top: 22%; right: 7%; padding: 12px; border: 2px solid #e3b968; border-radius: 50%; color: #e3b968; font-size: 21px; }
        .case-header__art .art-pin { bottom: 10%; left: 42%; width: 31px; height: 31px; color: transparent; background: #cf9589; border-radius: 50% 50% 50% 0; font-size: 0; transform: rotate(-45deg); }
        .case-header__art .art-pin::after { content: ''; position: absolute; top: 9px; left: 9px; width: 13px; height: 13px; background: #f8f1e7; border-radius: 50%; }
        .case-header__art .art-dots { right: 25%; bottom: 12%; color: #d7a58d; font-size: 28px; }
        @media (max-width: 768px) { .case-page-header { min-height: 245px; padding: 32px 24px 110px; } .case-page-header > div:first-child { width: 100%; } .case-page-header h1 { font-size: 36px; } .case-header__art { width: 55%; opacity: .35; } }

        .card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; background: white; margin-bottom: 16px; }
        .card h3 { margin-top: 0; color: #1f2937; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .button { padding: 10px 16px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        .students-table td[data-label="Action"] {
            white-space: nowrap;
        }
        .students-table td[data-label="Action"] .button {
            border-radius: 9px;
            font-weight: 600;
            transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        }
        .students-table td[data-label="Action"] .button:hover {
            transform: translateY(-1px);
        }
        .students-table td[data-label="Action"] .button i {
            margin-right: 5px;
        }
        .students-table td[data-label="Action"] .good-moral-view {
            background: #fbf4e4 !important;
            border: 1px solid #d8b8a8 !important;
            color: #5c1f23 !important;
        }
        .students-table td[data-label="Action"] .good-moral-view:hover {
            background: #f4e2d9 !important;
            color: #8f0011 !important;
        }
        .students-table td[data-label="Action"] .good-moral-approve {
            background: linear-gradient(135deg, #68151c, #9e3030) !important;
            border: 1px solid #68151c !important;
            color: #ffffff !important;
        }
        .students-table td[data-label="Action"] .good-moral-approve:hover {
            background: linear-gradient(135deg, #531017, #8f0011) !important;
        }
        .students-table td[data-label="Action"] .good-moral-hold {
            background: #f9e5df !important;
            border: 1px solid #d8a99b !important;
            color: #8f0011 !important;
        }
        .students-table td[data-label="Action"] .good-moral-hold:hover {
            background: #f1d1c8 !important;
            color: #68151c !important;
        }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
        .alert-error { background: #fee2e2; border-left: 4px solid #ef4444; color: #7f1d1d; }
        .student-result { background: #f3f4f6; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
        .metric-card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; background: white; }
        .metric-card h3 { margin: 0 0 8px 0; color: #6b7280; font-size: 14px; font-weight: 500; text-transform: uppercase; }
        .metric-card .value { font-size: 28px; font-weight: 700; color: #1f2937; }
        .students-table { width: 100%; border-collapse: collapse; }
        .students-table th, .students-table td { padding: 12px 10px; border: 1px solid #e2e8f0; text-align: left; }
        .students-table th { background: #f8fafc; font-weight: 600; }
        .students-table tr:hover { background: #f9fafb; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-eligible { background: #d1fae5; color: #065f46; }
        .badge-flagged { background: #fee2e2; color: #7f1d1d; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-open { background: #dbeafe; color: #1e40af; }
        .badge-in_progress { background: #fef3c7; color: #92400e; }
        .badge-resolved { background: #d1fae5; color: #065f46; }
        .badge-closed { background: #e5e7eb; color: #374151; }
        .badge-escalated { background: #fee2e2; color: #7f1d1d; }
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
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
                        <a href="good_moral.php" class="active" data-tooltip="Good Moral">
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
                <section class="dashboard-intro case-page-header">
                    <div>
                        <span class="eyebrow">GOOD MORAL CERTIFICATE</span>
                        <h1>Generate Good Moral Certificates</h1>
                        <p class="dashboard-subtitle">Evaluate student eligibility, verify case history, and generate certified Good Moral documents for qualified students.</p>
                    </div>
                    <div class="case-header__art" aria-hidden="true">
                        <i class="fa-solid fa-message art-chat"></i>
                        <i class="fa-solid fa-folder-open art-file"></i>
                        <i class="fa-solid fa-address-card art-card"></i>
                        <i class="fa-solid fa-circle-check art-check"></i>
                        <i class="fa-solid fa-location-pin art-pin"></i>
                        <i class="fa-solid fa-leaf art-dots"></i>
                    </div>
                </section>

                    <?php if ($message !== ''): ?>
                        <div class="alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Search / Good Moral Requests - TABBED -->
                    <div class="card">
                        <div class="tabs-container">
                            <div class="tabs-header">
                                <button class="tab-button active" data-tab="tab-search">
                                    <i class="fa-solid fa-magnifying-glass"></i> Search Student Case History
                                </button>
                                <button class="tab-button" data-tab="tab-requests">
                                    <i class="fa-solid fa-file-request"></i> Pending Good Moral Requests
                                </button>
                            </div>

                            <!-- Tab 1: Search Student Case History -->
                            <div id="tab-search" class="tab-content active">
                                <h3><i class="fa-solid fa-magnifying-glass"></i> Search Student Case History</h3>
                                <form method="post">
                                    <input type="hidden" name="search_student" value="1">
                                    <div class="form-group">
                                        <label>Student ID or Name:</label>
                                        <input type="text" name="student_search" placeholder="Enter student name or ID to view their complete case history" />
                                    </div>
                                    <button type="submit" class="button btn-primary" style="margin-top: 10px;">Search</button>
                                </form>

                                <?php if ($studentSearchResult): ?>
                                    <div class="student-result">
                                        <h4>👤 <?= htmlspecialchars($studentSearchResult['full_name']) ?></h4>
                                        <p><strong>Student ID:</strong> <?= htmlspecialchars($studentSearchResult['student_id'] ?? 'N/A') ?></p>
                                        <p><strong>Course/Year:</strong> <?= htmlspecialchars(format_course_for_display($studentSearchResult['course_year'] ?? '', $studentSearchResult['id'] ?? null) ?: 'N/A') ?></p>

                                        <h5 style="margin-top: 20px; margin-bottom: 10px;">📋 Student Case History</h5>
                                        <?php if (!empty($studentCases)): ?>
                                            <div style="overflow-x: auto; margin-bottom: 16px;">
                                                <table class="students-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Case ID</th>
                                                            <th>Status</th>
                                                            <th>Type of Concern</th>
                                                            <th>Reporter</th>
                                                            <th>Created Date</th>
                                                            <th>Resolution</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($studentCases as $case): ?>
                                                            <tr>
                                                                <td data-label="Case ID"><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                                <td data-label="Status">
                                                                    <span class="badge badge-<?= $case['status'] ?>">
                                                                        <?= ucfirst(str_replace('_', ' ', $case['status'])) ?>
                                                                    </span>
                                                                </td>
                                                                <td data-label="Type of Concern"><?= htmlspecialchars($case['type_of_concern']) ?></td>
                                                                <td data-label="Reporter"><?= htmlspecialchars($case['reporter_name'] ?? 'Unknown') ?></td>
                                                                <td data-label="Created Date"><?= date('M d, Y', strtotime($case['created_at'])) ?></td>
                                                                <td data-label="Resolution">
                                                                    <?php if ($case['external_resolution'] && $case['external_resolution'] !== 'none'): ?>
                                                                        <span style="font-size: 12px; color: #065f46;"><?= htmlspecialchars($case['external_resolution']) ?></span>
                                                                    <?php else: ?>
                                                                        <span style="font-size: 12px; color: #6b7280;">Pending</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p style="color: #6b7280; font-style: italic;">No cases found for this student.</p>
                                        <?php endif; ?>

                                        <div style="margin-top: 16px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="generate_gmc" value="1">
                                                <input type="hidden" name="student_id" value="<?= (int)$studentSearchResult['id'] ?>">
                                                <button type="submit" class="button btn-primary">Generate Good Moral Certificate</button>
                                            </form>

                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="hold_request" value="1">
                                                <input type="hidden" name="student_id" value="<?= (int)$studentSearchResult['id'] ?>">
                                                <button type="submit" class="button btn-secondary" onclick="return confirm('Place Good Moral request on HOLD for this student?');">Hold</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Tab 2: Good Moral Requests -->
                            <div id="tab-requests" class="tab-content">
                                <h3><i class="fa-solid fa-file-request"></i> Pending Good Moral Requests</h3>
                                <p style="color: #6b7280; margin-bottom: 16px;">Students who have submitted Good Moral Certificate requests pending your evaluation.</p>
                                
                                <?php if (empty($pendingGoodMoralRequests)): ?>
                                    <div style="text-align: center; padding: 40px 20px; background: #f9fafb; border-radius: 8px;">
                                        <i class="fa-solid fa-inbox" style="font-size: 48px; color: #d1d5db; margin-bottom: 12px;"></i>
                                        <p style="color: #6b7280; font-size: 16px;">No pending Good Moral requests at the moment.</p>
                                    </div>
                                <?php else: ?>
                                    <div style="overflow-x: auto;">
                                        <table class="students-table">
                                            <thead>
                                                <tr>
                                                    <th>Student ID</th>
                                                    <th>Student Name</th>
                                                    <th>Course/Year</th>
                                                    <th>Request Status</th>
                                                    <th>Active Cases</th>
                                                    <th>Request Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pendingGoodMoralRequests as $request): ?>
                                                    <tr>
                                                        <td data-label="Student ID"><strong><?= htmlspecialchars($request['student_id']) ?></strong></td>
                                                        <td data-label="Student Name"><?= htmlspecialchars($request['full_name']) ?></td>
                                                        <td data-label="Course/Year"><?= htmlspecialchars(format_course_for_display($request['course_year'] ?? '', $request['user_id'] ?? null) ?: 'N/A') ?></td>
                                                        <td data-label="Request Status">
                                                            <span class="badge <?= $request['status'] === 'hold' ? 'badge-warning' : 'badge-info' ?>">
                                                                <?= $request['status'] === 'hold' ? '⏸️ On Hold' : '⏳ Pending' ?>
                                                            </span>
                                                        </td>
                                                        <td data-label="Active Cases">
                                                            <?php if ((int)$request['active_cases'] > 0): ?>
                                                                <span style="color: #dc2626; font-weight: 600;">
                                                                    <i class="fa-solid fa-exclamation-circle"></i> <?= (int)$request['active_cases'] ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span style="color: #059669;">✓ Clean</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td data-label="Request Date"><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                                        <td data-label="Action">
                                                            <button type="button" class="button btn-secondary good-moral-view" style="padding: 6px 12px; font-size: 12px; margin-right: 5px;" onclick="openCaseHistoryModal(<?= (int)$request['user_id'] ?>, '<?= htmlspecialchars(addslashes($request['full_name'])) ?>')">
                                                                <i class="fa-solid fa-eye"></i> View
                                                            </button>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="generate_gmc" value="1">
                                                                <input type="hidden" name="student_id" value="<?= (int)$request['user_id'] ?>">
                                                                <button type="submit" class="button btn-primary good-moral-approve" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-check"></i> Approve</button>
                                                            </form>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="hold_request" value="1">
                                                                <input type="hidden" name="student_id" value="<?= (int)$request['user_id'] ?>">
                                                                <button type="submit" class="button btn-secondary good-moral-hold" style="padding: 6px 12px; font-size: 12px;" onclick="return confirm('Place Good Moral request on HOLD for this student?');"><i class="fa-solid fa-pause"></i> Hold</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ALL STUDENTS SUMMARY -->
                    <div class="card">
                        <h3><i class="fa-solid fa-clipboard"></i> All Students - Good Moral Status</h3>
                        <div style="overflow-x: auto;">
                            <table class="students-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Full Name</th>
                                        <th>Course/Year</th>
                                        <th>GMC Status</th>
                                        <th>Generated Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allStudents as $student): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($student['student_id'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($student['full_name']) ?></td>
                                            <td><?= htmlspecialchars(format_course_for_display($student['course_year'] ?? '', $student['id'] ?? null) ?: 'N/A') ?></td>
                                            <td>
                                                <?php if ($student['gmc_status']): ?>
                                                    <span class="badge <?= $student['gmc_status'] === 'eligible' ? 'badge-eligible' : 'badge-flagged' ?>">
                                                        <?= ucfirst($student['gmc_status']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-pending">Not Evaluated</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $student['gmc_date'] ? date('M d, Y', strtotime($student['gmc_date'])) : '--' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                </div>
            </div>
        </main>
        
        <!-- Modal for Case History -->
        <div id="caseHistoryModal" class="modal-backdrop" style="display: none;">
            <div class="modal-content" style="width: 90%; max-width: 1000px; max-height: 85vh; overflow-y: auto;">
                <div class="modal-header">
                    <h2 id="modalStudentName">Student Case History</h2>
                    <button type="button" class="modal-close" onclick="closeCaseHistoryModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="caseHistoryContent">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.emailJsConfig = {
            serviceId: 'service_ku8p0ed',
            approvedTemplateId: 'template_v04bwqb',
            holdTemplateId: 'template_vy41tcb',
            publicKey: 'moMELqn21WfUvr1RL'
        };
        window.goodMoralEmailData = <?= isset($emailData) ? json_encode($emailData) : 'null' ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (!window.goodMoralEmailData || !window.goodMoralEmailData.send) {
                return;
            }

            if (typeof emailjs === 'undefined') {
                console.error('EmailJS SDK not loaded.');
                return;
            }

            emailjs.init(window.emailJsConfig.publicKey);

            const emailData = window.goodMoralEmailData;
            const templateId = emailData.template === 'hold' ? window.emailJsConfig.holdTemplateId : window.emailJsConfig.approvedTemplateId;
            
            setTimeout(function() {
                emailjs.send(window.emailJsConfig.serviceId, templateId, {
                    user_name: emailData.user_name,
                    email: emailData.email,
                    portal_link: emailData.portal_link,
                    request_id: emailData.request_id
                }).then(function(response) {
                    console.log('Email sent successfully', response);
                }, function(error) {
                    console.error('Email sending failed:', error);
                });
            }, 500);
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

        // Tabs Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    const container = this.closest('.tabs-container');
                    
                    if (!container) return;
                    
                    // Remove active class from all buttons and contents
                    container.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    container.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    const tabContent = container.querySelector('#' + tabId);
                    if (tabContent) {
                        tabContent.classList.add('active');
                    }
                });
            });

            const tabsHeader = document.querySelector('.tabs-header');
            if (tabsHeader) {
                attachTabSwipe(tabsHeader);
            }
        });

        function attachTabSwipe(header) {
            let touchStartX = 0;
            let touchEndX = 0;

            header.addEventListener('touchstart', function(e) {
                if (e.touches.length !== 1) return;
                touchStartX = e.touches[0].clientX;
            }, { passive: true });

            header.addEventListener('touchmove', function(e) {
                if (e.touches.length !== 1) return;
                touchEndX = e.touches[0].clientX;
            }, { passive: true });

            header.addEventListener('touchend', function() {
                const delta = touchEndX - touchStartX;
                if (Math.abs(delta) < 50) return;
                const buttons = Array.from(header.querySelectorAll('.tab-button'));
                const activeIndex = buttons.findIndex(btn => btn.classList.contains('active'));
                if (activeIndex < 0) return;
                const targetIndex = delta < 0 ? Math.min(activeIndex + 1, buttons.length - 1) : Math.max(activeIndex - 1, 0);
                if (targetIndex !== activeIndex) {
                    buttons[targetIndex].click();
                }
            }, { passive: true });
        }

        // Add CSS for tabs if not already defined
        const style = document.createElement('style');
        style.textContent = `
            .tabs-container {
                width: 100%;
            }
            .tabs-header {
                display: flex;
                flex-wrap: nowrap;
                gap: 8px;
                border-bottom: 2px solid #e5e7eb;
                margin-bottom: 20px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                touch-action: pan-x;
                scroll-snap-type: x proximity;
                align-items: stretch;
            }
            .tabs-header::-webkit-scrollbar {
                display: none;
            }
            .tab-button {
                padding: 12px 18px;
                background: none;
                border: none;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                color: #6b7280;
                border-bottom: 3px solid transparent;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                flex: 0 0 auto;
                min-width: 220px;
                white-space: nowrap;
                word-break: normal;
                overflow-wrap: normal;
                text-align: center;
                scroll-snap-align: start;
            }
            .tab-button:hover {
                color: #111827;
                background: #f9fafb;
            }
            .tab-button.active {
                color: #3b82f6;
                border-bottom-color: #3b82f6;
            }
            .students-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 16px;
            }
            .students-table th, .students-table td {
                padding: 12px 10px;
                border: 1px solid #e2e8f0;
                text-align: left;
                font-size: 13px;
                word-break: break-word;
            }
            .students-table th {
                background: #f8fafc;
                font-weight: 600;
                color: #0f172a;
            }
            .students-table tr:hover {
                background: #f9fafb;
            }
            .tab-content {
                min-width: 0;
                overflow-wrap: break-word;
            }
            @media (max-width: 768px) {
                .tabs-container {
                    display: flex !important;
                    flex-direction: column !important;
                    min-width: 0;
                    width: 100%;
                    overflow: visible !important;
                }
                .tabs-header {
                    display: flex !important;
                    flex-wrap: nowrap !important;
                    flex: 0 0 auto !important;
                    width: 100%;
                    gap: 6px;
                    padding-bottom: 2px;
                    overflow-x: auto !important;
                }
                .tabs-container > .tab-content {
                    flex: 0 0 auto !important;
                    display: none;
                    width: 100%;
                }
                .tabs-container > .tab-content.active {
                    display: block !important;
                }
                .tab-button { min-width: 220px; padding: 10px 12px; font-size: 13px; }
                .students-table, .students-table thead, .students-table tbody, .students-table tr, .students-table th, .students-table td { display: block; width: 100%; }
                .students-table thead { display: none; }
                .students-table tr { margin-bottom: 16px; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; padding: 12px; }
                .students-table th, .students-table td { border: none; }
                .students-table td { padding: 10px 0; border-bottom: 1px solid #e2e8f0; position: relative; text-align: left; }
                .students-table td:last-child { border-bottom: none; }
                .students-table td::before { content: attr(data-label); font-weight: 700; display: block; margin-bottom: 6px; color: #334155; }
            }
            @media (max-width: 480px) {
                .tab-button { min-width: 220px; padding: 9px 12px; font-size: 12px; }
                .students-table td { padding: 8px 0; font-size: 12px; }
            }
            .tab-content {
                display: none;
            }
            .tab-content.active {
                display: block;
                animation: fadeIn 0.3s ease;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .modal-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                animation: fadeIn 0.3s ease;
            }
            .modal-content {
                background: white;
                border-radius: 8px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                animation: slideUp 0.3s ease;
            }
            @keyframes slideUp {
                from { transform: translateY(20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .modal-header {
                padding: 20px;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .modal-header h2 {
                margin: 0;
                font-size: 20px;
                color: #111827;
            }
            .modal-close {
                background: none;
                border: none;
                font-size: 28px;
                cursor: pointer;
                color: #6b7280;
                transition: color 0.3s ease;
            }
            .modal-close:hover {
                color: #111827;
            }
            .modal-body {
                padding: 20px;
            }
        `;
        if (!document.querySelector('style[data-tabs]')) {
            style.setAttribute('data-tabs', 'true');
            document.head.appendChild(style);
        }
        
        // Modal functions for case history
        function openCaseHistoryModal(studentId, studentName) {
            const modal = document.getElementById('caseHistoryModal');
            const content = document.getElementById('caseHistoryContent');
            const header = document.getElementById('modalStudentName');
            
            header.textContent = 'Case History - ' + studentName;
            content.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fa-solid fa-spinner" style="font-size: 24px; animation: spin 1s linear infinite;"></i> Loading...</div>';
            modal.style.display = 'flex';
            
            // Fetch case data via AJAX
            fetch('?fetch_student_cases=1&student_id=' + studentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.student) {
                        let html = `
                            <div style="margin-bottom: 20px;">
                                <p style="margin: 5px 0;"><strong>Student ID:</strong> ${data.student.student_id || 'N/A'}</p>
                                <p style="margin: 5px 0;"><strong>Name:</strong> ${data.student.full_name}</p>
                                <p style="margin: 5px 0;"><strong>Course/Year:</strong> ${data.student.course_display || 'N/A'}</p>
                            </div>
                            <h4 style="margin-top: 20px; margin-bottom: 10px;">📋 Case History</h4>
                        `;
                        
                        if (data.cases && data.cases.length > 0) {
                            html += '<div style="overflow-x: auto;"><table class="students-table" style="width: 100%; border-collapse: collapse;">';
                            html += `<thead><tr>
                                <th>Case ID</th>
                                <th>Status</th>
                                <th>Type of Concern</th>
                                <th>Reporter</th>
                                <th>Created Date</th>
                                <th>Resolution</th>
                            </tr></thead><tbody>`;
                            
                            data.cases.forEach(function(caseItem) {
                                const statusClass = 'badge-' + (caseItem.status || 'unknown');
                                const resolution = caseItem.external_resolution && caseItem.external_resolution !== 'none' 
                                    ? `<span style="font-size: 12px; color: #065f46;">${caseItem.external_resolution}</span>`
                                    : '<span style="font-size: 12px; color: #6b7280;">Pending</span>';
                                const createdDate = new Date(caseItem.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                                
                                html += `<tr>
                                    <td><strong>${caseItem.case_number}</strong></td>
                                    <td><span class="badge ${statusClass}">${(caseItem.status || '').replace(/_/g, ' ').charAt(0).toUpperCase() + (caseItem.status || '').replace(/_/g, ' ').slice(1)}</span></td>
                                    <td>${caseItem.type_of_concern}</td>
                                    <td>${caseItem.reporter_name || 'Unknown'}</td>
                                    <td>${createdDate}</td>
                                    <td>${resolution}</td>
                                </tr>`;
                            });
                            
                            html += '</tbody></table></div>';
                        } else {
                            html += '<p style="color: #6b7280; font-style: italic;">No cases found for this student.</p>';
                        }
                        
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = '<div style="color: #dc2626;"><i class="fa-solid fa-exclamation-circle"></i> ' + (data.message || 'Failed to load case history') + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = '<div style="color: #dc2626;"><i class="fa-solid fa-exclamation-circle"></i> Error loading case history</div>';
                });
        }
        
        function closeCaseHistoryModal() {
            document.getElementById('caseHistoryModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('caseHistoryModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeCaseHistoryModal();
                    }
                });
            }
        });
        
        // Add spin animation for loading indicator
        const spinStyle = document.createElement('style');
        spinStyle.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(spinStyle);
    </script>
</body>
</html>
