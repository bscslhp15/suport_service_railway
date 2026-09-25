<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'clinic') {
    header('Location: /auth/teacher_login.php');
    exit;
}

function clinic_display_name(?string $name): string {
    $name = trim(str_replace("\xC2\xA0", ' ', (string) $name));
    return trim((string) preg_replace('/^name(?:\s+|$)/i', '', $name));
}

// Initialize clinic schema
ensure_clinic_schema();

// Manage section remains collapsed by default on clinic management pages
$is_manage_open = false;

// Handle search and filter parameters
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';

// Handle AJAX requests
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if (isset($_POST['get_record'])) {
        // Get single record for modal
        $userId = (int)$_POST['get_record'];
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT u.id, u.full_name, hr.* FROM users u LEFT JOIN clinic_health_records hr ON u.id = hr.user_id WHERE u.id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $record = $stmt->fetch();

        if ($record) {
            $record['full_name'] = clinic_display_name($record['full_name'] ?? '');
            echo json_encode(['success' => true, 'record' => $record]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Record not found']);
        }
        exit;
    } else {
        // Handle search/filter request
        $search = $_POST['search'] ?? '';
        $roleFilter = $_POST['role'] ?? '';

        $query = 'SELECT u.id AS user_id, u.full_name, u.role, u.course_year, hr.id AS health_record_id, hr.medical_history, hr.allergies, hr.current_medications, hr.blood_type, hr.emergency_contact_name, hr.emergency_contact_phone, hr.emergency_contact_relationship FROM users u LEFT JOIN clinic_health_records hr ON u.id = hr.user_id WHERE 1=1';
        $params = [];

        if (!empty($search)) {
            $query .= ' AND u.full_name LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($roleFilter)) {
            $query .= ' AND u.role = :role';
            $params['role'] = $roleFilter;
        }

        $query .= ' ORDER BY u.full_name';

        $pdo = get_db();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as &$record) {
            $record['full_name'] = clinic_display_name($record['full_name'] ?? '');
        }
        unset($record);

        echo json_encode([
            'success' => true,
            'records' => $records,
            'count' => count($records)
        ]);
        exit;
    }
}

// Build query with search and filter
$query = 'SELECT u.id AS user_id, u.full_name, u.role, u.course_year, hr.id AS health_record_id, hr.medical_history, hr.allergies, hr.current_medications, hr.blood_type, hr.emergency_contact_name, hr.emergency_contact_phone, hr.emergency_contact_relationship FROM users u LEFT JOIN clinic_health_records hr ON u.id = hr.user_id WHERE 1=1';
$params = [];

if (!empty($search)) {
    $query .= ' AND u.full_name LIKE :search';
    $params['search'] = '%' . $search . '%';
}

if (!empty($roleFilter)) {
    $query .= ' AND u.role = :role';
    $params['role'] = $roleFilter;
}

$query .= ' ORDER BY u.full_name';

$pdo = get_db();
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$healthRecords = $stmt->fetchAll();
foreach ($healthRecords as &$record) {
    $record['full_name'] = clinic_display_name($record['full_name'] ?? '');
}
unset($record);

// Role counts for overview cards
$stmt = $pdo->prepare('SELECT role, COUNT(*) AS total FROM users GROUP BY role');
$stmt->execute();
$roleCounts = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'total', 'role');
$studentCount = $roleCounts['student'] ?? 0;
$teacherCount = $roleCounts['teacher'] ?? 0;
$adminCount = $roleCounts['admin'] ?? 0;

