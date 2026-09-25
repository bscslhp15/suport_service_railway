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

// Get clearance requests
$pendingRequests = get_pending_clearance_requests();

// Get all clearance requests by status
$pdo = get_db();
$stmt = $pdo->prepare('SELECT cr.*, u.full_name, u.role, u.course_year FROM clinic_clearance_requests cr JOIN users u ON cr.user_id = u.id WHERE cr.status = ? ORDER BY cr.created_at DESC LIMIT 100');

// Get pending requests
$stmt->execute(['pending']);
$pendingRequestsAll = $stmt->fetchAll();

// Get approved requests
$stmt->execute(['approved']);
$approvedRequests = $stmt->fetchAll();

// Get cancelled/rejected requests
$stmt->execute(['rejected']);
$cancelledRequests = $stmt->fetchAll();

// Get all requests for statistics
$stmtAll = $pdo->prepare('SELECT cr.*, u.full_name, u.role, u.course_year FROM clinic_clearance_requests cr JOIN users u ON cr.user_id = u.id ORDER BY cr.created_at DESC LIMIT 100');
$stmtAll->execute();
$allRequests = $stmtAll->fetchAll();

// Handle form submissions
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_clearance_request'])) {
        $requestId = (int)$_POST['request_id'];
        $action = $_POST['process_clearance_request'];
        $notes = $_POST['review_notes'] ?? null;

        if (process_clearance_request($requestId, $action, $notes, $user['id'])) {
            $message = 'Clearance request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully!';
            $pendingRequests = get_pending_clearance_requests();
            // Refresh all requests
            $stmt->execute();
            $allRequests = $stmt->fetchAll();
        } else {
            $message = 'Failed to process clearance request.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clearance Requests Management | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
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
            min-height: 80px;
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
            background: #660000;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
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

        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }

        .status-approved {
            color: #28a745;
            font-weight: bold;
        }

        .status-rejected {
            color: #dc3545;
            font-weight: bold;
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

        .clinic-subtabs,
        .inventory-tab-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding: 10px;
            background: #ffffff;
            border-radius: 12px;
            justify-content: space-between;
            border: 1px solid #e5e7eb;
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
            display: none;
        }

        .clinic-subtab-content.active {
            display: block;
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
                <button type="button" class="nav-search-btn" aria-label="Search" data-tooltip="Search">
                    <i class="fa-solid fa-search"></i>
                </button>
            </div>
            <div class="nav-section">
                <a href="nurse_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_home.php" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="librarian_home.php" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="nurse_home.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc_head_home.php" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="ssc_head_home.php#scholarship" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="ssaa_home.php" data-tooltip="Alumni">
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
                        <a href="clinic_visit_logs.php" data-tooltip="Visit Logs">
                            <span class="nav-icon"><i class="fa-solid fa-notes-medical"></i></span>
                            <span class="nav-text">Visit Logs</span>
                        </a>
                        <a href="clinic_clearance_requests.php" class="active" data-tooltip="Clearance Requests">
                            <span class="nav-icon"><i class="fa-solid fa-clipboard-check"></i></span>
                            <span class="nav-text">Clearance Requests</span>
                        </a>
                        <a href="clinic_announcements.php" data-tooltip="Announcements">
                            <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                            <span class="nav-text">Announcements</span>
                        </a>
                        <a href="clinic_analytics.php" data-tooltip="Analytics">
                            <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                            <span class="nav-text">Analytics</span>
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
                    <div class="nav-brand">
                        <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
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
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></button>
                    <button type="button" class="topbar-icon" aria-label="About"><i class="fa-solid fa-question-circle"></i></button>
                </div>
            </header>
            <div class="main-scroll">
                <?php if ($message): ?>
                    <div class="alert-card <?= $messageType ?>">
                        <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                    <div class="page-header-panel">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-clipboard-check"></i> Clearance Requests Management</h2>
                        </div>
                        <p class="section-subtitle">Review and process clearance submissions with clear status tracking.</p>
                    </div>

                    <!-- Clearance Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-clock"></i> Pending Requests</h4>
                            <div class="stat-value"><?= count($pendingRequests) ?></div>
                            <div class="stat-label">Awaiting approval</div>
                        </div>
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-check-circle"></i> Approved Today</h4>
                            <div class="stat-value">
                                <?php
                                $approvedToday = 0;
                                foreach ($allRequests as $request) {
                                    if ($request['status'] === 'approved' && date('Y-m-d', strtotime($request['reviewed_at'])) === date('Y-m-d')) {
                                        $approvedToday++;
                                    }
                                }
                                echo $approvedToday;
                                ?>
                            </div>
                            <div class="stat-label">Clearances issued</div>
                        </div>
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-calendar-alt"></i> This Month</h4>
                            <div class="stat-value">
                                <?php
                                $thisMonth = 0;
                                foreach ($allRequests as $request) {
                                    if ($request['status'] === 'approved' && date('Y-m', strtotime($request['reviewed_at'])) === date('Y-m')) {
                                        $thisMonth++;
                                    }
                                }
                                echo $thisMonth;
                                ?>
                            </div>
                            <div class="stat-label">Total clearances</div>
                        </div>
                    </div>

                    <!-- Pending Clearance Requests -->
                    <?php if (!empty($pendingRequests)): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-clipboard-check"></i> Pending Clearance Requests</h2>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Course/Year</th>
                                        <th>Request Date</th>
                                        <th>Purpose</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRequests as $request): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($request['full_name']) ?></td>
                                        <td><?= htmlspecialchars($request['course_year'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(date('M d, Y', strtotime($request['created_at']))) ?></td>
                                        <td>
                                            <?php if ($request['purpose']): ?>
                                                <span title="<?= htmlspecialchars($request['purpose']) ?>">
                                                    <?= htmlspecialchars(substr($request['purpose'], 0, 40)) ?>...
                                                </span>
                                            <?php else: ?>
                                                General Clearance
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-success btn-sm" onclick="openProcessModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['full_name']) ?>', 'approve')">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm" onclick="openProcessModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['full_name']) ?>', 'reject')">
                                                <i class="fa-solid fa-times"></i> Reject
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-clipboard-check"></i> Clearance Requests</h2>
                        </div>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fa-solid fa-clipboard-check" style="font-size: 48px; margin-bottom: 20px; color: var(--clinic-maroon);"></i>
                            <h3>No Pending Requests</h3>
                            <p>All clearance requests have been processed.</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Clearance Request History Tabs -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-history"></i> Clearance Request History</h2>
                        </div>

                        <div class="clinic-subtabs inventory-tab-bar" role="tablist" aria-label="Clearance navigation">
                            <button class="clinic-subtab library-tab-button active" type="button" data-tab="pending-requests" onclick="switchClearanceTab('pending-requests', this)">PENDING</button>
                            <button class="clinic-subtab library-tab-button" type="button" data-tab="cancelled-requests" onclick="switchClearanceTab('cancelled-requests', this)">CANCELLED</button>
                            <button class="clinic-subtab library-tab-button" type="button" data-tab="approved-requests" onclick="switchClearanceTab('approved-requests', this)">APPROVED</button>
                        </div>

                        <div id="pending-requests" class="clinic-subtab-content active">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Course/Year</th>
                                            <th>Request Date</th>
                                            <th>Status</th>
                                            <th>Expires</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($pendingRequestsAll)): ?>
                                            <?php foreach ($pendingRequestsAll as $request): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($request['full_name']) ?></td>
                                                <td><?= htmlspecialchars($request['course_year'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars(date('M d, Y', strtotime($request['created_at']))) ?></td>
                                                <td><span class="status-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span></td>
                                                <td>
                                                    <?php if ($request['clearance_expiry']): ?>
                                                        <?= htmlspecialchars(date('M d, Y', strtotime($request['clearance_expiry']))) ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" style="text-align: center; padding: 40px; color: #666;">
                                                    <i class="fa-solid fa-clock" style="font-size: 48px; margin-bottom: 20px; color: var(--clinic-maroon);"></i>
                                                    <h3>No Pending Requests</h3>
                                                    <p>All pending requests have been processed.</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="cancelled-requests" class="clinic-subtab-content">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Course/Year</th>
                                            <th>Request Date</th>
                                            <th>Status</th>
                                            <th>Reviewed Date</th>
                                            <th>Expires</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($cancelledRequests)): ?>
                                            <?php foreach ($cancelledRequests as $request): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($request['full_name']) ?></td>
                                                <td><?= htmlspecialchars($request['course_year'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars(date('M d, Y', strtotime($request['created_at']))) ?></td>
                                                <td><span class="status-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span></td>
                                                <td>
                                                    <?php if ($request['reviewed_at']): ?>
                                                        <?= htmlspecialchars(date('M d, Y', strtotime($request['reviewed_at']))) ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($request['clearance_expiry']): ?>
                                                        <?= htmlspecialchars(date('M d, Y', strtotime($request['clearance_expiry']))) ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                                    <i class="fa-solid fa-times-circle" style="font-size: 48px; margin-bottom: 20px; color: var(--clinic-maroon);"></i>
                                                    <h3>No Cancelled Requests</h3>
                                                    <p>No requests have been cancelled.</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="approved-requests" class="clinic-subtab-content">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Course/Year</th>
                                            <th>Request Date</th>
                                            <th>Status</th>
                                            <th>Reviewed Date</th>
                                            <th>Expires</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($approvedRequests)): ?>
                                            <?php foreach ($approvedRequests as $request): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($request['full_name']) ?></td>
                                                <td><?= htmlspecialchars($request['course_year'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars(date('M d, Y', strtotime($request['created_at']))) ?></td>
                                                <td><span class="status-<?= $request['status'] ?>"><?= ucfirst($request['status']) ?></span></td>
                                                <td>
                                                    <?php if ($request['reviewed_at']): ?>
                                                        <?= htmlspecialchars(date('M d, Y', strtotime($request['reviewed_at']))) ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($request['clearance_expiry']): ?>
                                                        <?= htmlspecialchars(date('M d, Y', strtotime($request['clearance_expiry']))) ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                                    <i class="fa-solid fa-check-circle" style="font-size: 48px; margin-bottom: 20px; color: var(--clinic-maroon);"></i>
                                                    <h3>No Approved Requests</h3>
                                                    <p>No requests have been approved yet.</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
            </div>
        </main>
    </div>

    <!-- Process Clearance Modal -->
    <div id="processModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeProcessModal()">&times;</span>
            <h2 id="modalTitle">Process Clearance Request</h2>
            <form method="POST" id="processForm">
                <input type="hidden" name="request_id" id="modal_request_id">

                <div class="form-group">
                    <label for="modal_student_name">Student Name</label>
                    <input type="text" id="modal_student_name" readonly>
                </div>

                <div class="form-group">
                    <label for="review_notes">Review Notes (Optional)</label>
                    <textarea name="review_notes" id="review_notes" placeholder="Add any notes about this clearance decision..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeProcessModal()">Cancel</button>
                    <button type="submit" name="process_clearance_request" id="processButton" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i> Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        function openProcessModal(requestId, studentName, action) {
            document.getElementById('modal_request_id').value = requestId;
            document.getElementById('modal_student_name').value = studentName;

            const modalTitle = document.getElementById('modalTitle');
            const processButton = document.getElementById('processButton');

            if (action === 'approve') {
                modalTitle.textContent = 'Approve Clearance Request';
                processButton.innerHTML = '<i class="fa-solid fa-check"></i> Approve';
                processButton.className = 'btn btn-success';
                processButton.name = 'process_clearance_request';
                processButton.value = 'approve';
            } else {
                modalTitle.textContent = 'Reject Clearance Request';
                processButton.innerHTML = '<i class="fa-solid fa-times"></i> Reject';
                processButton.className = 'btn btn-warning';
                processButton.name = 'process_clearance_request';
                processButton.value = 'reject';
            }

            document.getElementById('processModal').style.display = 'block';
        }

        function closeProcessModal() {
            document.getElementById('processModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('processModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        function switchClearanceTab(tabId, button) {
            // Hide all clearance subtab contents
            const subtabs = document.querySelectorAll('.clinic-subtab-content');
            subtabs.forEach(subtab => subtab.style.display = 'none');

            // Remove active class from all clearance subtab buttons
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
        }
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>