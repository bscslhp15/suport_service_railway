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

// Determine if Manage section should be expanded (when on clinic management pages)
$clinic_pages = ['clinic_status.php', 'clinic_health_records.php', 'clinic_medicine_inventory.php', 'clinic_visit_logs.php', 'clinic_clearance_requests.php', 'clinic_announcements.php', 'clinic_analytics.php'];
$current_page = basename(__FILE__);
$is_manage_open = in_array($current_page, $clinic_pages);

// Get clinic data
$clinicStatus = get_clinic_status();
$currentVisits = get_current_clinic_visits();
$todayVisits = get_today_clinic_visits();
$lowStockMedicines = get_low_stock_medicines();
$expiringMedicines = get_expiring_medicines();
$pendingClearanceRequests = get_pending_clearance_requests();
$activeAnnouncements = get_active_clinic_announcements();
$analytics = get_clinic_visit_analytics(30);

// Handle form submissions
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $isAvailable = (int)$_POST['is_available'] === 1;
        $reason = $_POST['reason'] ?? null;
        $duration = !empty($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : null;

        if (update_clinic_status($isAvailable, $reason, $duration, $user['id'])) {
            $message = 'Clinic status updated successfully!';
            $clinicStatus = get_clinic_status();
        } else {
            $message = 'Failed to update clinic status.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['create_announcement'])) {
        $announcementData = [
            'title' => $_POST['announcement_title'],
            'category' => $_POST['announcement_category'],
            'description' => $_POST['announcement_description'],
            'target_audience' => $_POST['target_audience'],
            'priority' => $_POST['priority'],
            'duration_days' => (int)$_POST['duration_days']
        ];

        if (create_clinic_announcement($announcementData, $user['id'])) {
            $message = 'Announcement created successfully!';
            $activeAnnouncements = get_active_clinic_announcements();
        } else {
            $message = 'Failed to create announcement.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['update_medicine_stock'])) {
        $medicineId = (int)$_POST['medicine_id'];
        $newStock = (int)$_POST['new_stock'];

        if (update_medicine_stock($medicineId, $newStock)) {
            $message = 'Medicine stock updated successfully!';
            $lowStockMedicines = get_low_stock_medicines();
        } else {
            $message = 'Failed to update medicine stock.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['process_clearance_request'])) {
        $requestId = (int)$_POST['request_id'];
        $action = $_POST['process_clearance_request'];
        $notes = $_POST['review_notes'] ?? null;

        if (process_clearance_request($requestId, $action, $notes, $user['id'])) {
            $message = 'Clearance request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully!';
            $pendingClearanceRequests = get_pending_clearance_requests();
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
    <title>Clinic Head Dashboard | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="manifest" href="../PWA/manifest-teacher.json">
    <meta name="theme-color" content="#800000">
    <style>
        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-light: #f8f9fa;
        }

        .clinic-status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-right: 1rem;
        }

        .clinic-status-indicator.available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .clinic-status-indicator.unavailable {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .clinic-card {
            background: linear-gradient(135deg, var(--clinic-maroon), var(--clinic-gold));
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .clinic-card h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }

        .clinic-card p {
            margin: 0;
            opacity: 0.9;
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
            background: #660000;
        }

        .btn-secondary {
            background: var(--clinic-gold);
            color: var(--clinic-maroon);
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

        .priority-high { color: #dc3545; }
        .priority-medium { color: #ffc107; }
        .priority-low { color: #28a745; }

        .status-waiting { color: #ffc107; }
        .status-in-consultation { color: #007bff; }
        .status-completed { color: #28a745; }

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

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
                <a href="nurse_home.php" class="active" data-tooltip="Dashboard">
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
                            <span class="nav-text">Alumni</span>
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
                    <div class="clinic-status-indicator <?= $clinicStatus['is_available'] ? 'available' : 'unavailable' ?>">
                        <i class="fa-solid fa-circle"></i>
                        Clinic: <?= $clinicStatus['is_available'] ? 'Open' : 'Closed' ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Clinic Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Download App" data-tooltip="Download App" id="installAppBtn"><i class="fa-solid fa-download"></i></button>
                </div>
            </header>
            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                    <?php if ($message): ?>
                        <div class="alert-card <?= $messageType ?>">
                            <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$clinicStatus['is_available']): ?>
                    <div style="background: #dc3545; color: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.15);">
                        <i class="fa-solid fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                        <h3 style="margin: 10px 0; font-size: 20px;">⚠️ CLINIC IS CURRENTLY CLOSED</h3>
                        <p style="margin: 5px 0; font-size: 16px;">No Nurse Available</p>
                        <?php if ($clinicStatus['reason']): ?>
                            <p style="margin: 10px 0; font-size: 14px;"><strong>Reason:</strong> <?= htmlspecialchars($clinicStatus['reason']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Dashboard Overview -->
                    <section id="dashboard" class="dashboard-intro">
                        <div class="dashboard-intro-left">
                            <p class="eyebrow"><i class="fa-solid fa-star sparkle-icon"></i> Clinic Management System</p>
                            <p class="dashboard-date" style="color: white;"><strong style="color: white;">Today's Date</strong> <?= date('F j, Y') ?></p>
                            <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?></h1>
                            <p class="dashboard-subtitle">Comprehensive health support and clinic management dashboard.</p>
                        </div>
                        <aside class="dashboard-intro-right">
                            <div class="dashboard-detail-panel">
                                <div class="detail-row role-row">
                                    <div class="detail-icon"><i class="fa-solid fa-calendar-days"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Date</div>
                                        <div class="detail-bottom"><?= date('F j, Y') ?></div>
                                    </div>
                                </div>
                                <div class="detail-row course-row">
                                    <div class="detail-icon"><i class="fa-solid fa-users"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Today's Patients</div>
                                        <div class="detail-bottom"><?= count($todayVisits) ?></div>
                                    </div>
                                </div>
                                <div class="detail-row year-row">
                                    <div class="detail-icon"><i class="fa-solid fa-pills"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Low Stock Alerts</div>
                                        <div class="detail-bottom"><?= count($lowStockMedicines) ?></div>
                                    </div>
                                </div>
                                <div class="detail-row id-row">
                                    <div class="detail-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Pending Clearances</div>
                                        <div class="detail-bottom"><?= count($pendingClearanceRequests) ?></div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </section>

                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-users"></i> Today's Patients</h4>
                            <div class="stat-value"><?= count($todayVisits) ?></div>
                            <div class="stat-label">Clinic visits recorded</div>
                        </div>
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-clock"></i> Average Wait Time</h4>
                            <div class="stat-value"><?= $analytics['average_duration'] ?>m</div>
                            <div class="stat-label">Last 30 days</div>
                        </div>
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-pills"></i> Low Stock Alerts</h4>
                            <div class="stat-value"><?= count($lowStockMedicines) ?></div>
                            <div class="stat-label">Medicines need restocking</div>
                        </div>
                        <div class="stat-card">
                            <h4><i class="fa-solid fa-calendar-check"></i> Pending Clearances</h4>
                            <div class="stat-value"><?= count($pendingClearanceRequests) ?></div>
                            <div class="stat-label">Awaiting approval</div>
                        </div>
                    </div>

                    <!-- Clinic Status Management -->
                    <div id="clinic-status" class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-clock"></i> Clinic Status Management</h2>
                        </div>
                        <div class="clinic-card" style="<?= !$clinicStatus['is_available'] ? 'background: linear-gradient(135deg, #dc3545, #c82333); border: 2px solid #a71d2a;' : '' ?>">
                            <h3><i class="fa-solid fa-<?= $clinicStatus['is_available'] ? 'circle-check' : 'circle-xmark' ?>"></i> Current Status: <?= $clinicStatus['is_available'] ? 'OPEN - Nurse Available' : 'CLOSED - No Nurse Available' ?></h3>
                            <p><?= $clinicStatus['is_available'] ? 'Clinic is currently accepting patients' : 'Clinic is currently CLOSED and NOT accepting patients' ?></p>
                            <?php if (!$clinicStatus['is_available'] && $clinicStatus['reason']): ?>
                                <p><strong>Reason:</strong> <?= htmlspecialchars($clinicStatus['reason']) ?></p>
                            <?php endif; ?>
                        </div>

                        <form method="POST">
                            <div class="form-group">
                                <label for="clinic_status_select">Clinic Nurse Status</label>
                                <select id="clinic_status_select" name="is_available" required>
                                    <option value="1" <?= $clinicStatus['is_available'] ? 'selected' : '' ?>>Nurse Available - Clinic Open</option>
                                    <option value="0" <?= !$clinicStatus['is_available'] ? 'selected' : '' ?>>No Nurse Available - Clinic Closed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="reason">Additional Details/Reason</label>
                                <input type="text" id="reason" name="reason" placeholder="e.g., Lunch break, Medical emergency, etc." value="<?= htmlspecialchars($clinicStatus['reason'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="duration_hours">Duration (hours, optional)</label>
                                <input type="number" id="duration_hours" name="duration_hours" min="1" max="24" placeholder="Leave empty for indefinite">
                            </div>
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i> Update Clinic Status
                            </button>
                        </form>
                    </div>

                    <!-- Medicine Inventory Management -->
                    <div id="medicine-inventory" class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-pills"></i> Medicine Inventory</h2>
                        </div>

                        <?php if (!empty($lowStockMedicines)): ?>
                        <div class="clinic-card">
                            <h3><i class="fa-solid fa-exclamation-triangle"></i> Low Stock Alerts</h3>
                            <p>The following medicines are running low and need restocking:</p>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Current Stock</th>
                                        <th>Minimum Required</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockMedicines as $medicine): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($medicine['name']) ?></td>
                                        <td><?= htmlspecialchars($medicine['stock_quantity']) ?></td>
                                        <td><?= htmlspecialchars($medicine['min_stock_level']) ?></td>
                                        <td><span class="priority-high">Low Stock</span></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="medicine_id" value="<?= $medicine['id'] ?>">
                                                <input type="number" name="new_stock" placeholder="New stock" min="0" required style="width: 80px;">
                                                <button type="submit" name="update_medicine_stock" class="btn btn-success btn-sm">
                                                    <i class="fa-solid fa-plus"></i> Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($expiringMedicines)): ?>
                        <div class="clinic-card">
                            <h3><i class="fa-solid fa-calendar-times"></i> Expiring Soon</h3>
                            <p>The following medicines are expiring within 30 days:</p>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Batch Number</th>
                                        <th>Expiry Date</th>
                                        <th>Days Left</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expiringMedicines as $medicine): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($medicine['name']) ?></td>
                                        <td><?= htmlspecialchars($medicine['batch_number']) ?></td>
                                        <td><?= htmlspecialchars(date('M d, Y', strtotime($medicine['expiry_date']))) ?></td>
                                        <td>
                                            <?php
                                            $daysLeft = floor((strtotime($medicine['expiry_date']) - time()) / (60*60*24));
                                            $statusClass = $daysLeft <= 7 ? 'priority-high' : ($daysLeft <= 14 ? 'priority-medium' : 'priority-low');
                                            ?>
                                            <span class="<?= $statusClass ?>"><?= $daysLeft ?> days</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Announcements Management -->
                    <div id="announcements" class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-bullhorn"></i> Health Announcements</h2>
                            <button type="button" class="btn btn-primary" onclick="toggleAnnouncementForm()">
                                <i class="fa-solid fa-plus"></i> Create Announcement
                            </button>
                        </div>

                        <div id="announcement-form" style="display: none; margin-bottom: 20px;">
                            <div class="clinic-card">
                                <h3>Create New Announcement</h3>
                            </div>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="announcement_title">Title</label>
                                    <input type="text" id="announcement_title" name="announcement_title" required>
                                </div>
                                <div class="form-group">
                                    <label for="announcement_category">Category</label>
                                    <select id="announcement_category" name="announcement_category" required>
                                        <option value="">Select category</option>
                                        <option value="health">Health & Safety</option>
                                        <option value="services">Clinic Services</option>
                                        <option value="vaccination">Vaccination</option>
                                        <option value="emergency">Emergency</option>
                                        <option value="general">General</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="announcement_description">Description</label>
                                    <textarea id="announcement_description" name="announcement_description" rows="4" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="target_audience">Target Audience</label>
                                    <select id="target_audience" name="target_audience" required>
                                        <option value="all">All Students & Teachers</option>
                                        <option value="students">Students Only</option>
                                        <option value="teachers">Teachers Only</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="priority">Priority</label>
                                    <select id="priority" name="priority" required>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="duration_days">Display Duration (days)</label>
                                    <input type="number" id="duration_days" name="duration_days" min="1" max="30" value="7" required>
                                </div>
                                <button type="submit" name="create_announcement" class="btn btn-primary">
                                    <i class="fa-solid fa-paper-plane"></i> Create Announcement
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="toggleAnnouncementForm()">Cancel</button>
                            </form>
                        </div>

                        <?php if (!empty($activeAnnouncements)): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Target</th>
                                        <th>Created</th>
                                        <th>Expires</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeAnnouncements as $announcement): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($announcement['title']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($announcement['category'])) ?></td>
                                        <td><span class="priority-<?= htmlspecialchars($announcement['priority']) ?>"><?= ucfirst(htmlspecialchars($announcement['priority'])) ?></span></td>
                                        <td><?= htmlspecialchars(ucfirst($announcement['target_audience'])) ?></td>
                                        <td><?= htmlspecialchars(date('M d, Y', strtotime($announcement['created_at']))) ?></td>
                                        <td><?= htmlspecialchars(date('M d, Y', strtotime($announcement['expires_at']))) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="clinic-card">
                            <h3><i class="fa-solid fa-info-circle"></i> No Active Announcements</h3>
                            <p>Create health announcements to inform students and teachers about important updates.</p>
                        </div>
                        <?php endif; ?>
                    </div>
            </div>
        </main>
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

        function toggleAnnouncementForm() {
            const form = document.getElementById('announcement-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

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
