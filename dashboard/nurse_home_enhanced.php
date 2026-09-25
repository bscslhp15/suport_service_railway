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

// Get clinic data
$clinicStatus = get_clinic_status();
$currentVisits = get_current_clinic_visits();
$todayVisits = get_today_clinic_visits();
$lowStockMedicines = get_low_stock_medicines();
$expiringMedicines = get_expiring_medicines();
$pendingClearanceRequests = get_pending_clearance_requests();
$activeAnnouncements = get_active_clinic_announcements();
$pendingFeedback = get_pending_clinic_feedback();
$analytics = get_clinic_visit_analytics(30);

// Handle form submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $isAvailable = isset($_POST['is_available']);
        $reason = $_POST['reason'] ?? null;
        $duration = !empty($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : null;

        if (update_clinic_status($isAvailable, $reason, $duration, $user['id'])) {
            $message = 'Clinic status updated successfully!';
            $clinicStatus = get_clinic_status();
        } else {
            $message = 'Failed to update clinic status.';
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
        }
    }

    if (isset($_POST['approve_feedback'])) {
        $feedbackId = (int)$_POST['feedback_id'];
        if (approve_clinic_feedback($feedbackId, $user['id'])) {
            $message = 'Feedback approved successfully!';
            $pendingFeedback = get_pending_clinic_feedback();
        } else {
            $message = 'Failed to approve feedback.';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-rose: #FFB6C1;
            --clinic-light: #FFF8DC;
        }

        .clinic-status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
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
            font-size: 28px;
            font-weight: bold;
            color: var(--clinic-maroon);
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            color: #666;
            font-size: 14px;
        }

        .alert-card {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .alert-card.warning {
            background: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-card.success {
            background: #d4edda;
            border-color: #c3e6cb;
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
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
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
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>Clinic Head</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Menu</h3>
                <a href="#dashboard" class="active"><span class="nav-icon"><i class="fa-solid fa-tachometer-alt"></i></span>Dashboard</a>
                <a href="#clinic-status"><span class="nav-icon"><i class="fa-solid fa-clock"></i></span>Clinic Status</a>
                <a href="#health-records"><span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>Health Records</a>
                <a href="#medicine-inventory"><span class="nav-icon"><i class="fa-solid fa-pills"></i></span>Medicine Inventory</a>
                <a href="#visit-logs"><span class="nav-icon"><i class="fa-solid fa-notes-medical"></i></span>Visit Logs</a>
                <a href="#clearance-requests"><span class="nav-icon"><i class="fa-solid fa-clipboard-check"></i></span>Clearance Requests</a>
                <a href="#announcements"><span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>Announcements</a>
                <a href="#feedback"><span class="nav-icon"><i class="fa-solid fa-comments"></i></span>Feedback</a>
                <a href="#analytics"><span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>Analytics</a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false">
                        <span class="nav-icon">▸</span>
                        Other Services
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_home.php"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Guidance</a>
                        <a href="librarian_home.php"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Library</a>
                        <a href="ssc_head_home.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>SSC / Scholarship</a>
                        <a href="ssaa_home.php"><span class="nav-icon"><i class="fa-solid fa-users"></i></span>SSAA</a>
                    </div>
                </div>
            </div>
            <div class="nav-footer">
                <a href="#settings"><span class="nav-icon"><i class="fa-solid fa-gear"></i></span>Settings</a>
                <a href="../logout.php"><span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
            </div>
        </aside>

        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="search" placeholder="Search patients, medicines, or records" />
                        <button type="button" class="mic-button"><i class="fa-solid fa-microphone"></i></button>
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
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></button>
                </div>
            </header>

            <div class="main-scroll">
                <div class="content-panel">
                    <?php if ($message): ?>
                        <div class="alert-card success">
                            <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Dashboard Overview -->
                    <section id="dashboard" class="dashboard-intro">
                        <div>
                            <p class="eyebrow">Clinic Management System</p>
                            <h1>Welcome, <?= htmlspecialchars($user['full_name']) ?></h1>
                            <p class="dashboard-subtitle">Comprehensive health support and clinic management dashboard.</p>
                        </div>
                        <div class="dashboard-summary">
                            <div>
                                <span>Today's Visits</span>
                                <strong><?= count($todayVisits) ?></strong>
                            </div>
                            <div>
                                <span>Active Patients</span>
                                <strong><?= count($currentVisits) ?></strong>
                            </div>
                            <div>
                                <span>Low Stock Items</span>
                                <strong><?= count($lowStockMedicines) ?></strong>
                            </div>
                        </div>
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
                            <h4><i class="fa-solid fa-clipboard-check"></i> Pending Clearances</h4>
                            <div class="stat-value"><?= count($pendingClearanceRequests) ?></div>
                            <div class="stat-label">Awaiting review</div>
                        </div>
                    </div>

                    <!-- Alerts Section -->
                    <?php if (count($lowStockMedicines) > 0): ?>
                        <div class="alert-card warning">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <strong>Low Stock Alert:</strong> <?= count($lowStockMedicines) ?> medicine(s) are running low on stock.
                            <a href="#medicine-inventory" class="btn btn-warning" style="margin-left: 10px;">View Inventory</a>
                        </div>
                    <?php endif; ?>

                    <?php if (count($expiringMedicines) > 0): ?>
                        <div class="alert-card warning">
                            <i class="fa-solid fa-calendar-times"></i>
                            <strong>Expiry Alert:</strong> <?= count($expiringMedicines) ?> medicine(s) are expiring within 30 days.
                            <a href="#medicine-inventory" class="btn btn-warning" style="margin-left: 10px;">Check Expiry</a>
                        </div>
                    <?php endif; ?>

                    <!-- Current Patients Queue -->
                    <div class="clinic-card">
                        <h3><i class="fa-solid fa-users"></i> Current Patients in Queue</h3>
                        <p>Active clinic visits requiring attention</p>
                        <?php if (count($currentVisits) > 0): ?>
                            <div class="table-responsive" style="margin-top: 20px;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Time In</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($currentVisits as $visit): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($visit['patient_name'] ?? $visit['name']) ?></td>
                                                <td><?= date('H:i', strtotime($visit['time_in'])) ?></td>
                                                <td><span class="priority-<?= $visit['priority'] ?>"><?= ucfirst($visit['priority']) ?></span></td>
                                                <td><span class="status-<?= str_replace(' ', '-', $visit['status']) ?>"><?= ucfirst(str_replace('_', ' ', $visit['status'])) ?></span></td>
                                                <td>
                                                    <button class="btn btn-primary" onclick="viewPatient(<?= $visit['id'] ?>)">View</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="margin-top: 20px; color: rgba(255,255,255,0.8);">No active patients in queue.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="clinic-card">
                        <h3><i class="fa-solid fa-bolt"></i> Quick Actions</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                            <button class="btn btn-primary" onclick="openModal('statusModal')">
                                <i class="fa-solid fa-clock"></i> Update Clinic Status
                            </button>
                            <button class="btn btn-secondary" onclick="openModal('announcementModal')">
                                <i class="fa-solid fa-bullhorn"></i> Create Announcement
                            </button>
                            <button class="btn btn-success" onclick="openModal('visitModal')">
                                <i class="fa-solid fa-plus"></i> Log New Visit
                            </button>
                            <a href="#analytics" class="btn btn-warning" style="text-align: center;">
                                <i class="fa-solid fa-chart-bar"></i> View Analytics
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <!-- Clinic Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h3>Update Clinic Status</h3>
            <form method="POST">
                <div class="form-group">
                    <label><input type="checkbox" name="is_available" <?= $clinicStatus['is_available'] ? 'checked' : '' ?>> Clinic is Open</label>
                </div>
                <div class="form-group">
                    <label for="reason">Reason (if closed):</label>
                    <input type="text" id="reason" name="reason" value="<?= htmlspecialchars($clinicStatus['reason'] ?? '') ?>" placeholder="e.g., Staff meeting, Holiday">
                </div>
                <div class="form-group">
                    <label for="duration_hours">Duration (hours):</label>
                    <input type="number" id="duration_hours" name="duration_hours" value="<?= htmlspecialchars($clinicStatus['duration_hours'] ?? '') ?>" placeholder="Leave empty for indefinite">
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <h3>Create Announcement</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="announcement_title">Title:</label>
                    <input type="text" id="announcement_title" name="announcement_title" required>
                </div>
                <div class="form-group">
                    <label for="announcement_category">Category:</label>
                    <select id="announcement_category" name="announcement_category">
                        <option value="health">Health & Safety</option>
                        <option value="services">Clinic Services</option>
                        <option value="emergency">Emergency</option>
                        <option value="general">General</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="announcement_description">Description:</label>
                    <textarea id="announcement_description" name="announcement_description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="target_audience">Target Audience:</label>
                    <select id="target_audience" name="target_audience">
                        <option value="all">All Users</option>
                        <option value="students">Students Only</option>
                        <option value="teachers">Teachers Only</option>
                        <option value="staff">Staff Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="priority">Priority:</label>
                    <select id="priority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="duration_days">Duration (days):</label>
                    <input type="number" id="duration_days" name="duration_days" value="7" min="1">
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('announcementModal')">Cancel</button>
                    <button type="submit" name="create_announcement" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Visit Modal -->
    <div id="visitModal" class="modal">
        <div class="modal-content">
            <h3>Log New Clinic Visit</h3>
            <form method="POST" action="process_visit.php">
                <div class="form-group">
                    <label for="visitor_name">Patient Name:</label>
                    <input type="text" id="visitor_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="visitor_type">Visitor Type:</label>
                    <select id="visitor_type" name="visitor_type" required>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="visitor">Visitor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="student_number">Student Number (if student):</label>
                    <input type="text" id="student_number" name="student_number">
                </div>
                <div class="form-group">
                    <label for="reason">Reason for Visit:</label>
                    <textarea id="reason" name="reason_for_visit" required></textarea>
                </div>
                <div class="form-group">
                    <label for="priority">Priority:</label>
                    <select id="priority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('visitModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Log Visit</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        function viewPatient(visitId) {
            // Implement patient view functionality
            window.location.href = `patient_view.php?visit_id=${visitId}`;
        }

        // Auto-refresh patient queue every 30 seconds
        setInterval(function() {
            // This would typically use AJAX to refresh the patient queue
            // For now, we'll just reload alerts if needed
        }, 30000);
    </script>
</body>
</html>