// Handle form submissions
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_health_record'])) {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        if ($userId <= 0) {
            $message = 'Unable to update health record: missing patient ID.';
            $messageType = 'error';
        } else {
            $data = [
                'medical_history' => $_POST['medical_history'] ?? null,
                'allergies' => $_POST['allergies'] ?? null,
                'current_medications' => $_POST['current_medications'] ?? null,
                'blood_type' => $_POST['blood_type'] ?? null,
                'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $_POST['emergency_contact_relationship'] ?? null
            ];

            if (save_health_record($userId, $data)) {
                $message = 'Health record updated successfully!';
                // Refresh data
                $stmt->execute();
                $healthRecords = $stmt->fetchAll();
            } else {
                $message = 'Failed to update health record.';
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
    <title>Health Records Management | PASS Support System</title>
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

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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

        .btn-secondary {
            background: var(--clinic-gold);
            color: var(--clinic-maroon);
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

        .cell-label {
            display: none;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            padding: 20px;
            border-left: 4px solid var(--clinic-maroon);
        }

        .stat-card h4 {
            margin: 0 0 12px;
            color: var(--clinic-maroon);
            font-size: 16px;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .stat-card .stat-label {
            color: #666;
            font-size: 0.95rem;
        }

        .search-filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }

        .search-filter-form {
            margin-bottom: 15px;
        }

        .search-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input-group {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .search-input {
            width: 100%;
            padding: 10px 10px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--clinic-maroon);
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
        }

        .filter-group {
            min-width: 150px;
        }

        .filter-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--clinic-maroon);
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
        }

        .results-count {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .results-count i.fa-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .search-input, .filter-select {
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .search-input:focus, .filter-select:focus {
            border-color: var(--clinic-maroon);
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
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
            margin: 5% auto;
            padding: 20px;
            border-radius: 12px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        @media (max-width: 820px) {
            .page-content {
                padding: 16px 12px;
            }

            .main-scroll {
                padding-bottom: 24px;
            }

            .page-header-panel {
                margin-bottom: 20px;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .section-header h2 {
                font-size: 1.4rem;
            }

            .section-subtitle {
                font-size: 0.95rem;
                max-width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr !important;
                gap: 16px;
            }

            .stat-card {
                padding: 18px;
            }

            .search-filter-section {
                padding: 16px;
            }

            .search-group {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input-group,
            .filter-group {
                min-width: 0;
                width: 100%;
            }

            .results-count {
                justify-content: flex-start;
                margin-top: 12px;
            }

            .content-section {
                padding: 18px;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .data-table th,
            .data-table td {
                padding: 10px 8px;
                font-size: 0.95rem;
            }

            .data-table th:first-child,
            .data-table td:first-child {
                min-width: 120px;
            }

            .data-table th:nth-child(6),
            .data-table td:nth-child(6) {
                white-space: nowrap;
            }

            .btn {
                width: 100%;
                text-align: center;
            }

            .data-table {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 16px;
                margin-top: 12px;
            }

            .data-table thead {
                display: none;
            }

            .data-table tbody {
                display: block;
                width: 100%;
            }

            .data-table tr {
                display: grid;
                gap: 12px;
                width: 100%;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 18px;
                padding: 18px;
                margin-bottom: 16px;
            }

            .data-table td {
                display: block;
                width: 100%;
                padding: 10px 0;
                border: none;
            }

            .data-table td > .cell-row {
                display: grid !important;
                grid-template-columns: minmax(110px, 1fr) minmax(0, 1.8fr);
                gap: 12px;
                align-items: start;
                width: 100%;
            }

            .cell-label {
                display: block;
                color: #475569;
                font-weight: 700;
                font-size: 0.88rem;
                white-space: normal;
                line-height: 1.4;
                min-width: 0;
            }

            .cell-value {
                min-width: 0;
                text-align: left;
                line-height: 1.5;
                word-break: break-word;
            }

            .cell-value span,
            .cell-value strong,
            .cell-value .btn {
                margin: 0;
            }

            .cell-value span,
            .cell-value strong,
            .cell-value .btn {
                margin: 0;
            }

            .data-table td > .cell-value span,
            .data-table td > .cell-value strong,
            .data-table td > .cell-value .btn {
                margin: 0;
            }

            .data-table td:last-child {
                padding-bottom: 0;
            }

            .data-table td:last-child .btn {
                width: 100%;
                margin: 4px 0 0;
            }

            .data-table td:last-child .btn + .btn {
                margin-top: 10px;
            }

            .btn + .btn {
                margin-top: 10px;
            }

            .modal-content {
                width: 95%;
                margin: 6% auto;
                padding: 18px;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 0.95rem;
            }
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            top: 10px;
            right: 15px;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
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
                        <a href="clinic_health_records.php" class="active" data-tooltip="Health Records">
                            <span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>
                            <span class="nav-text">Health Records</span>
                        </a>
                        <a href="clinic_medicine_inventory.php" data-tooltip="Medicine Inventory">
                            <span class="nav-icon"><i class="fa-solid fa-pills"></i></span>
                            <span class="nav-text">Medicine Inventory</span>
                        </a>
                        <a href="clinic_visit_logs.php" data-tooltip="Visit Logs">
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
                        <span class="eyebrow">HEALTH RECORDS</span>
                        <h1>Health Records Management</h1>
                        <p class="clinic-header__subtitle">Track patient history, record visits, and keep clinical notes organized.</p>
                    </div>
                    <div class="clinic-header__art" aria-hidden="true"><i class="fa-solid fa-clipboard medical-clipboard"></i><i class="fa-solid fa-plus medical-cross"></i><i class="fa-solid fa-circle-check medical-check"></i><i class="fa-solid fa-stethoscope medical-stethoscope"></i><i class="fa-solid fa-location-pin medical-pin"></i><i class="fa-solid fa-leaf medical-leaf"></i></div>
                </section>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h4><i class="fa-solid fa-user-graduate"></i> Students</h4>
                        <span class="stat-value"><?= number_format($studentCount) ?></span>
                        <span class="stat-label">Total student records</span>
                    </div>
                    <div class="stat-card">
                        <h4><i class="fa-solid fa-chalkboard-teacher"></i> Teachers</h4>
                        <span class="stat-value"><?= number_format($teacherCount) ?></span>
                        <span class="stat-label">Total teacher records</span>
                    </div>
                    <div class="stat-card">
                        <h4><i class="fa-solid fa-user-shield"></i> Admins</h4>
                        <span class="stat-value"><?= number_format($adminCount) ?></span>
                        <span class="stat-label">Total admin records</span>
                    </div>
                </div>

                <!-- Health Records Management -->
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fa-solid fa-heart-pulse"></i> Health Records Management</h2>
                    </div>

                    <!-- Search and Filter Controls -->
                    <div class="search-filter-section">
                        <div class="search-filter-form">
                            <div class="search-group">
                                <div class="search-input-group">
                                    <i class="fa-solid fa-search"></i>
                                    <input type="text" id="search-input" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name..." class="search-input">
                                </div>
                                <div class="filter-group">
                                    <select id="role-filter" class="filter-select">
                                        <option value="">All Roles</option>
                                        <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Students</option>
                                        <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Teachers</option>
                                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admins</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="results-count">
                            <i class="fa-solid fa-users"></i>
                            <span id="results-count">Showing <?= count($healthRecords) ?> health record<?= count($healthRecords) !== 1 ? 's' : '' ?>
                            <?php if (!empty($search)): ?>
                                for "<?= htmlspecialchars($search) ?>"
                            <?php endif; ?>
                            <?php if (!empty($roleFilter)): ?>
                                (<?= ucfirst($roleFilter) ?>s only)
                            <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Blood Type</th>
                                    <th>Allergies</th>
                                    <th>Medical History</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($healthRecords as $record): ?>
                                <tr>
                                    <td>
                                        <div class="cell-row">
                                            <span class="cell-label">Name</span>
                                            <span class="cell-value"><?= htmlspecialchars(clinic_display_name($record['full_name'])) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-row">
                                            <span class="cell-label">Role</span>
                                            <span class="cell-value"><?= htmlspecialchars(ucfirst($record['role'])) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-row">
                                            <span class="cell-label">Blood Type</span>
                                            <span class="cell-value"><?= htmlspecialchars($record['blood_type'] ?? 'Not set') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-row">
                                            <span class="cell-label">Allergies</span>
                                            <span class="cell-value">
                                                <?php if ($record['allergies']): ?>
                                                    <span title="<?= htmlspecialchars($record['allergies']) ?>"><?= htmlspecialchars(substr($record['allergies'], 0, 30)) ?>...</span>
                                                <?php else: ?>
                                                    None
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-row">
                                            <span class="cell-label">Medical History</span>
                                            <span class="cell-value">
                                                <?php if ($record['medical_history']): ?>
                                                    <span title="<?= htmlspecialchars($record['medical_history']) ?>"><?= htmlspecialchars(substr($record['medical_history'], 0, 30)) ?>...</span>
                                                <?php else: ?>
                                                    None
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-row">
                                            <span class="cell-label">Actions</span>
                                            <span class="cell-value">
                                                <button type="button" class="btn btn-primary btn-sm"
                                                    data-user-id="<?= htmlspecialchars($record['user_id']) ?>"
                                                    data-full-name="<?= htmlspecialchars(clinic_display_name($record['full_name'])) ?>"
                                                    data-blood-type="<?= htmlspecialchars($record['blood_type'] ?? '') ?>"
                                                    data-medical-history="<?= htmlspecialchars($record['medical_history'] ?? '') ?>"
                                                    data-allergies="<?= htmlspecialchars($record['allergies'] ?? '') ?>"
                                                    data-current-medications="<?= htmlspecialchars($record['current_medications'] ?? '') ?>"
                                                    data-emergency-contact-name="<?= htmlspecialchars($record['emergency_contact_name'] ?? '') ?>"
                                                    data-emergency-contact-phone="<?= htmlspecialchars($record['emergency_contact_phone'] ?? '') ?>"
                                                    data-emergency-contact-relationship="<?= htmlspecialchars($record['emergency_contact_relationship'] ?? '') ?>"
                                                    onclick="openHealthRecordModal(this, <?= (int)$record['user_id'] ?>)" aria-label="Edit health record">
                                                    <i class="fa-solid fa-edit"></i> Edit
                                                </button>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Health Record Modal -->
    <div id="healthRecordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeHealthRecordModal()">&times;</span>
            <h2>Health Record Details</h2>
            <form method="POST" id="healthRecordForm">
                <input type="hidden" name="user_id" id="modal_user_id">

                <div class="form-group">
                    <label for="modal_full_name">Patient Name</label>
                    <input type="text" id="modal_full_name" readonly>
                </div>

                <div class="form-group">
                    <label for="blood_type">Blood Type</label>
                    <select name="blood_type" id="blood_type">
                        <option value="">Select blood type</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="medical_history">Medical History</label>
                    <textarea name="medical_history" id="medical_history" placeholder="Past illnesses, surgeries, etc."></textarea>
                </div>

                <div class="form-group">
                    <label for="allergies">Allergies</label>
                    <textarea name="allergies" id="allergies" placeholder="Drug allergies, food allergies, etc."></textarea>
                </div>

                <div class="form-group">
                    <label for="current_medications">Current Medications</label>
                    <textarea name="current_medications" id="current_medications" placeholder="List current medications and dosages"></textarea>
                </div>

                <div class="form-group">
                    <label for="emergency_contact_name">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" id="emergency_contact_name">
                </div>

                <div class="form-group">
                    <label for="emergency_contact_phone">Emergency Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" id="emergency_contact_phone">
                </div>

                <div class="form-group">
                    <label for="emergency_contact_relationship">Emergency Contact Relationship</label>
                    <input type="text" name="emergency_contact_relationship" id="emergency_contact_relationship" placeholder="e.g., Parent, Spouse, Sibling">
                </div>

                <button type="submit" name="update_health_record" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Update Health Record
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
        let searchTimeout;

        // Initialize live search and filter
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-input');
            const roleFilter = document.getElementById('role-filter');

            // Add event listeners
            searchInput.addEventListener('input', handleSearch);
            roleFilter.addEventListener('change', handleFilter);

            // Initial load with current values
            updateResults();
        });

        function handleSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(updateResults, 300); // 300ms debounce
        }

        function handleFilter() {
            updateResults();
        }

        function updateResults() {
            const searchValue = document.getElementById('search-input').value.trim();
            const roleValue = document.getElementById('role-filter').value;

            // Show loading state
            const resultsCount = document.getElementById('results-count');
            resultsCount.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Searching...';

            // Create form data for AJAX request
            const formData = new FormData();
            formData.append('search', searchValue);
            formData.append('role', roleValue);
            formData.append('ajax', '1');

            // Make AJAX request
            fetch('clinic_health_records.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTable(data.records);
                    updateResultsCount(data.count, searchValue, roleValue);
                } else {
                    console.error('Error:', data.error);
                    resultsCount.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Error loading results';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                resultsCount.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Error loading results';
            });
        }

        function updateTable(records) {
            const tbody = document.querySelector('.data-table tbody');
            tbody.innerHTML = '';

            if (records.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fa-solid fa-search" style="font-size: 48px; margin-bottom: 15px; color: #ccc;"></i>
                            <div>No health records found matching your search.</div>
                        </td>
                    </tr>
                `;
                return;
            }

            records.forEach(record => {
                const allergiesText = record.allergies ?
                    `<span title="${escapeHtml(record.allergies)}">${escapeHtml(record.allergies.substring(0, 30))}...</span>` :
                    'None';

                const medicalHistoryText = record.medical_history ?
                    `<span title="${escapeHtml(record.medical_history)}">${escapeHtml(record.medical_history.substring(0, 30))}...</span>` :
                    'None';

                const row = `
                    <tr>
                        <td>
                            <div class="cell-row">
                                <span class="cell-label">Name</span>
                                <span class="cell-value">${escapeHtml(cleanClinicDisplayName(record.full_name))}</span>
                            </div>
                        </td>
                        <td>
                            <div class="cell-row">
                                <span class="cell-label">Role</span>
                                <span class="cell-value">${escapeHtml(record.role.charAt(0).toUpperCase() + record.role.slice(1))}</span>
                            </div>
                        </td>
                        <td>
                            <div class="cell-row">
                                <span class="cell-label">Blood Type</span>
                                <span class="cell-value">${escapeHtml(record.blood_type || 'Not set')}</span>
                            </div>
                        </td>
                        <td>
                            <div class="cell-row">
                                <span class="cell-label">Allergies</span>
                                <span class="cell-value">${allergiesText}</span>
                            </div>
                        </td>
                        <td>
                            <div class="cell-row">
                                <span class="cell-label">Medical History</span>
                                <span class="cell-value">${medicalHistoryText}</span>
                            </div>
                        </td>
                        <td>
                            <div class="cell-row">
                                <span class="cell-label">Actions</span>
                                <span class="cell-value">
                                    <button type="button" class="btn btn-primary btn-sm"
                                        data-user-id="${escapeHtml(record.user_id)}"
                                        data-full-name="${escapeHtml(cleanClinicDisplayName(record.full_name || ''))}"
                                        data-blood-type="${escapeHtml(record.blood_type || '')}"
                                        data-medical-history="${escapeHtml(record.medical_history || '')}"
                                        data-allergies="${escapeHtml(record.allergies || '')}"
                                        data-current-medications="${escapeHtml(record.current_medications || '')}"
                                        data-emergency-contact-name="${escapeHtml(record.emergency_contact_name || '')}"
                                        data-emergency-contact-phone="${escapeHtml(record.emergency_contact_phone || '')}"
                                        data-emergency-contact-relationship="${escapeHtml(record.emergency_contact_relationship || '')}"
                                        onclick="openHealthRecordModal(this, '${escapeHtml(record.user_id)}')" aria-label="Edit health record">
                                        <i class="fa-solid fa-edit"></i> Edit
                                    </button>
                                </span>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', row);
            });
        }

        function updateResultsCount(count, searchValue, roleValue) {
            const resultsCount = document.getElementById('results-count');
            let countText = `Showing ${count} health record${count !== 1 ? 's' : ''}`;

            if (searchValue) {
                countText += ` for "${escapeHtml(searchValue)}"`;
            }

            if (roleValue) {
                countText += ` (${roleValue.charAt(0).toUpperCase() + roleValue.slice(1)}s only)`;
            }

            resultsCount.innerHTML = `<i class="fa-solid fa-users"></i> ${countText}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function cleanClinicDisplayName(name) {
            return String(name || '').replace(/^\s*name\s+/i, '').trim();
        }

        function openHealthRecordModal(button, userIdFromParam = null) {
            const userId = userIdFromParam || button.getAttribute('data-user-id') || button.dataset.userId || '';
            if (!userId) {
                console.error('Missing data-user-id on edit button', button);
                return;
            }

            const recordData = {
                id: userId,
                full_name: button.getAttribute('data-full-name') || button.dataset.fullName || '',
                blood_type: button.getAttribute('data-blood-type') || button.dataset.bloodType || '',
                medical_history: button.getAttribute('data-medical-history') || button.dataset.medicalHistory || '',
                allergies: button.getAttribute('data-allergies') || button.dataset.allergies || '',
                current_medications: button.getAttribute('data-current-medications') || button.dataset.currentMedications || '',
                emergency_contact_name: button.getAttribute('data-emergency-contact-name') || button.dataset.emergencyContactName || '',
                emergency_contact_phone: button.getAttribute('data-emergency-contact-phone') || button.dataset.emergencyContactPhone || '',
                emergency_contact_relationship: button.getAttribute('data-emergency-contact-relationship') || button.dataset.emergencyContactRelationship || ''
            };

            // Show modal immediately
            document.getElementById('healthRecordModal').style.display = 'block';

            // Populate form with data
            document.getElementById('modal_user_id').value = recordData.id;
            document.getElementById('modal_full_name').value = recordData.full_name;
            document.getElementById('blood_type').value = recordData.blood_type;
            document.getElementById('medical_history').value = recordData.medical_history;
            document.getElementById('allergies').value = recordData.allergies;
            document.getElementById('current_medications').value = recordData.current_medications;
            document.getElementById('emergency_contact_name').value = recordData.emergency_contact_name;
            document.getElementById('emergency_contact_phone').value = recordData.emergency_contact_phone;
            document.getElementById('emergency_contact_relationship').value = recordData.emergency_contact_relationship;
        }

        function closeHealthRecordModal() {
            document.getElementById('healthRecordModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('healthRecordModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
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