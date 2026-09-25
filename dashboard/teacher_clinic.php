<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
$user = current_user();

// Initialize clinic schema
ensure_clinic_schema();

// Get clinic data for teacher
$clinicStatus = get_clinic_status();
$userHealthRecord = get_user_health_record($user['id']);
$todayHealthData = get_today_health_data($user['id']);
$userClearanceRequests = get_user_clearance_requests($user['id']);
$activeAnnouncements = get_active_clinic_announcements('teachers');

// Handle form submissions (similar to student clinic)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_health_record'])) {
        $data = [
            'medical_history' => $_POST['medical_history'] ?? null,
            'allergies' => $_POST['allergies'] ?? null,
            'current_medications' => $_POST['current_medications'] ?? null,
            'blood_type' => $_POST['blood_type'] ?? null,
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
            'emergency_contact_relationship' => $_POST['emergency_contact_relationship'] ?? null
        ];

        if (save_health_record($user['id'], $data)) {
            $message = 'Health record saved successfully!';
            $userHealthRecord = get_user_health_record($user['id']);
        } else {
            $message = 'Failed to save health record.';
        }
    }

    if (isset($_POST['save_daily_health'])) {
        $data = [
            'sleep_hours' => !empty($_POST['sleep_hours']) ? (float)$_POST['sleep_hours'] : null,
            'water_intake_liters' => !empty($_POST['water_intake_liters']) ? (float)$_POST['water_intake_liters'] : null,
            'height_cm' => !empty($_POST['height_cm']) ? (float)$_POST['height_cm'] : null,
            'weight_kg' => !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null,
            'mood' => $_POST['mood'] ?? null
        ];

        if (save_daily_health_data($user['id'], $data)) {
            $message = 'Daily health data saved successfully!';
            $todayHealthData = get_today_health_data($user['id']);
        } else {
            $message = 'Failed to save daily health data.';
        }
    }

    if (isset($_POST['submit_clearance_request'])) {
        $data = [
            'request_type' => $_POST['request_type'],
            'purpose' => $_POST['purpose'],
            'documents_uploaded' => $_POST['documents_uploaded'] ?? null
        ];

        if (submit_clearance_request($user['id'], $data)) {
            $message = 'Clearance request submitted successfully!';
            $userClearanceRequests = get_user_clearance_requests($user['id']);
        } else {
            $message = 'Failed to submit clearance request.';
        }
    }

    if (isset($_POST['submit_feedback'])) {
        $data = [
            'rating' => (int)$_POST['rating'],
            'comments' => $_POST['comments'] ?? null,
            'service_quality' => !empty($_POST['service_quality']) ? (int)$_POST['service_quality'] : null,
            'staff_attitude' => !empty($_POST['staff_attitude']) ? (int)$_POST['staff_attitude'] : null,
            'waiting_time' => !empty($_POST['waiting_time']) ? (int)$_POST['waiting_time'] : null,
            'facility_cleanliness' => !empty($_POST['facility_cleanliness']) ? (int)$_POST['facility_cleanliness'] : null
        ];

        if (submit_clinic_feedback($user['id'], $data)) {
            $message = 'Feedback submitted successfully!';
        } else {
            $message = 'Failed to submit feedback.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinic Services | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-rose: #FFB6C1;
            --clinic-light: #FFF8DC;
        }

        .clinic-status-banner {
            background: linear-gradient(135deg, var(--clinic-maroon), var(--clinic-gold));
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .clinic-status-banner.available {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .clinic-status-banner.unavailable {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--clinic-maroon), var(--clinic-gold));
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .welcome-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
        }

        .welcome-text h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .welcome-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
            line-height: 1.5;
        }

        .clinic-status-widget {
            text-align: center;
            min-width: 200px;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .status-indicator.available {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .status-indicator.unavailable {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .status-details {
            font-size: 12px;
            opacity: 0.8;
        }

        .announcements-section {
            margin-bottom: 30px;
        }

        .announcements-section h3 {
            color: var(--clinic-maroon);
            margin-bottom: 15px;
            font-size: 20px;
        }

        .announcements-list {
            display: grid;
            gap: 15px;
        }

        .clinic-tabs {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .tab-navigation {
            display: flex;
            background: var(--clinic-light);
            border-bottom: 1px solid #e0e0e0;
            overflow-x: auto;
        }

        .tab-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 100px;
            border-bottom: 3px solid transparent;
            color: var(--clinic-maroon);
        }

        .tab-button:hover {
            background: rgba(128, 0, 0, 0.05);
        }

        .tab-button.active {
            background: var(--clinic-maroon);
            color: white;
            border-bottom-color: var(--clinic-gold);
        }

        .tab-button i {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .tab-button span {
            font-size: 12px;
            font-weight: 600;
        }

        .tab-content {
            padding: 0;
        }

        .tab-panel {
            display: none;
            padding: 30px;
        }

        .tab-panel.active {
            display: block;
        }

        .tab-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--clinic-light);
        }

        .tab-header h3 {
            color: var(--clinic-maroon);
            margin: 0 0 8px 0;
            font-size: 24px;
        }

        .tab-header p {
            color: #666;
            margin: 0;
            font-size: 14px;
        }

        .tab-body {
            max-width: none;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .service-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid var(--clinic-maroon);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }

        .service-card h3 {
            color: var(--clinic-maroon);
            margin: 0 0 15px 0;
            font-size: 20px;
        }

        .service-card p {
            color: #666;
            margin: 0 0 20px 0;
            line-height: 1.6;
        }

        .health-form {
            background: var(--clinic-light);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .health-form h4 {
            color: var(--clinic-maroon);
            margin: 0 0 15px 0;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
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
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
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

        .health-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .metric-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--clinic-maroon);
            margin-bottom: 5px;
        }

        .metric-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .announcement-card {
            background: linear-gradient(135deg, var(--clinic-gold), var(--clinic-rose));
            color: var(--clinic-maroon);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .announcement-card h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }

        .announcement-card p {
            margin: 0;
            font-size: 14px;
        }

        .rating-stars {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }

        .rating-stars input[type="radio"] {
            display: none;
        }

        .rating-stars label {
            font-size: 25px;
            color: #ddd;
            cursor: pointer;
        }

        .rating-stars input[type="radio"]:checked ~ label,
        .rating-stars label:hover,
        .rating-stars label:hover ~ label {
            color: #ffc107;
        }

        .alert-card {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            color: #155724;
        }

        .alert-card.warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
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

        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }
        .status-requires_more_info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>Teacher Portal</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Services</h3>
                <a href="teacher_home.php"><span class="nav-icon"><i class="fa-solid fa-home"></i></span>Dashboard</a>
                <a href="teacher_clinic.php" class="active"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>Clinic</a>
                <a href="teacher_library.php"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Library</a>
                <a href="teacher_guidance.php"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Guidance</a>
                <a href="teacher_ssc.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>SSC/Scholarship</a>
                <a href="teacher_ssaa.php"><span class="nav-icon"><i class="fa-solid fa-users"></i></span>SSAA</a>
            </div>
            <div class="nav-footer">
                <a href="#profile"><span class="nav-icon"><i class="fa-solid fa-user"></i></span>Profile</a>
                <a href="../logout.php"><span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
            </div>
        </aside>

        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="search" placeholder="Search clinic services or health info" />
                        <button type="button" class="mic-button"><i class="fa-solid fa-microphone"></i></button>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Teacher · <?= htmlspecialchars($user['course_department'] ?? 'Department') ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                </div>
            </header>

            <div class="main-scroll">
                <div class="content-panel">
                    <?php if ($message): ?>
                        <div class="alert-card">
                            <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Welcome Card -->
                    <div class="welcome-card">
                        <div class="welcome-content">
                            <div class="welcome-text">
                                <h2><i class="fa-solid fa-stethoscope"></i> Welcome to Clinic Services</h2>
                                <p>Access comprehensive healthcare services, manage your health records, and stay informed about your well-being.</p>
                            </div>
                            <div class="clinic-status-widget">
                                <div class="status-indicator <?= $clinicStatus['is_available'] ? 'available' : 'unavailable' ?>">
                                    <i class="fa-solid fa-<?= $clinicStatus['is_available'] ? 'check-circle' : 'times-circle' ?>"></i>
                                    <span>Clinic is <?= $clinicStatus['is_available'] ? 'OPEN' : 'CLOSED' ?></span>
                                </div>
                                <?php if (!$clinicStatus['is_available'] && $clinicStatus['reason']): ?>
                                    <div class="status-details">
                                        <small><strong>Reason:</strong> <?= htmlspecialchars($clinicStatus['reason']) ?></small>
                                        <?php if ($clinicStatus['duration_hours']): ?>
                                            <small>Expected to reopen in <?= $clinicStatus['duration_hours'] ?> hours</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Clinic Announcements -->
                    <?php if (count($activeAnnouncements) > 0): ?>
                        <div class="announcements-section">
                            <h3><i class="fa-solid fa-bullhorn"></i> Clinic Announcements</h3>
                            <div class="announcements-list">
                                <?php foreach ($activeAnnouncements as $announcement): ?>
                                    <div class="announcement-card">
                                        <h4><?= htmlspecialchars($announcement['title']) ?></h4>
                                        <p><?= htmlspecialchars($announcement['description']) ?></p>
                                        <small>
                                            <i class="fa-solid fa-calendar"></i> Posted <?= date('M j, Y', strtotime($announcement['created_at'])) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Tabbed Services Interface -->
                    <div class="clinic-tabs">
                        <div class="tab-navigation">
                            <button class="tab-button active" onclick="switchTab('health-records')">
                                <i class="fa-solid fa-heart-pulse"></i>
                                <span>Health Records</span>
                            </button>
                            <button class="tab-button" onclick="switchTab('daily-health')">
                                <i class="fa-solid fa-chart-line"></i>
                                <span>Daily Health</span>
                            </button>
                            <button class="tab-button" onclick="switchTab('medicine-check')">
                                <i class="fa-solid fa-pills"></i>
                                <span>Medicines</span>
                            </button>
                            <button class="tab-button" onclick="switchTab('clearance-request')">
                                <i class="fa-solid fa-clipboard-check"></i>
                                <span>Clearance</span>
                            </button>
                            <button class="tab-button" onclick="switchTab('qr-visit')">
                                <i class="fa-solid fa-qrcode"></i>
                                <span>Clinic Visit</span>
                            </button>
                            <button class="tab-button" onclick="switchTab('feedback')">
                                <i class="fa-solid fa-comments"></i>
                                <span>Feedback</span>
                            </button>
                        </div>

                        <div class="tab-content">
                            <!-- Health Records Tab -->
                            <div id="health-records" class="tab-panel active">
                                <div class="tab-header">
                                    <h3><i class="fa-solid fa-heart-pulse"></i> Digital Health Card</h3>
                                    <p>Maintain your medical history, allergies, and emergency contacts</p>
                                </div>
                                <div class="tab-body">
                                    <form method="POST">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="blood_type">Blood Type:</label>
                                                <select id="blood_type" name="blood_type">
                                                    <option value="">Select Blood Type</option>
                                                    <option value="A+" <?= ($userHealthRecord['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                                                    <option value="A-" <?= ($userHealthRecord['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                                                    <option value="B+" <?= ($userHealthRecord['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                                                    <option value="B-" <?= ($userHealthRecord['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                                                    <option value="AB+" <?= ($userHealthRecord['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                                                    <option value="AB-" <?= ($userHealthRecord['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                                                    <option value="O+" <?= ($userHealthRecord['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                                                    <option value="O-" <?= ($userHealthRecord['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="medical_history">Medical History:</label>
                                            <textarea id="medical_history" name="medical_history" placeholder="List any past medical conditions, surgeries, or significant health events"><?= htmlspecialchars($userHealthRecord['medical_history'] ?? '') ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="allergies">Allergies:</label>
                                            <textarea id="allergies" name="allergies" placeholder="List any known allergies (medications, foods, environmental)"><?= htmlspecialchars($userHealthRecord['allergies'] ?? '') ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="current_medications">Current Medications:</label>
                                            <textarea id="current_medications" name="current_medications" placeholder="List any medications you are currently taking"><?= htmlspecialchars($userHealthRecord['current_medications'] ?? '') ?></textarea>
                                        </div>
                                        <h4 style="margin-top: 20px;">Emergency Contact</h4>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="emergency_contact_name">Contact Name:</label>
                                                <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= htmlspecialchars($userHealthRecord['emergency_contact_name'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="emergency_contact_relationship">Relationship:</label>
                                                <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" value="<?= htmlspecialchars($userHealthRecord['emergency_contact_relationship'] ?? '') ?>" placeholder="e.g., Spouse, Family Member">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="emergency_contact_phone">Contact Phone:</label>
                                            <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= htmlspecialchars($userHealthRecord['emergency_contact_phone'] ?? '') ?>">
                                        </div>
                                        <button type="submit" name="save_health_record" class="btn btn-primary">Save Health Record</button>
                                    </form>
                                </div>
                            </div>

                            <!-- Daily Health Tab -->
                            <div id="daily-health" class="tab-panel">
                                <div class="tab-header">
                                    <h3><i class="fa-solid fa-chart-line"></i> Daily Health Monitoring</h3>
                                    <p>Track your daily health metrics and get personalized recommendations</p>
                                </div>
                                <div class="tab-body">
                                    <?php if ($todayHealthData): ?>
                                        <div class="health-metrics">
                                            <div class="metric-card">
                                                <div class="metric-value"><?= $todayHealthData['bmi'] ?? 'N/A' ?></div>
                                                <div class="metric-label">BMI</div>
                                            </div>
                                            <div class="metric-card">
                                                <div class="metric-value"><?= $todayHealthData['sleep_hours'] ?? 'N/A' ?>h</div>
                                                <div class="metric-label">Sleep</div>
                                            </div>
                                            <div class="metric-card">
                                                <div class="metric-value"><?= $todayHealthData['water_intake_liters'] ?? 'N/A' ?>L</div>
                                                <div class="metric-label">Water Intake</div>
                                            </div>
                                            <div class="metric-card">
                                                <div class="metric-value"><?= $todayHealthData['weight_kg'] ?? 'N/A' ?>kg</div>
                                                <div class="metric-label">Weight</div>
                                            </div>
                                        </div>
                                        <div class="alert-card">
                                            <strong>Health Status:</strong> <?= htmlspecialchars($todayHealthData['health_status']) ?>
                                            <?php if ($todayHealthData['recommendations']): ?>
                                                <br><strong>Recommendations:</strong> <?= htmlspecialchars($todayHealthData['recommendations']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="sleep_hours">Sleep Hours:</label>
                                                <input type="number" id="sleep_hours" name="sleep_hours" step="0.5" min="0" max="24" value="<?= $todayHealthData['sleep_hours'] ?? '' ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="water_intake_liters">Water Intake (Liters):</label>
                                                <input type="number" id="water_intake_liters" name="water_intake_liters" step="0.1" min="0" value="<?= $todayHealthData['water_intake_liters'] ?? '' ?>">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="height_cm">Height (cm):</label>
                                                <input type="number" id="height_cm" name="height_cm" step="0.1" min="50" max="250" value="<?= $todayHealthData['height_cm'] ?? '' ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="weight_kg">Weight (kg):</label>
                                                <input type="number" id="weight_kg" name="weight_kg" step="0.1" min="20" max="300" value="<?= $todayHealthData['weight_kg'] ?? '' ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="mood">Today's Mood:</label>
                                            <select id="mood" name="mood">
                                                <option value="">Select Mood</option>
                                                <option value="excellent" <?= ($todayHealthData['mood'] ?? '') === 'excellent' ? 'selected' : '' ?>>Excellent</option>
                                                <option value="good" <?= ($todayHealthData['mood'] ?? '') === 'good' ? 'selected' : '' ?>>Good</option>
                                                <option value="fair" <?= ($todayHealthData['mood'] ?? '') === 'fair' ? 'selected' : '' ?>>Fair</option>
                                                <option value="poor" <?= ($todayHealthData['mood'] ?? '') === 'poor' ? 'selected' : '' ?>>Poor</option>
                                                <option value="stressed" <?= ($todayHealthData['mood'] ?? '') === 'stressed' ? 'selected' : '' ?>>Stressed</option>
                                            </select>
                                        </div>
                                        <button type="submit" name="save_daily_health" class="btn btn-primary">Save Daily Health Data</button>
                                    </form>
                                </div>
                            </div>

                            <!-- Medicine Check Tab -->
                            <div id="medicine-check" class="tab-panel">
                                <div class="tab-header">
                                    <h3><i class="fa-solid fa-pills"></i> Medicine Availability</h3>
                                    <p>Check availability of common medicines and first aid supplies</p>
                                </div>
                                <div class="tab-body">
                                    <div class="form-group">
                                        <label for="medicine_search">Search Medicine:</label>
                                        <input type="text" id="medicine_search" placeholder="Enter medicine name..." onkeyup="searchMedicines()">
                                    </div>
                                    <div id="medicine-results">
                                        <p style="color: #666; text-align: center;">Enter a medicine name to check availability</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Clearance Request Tab -->
                            <div id="clearance-request" class="tab-panel">
                                <div class="tab-header">
                                    <h3><i class="fa-solid fa-clipboard-check"></i> Medical Clearance</h3>
                                    <p>Request medical clearance for professional development or health requirements</p>
                                </div>
                                <div class="tab-body">
                                    <?php if (count($userClearanceRequests) > 0): ?>
                                        <h5>Your Clearance Requests</h5>
                                        <div class="table-responsive">
                                            <table class="data-table">
                                                <thead>
                                                    <tr>
                                                        <th>Type</th>
                                                        <th>Purpose</th>
                                                        <th>Status</th>
                                                        <th>Submitted</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($userClearanceRequests as $request): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($request['request_type']) ?></td>
                                                            <td><?= htmlspecialchars($request['purpose']) ?></td>
                                                            <td><span class="status-<?= str_replace('_', '-', $request['status']) ?>"><?= ucfirst(str_replace('_', ' ', $request['status'])) ?></span></td>
                                                            <td><?= date('M j, Y', strtotime($request['created_at'])) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>

                                    <h5>Submit New Clearance Request</h5>
                                    <form method="POST">
                                        <div class="form-group">
                                            <label for="request_type">Clearance Type:</label>
                                            <select id="request_type" name="request_type" required>
                                                <option value="">Select Type</option>
                                                <option value="professional_development">Professional Development</option>
                                                <option value="overseas_assignment">Overseas Assignment</option>
                                                <option value="health_screening">Health Screening</option>
                                                <option value="insurance">Insurance Application</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="purpose">Purpose/Details:</label>
                                            <textarea id="purpose" name="purpose" required placeholder="Please provide details about why you need medical clearance"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="documents_uploaded">Supporting Documents (optional):</label>
                                            <textarea id="documents_uploaded" name="documents_uploaded" placeholder="List any documents you've uploaded or plan to submit"></textarea>
                                        </div>
                                        <button type="submit" name="submit_clearance_request" class="btn btn-primary">Submit Clearance Request</button>
                                    </form>
                                </div>
                            </div>

                            <!-- QR Visit Tab -->
                            <div id="qr-visit" class="tab-panel">
                                <div class="tab-header">
                                    <h3><i class="fa-solid fa-qrcode"></i> Clinic Visit</h3>
                                    <p>Log your clinic visit using QR code for efficient check-in</p>
                                </div>
                                <div class="tab-body">
                                    <div class="qr-section">
                                        <div class="qr-placeholder">
                                            <i class="fa-solid fa-qrcode"></i>
                                            <span>QR Code</span>
                                            <small>Scan at clinic entrance</small>
                                        </div>
                                        <div class="alert-card warning">
                                            <strong>Note:</strong> If QR scanning is not available, please inform the clinic staff and they will assist you with manual check-in.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Feedback Tab -->
                            <div id="feedback" class="tab-panel">
                                <div class="tab-header">
                                    <h3><i class="fa-solid fa-comments"></i> Service Feedback</h3>
                                    <p>Share your experience and help us improve our clinic services</p>
                                </div>
                                <div class="tab-body">
                                    <form method="POST">
                                        <div class="form-group">
                                            <label>Overall Rating:</label>
                                            <div class="rating-stars">
                                                <input type="radio" id="star5" name="rating" value="5"><label for="star5">★</label>
                                                <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                                                <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                                                <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                                                <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="service_quality">Service Quality:</label>
                                                <select id="service_quality" name="service_quality">
                                                    <option value="">Rate Service Quality</option>
                                                    <option value="5">Excellent</option>
                                                    <option value="4">Very Good</option>
                                                    <option value="3">Good</option>
                                                    <option value="2">Fair</option>
                                                    <option value="1">Poor</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="staff_attitude">Staff Attitude:</label>
                                                <select id="staff_attitude" name="staff_attitude">
                                                    <option value="">Rate Staff Attitude</option>
                                                    <option value="5">Excellent</option>
                                                    <option value="4">Very Good</option>
                                                    <option value="3">Good</option>
                                                    <option value="2">Fair</option>
                                                    <option value="1">Poor</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="waiting_time">Waiting Time:</label>
                                                <select id="waiting_time" name="waiting_time">
                                                    <option value="">Rate Waiting Time</option>
                                                    <option value="5">Very Short</option>
                                                    <option value="4">Short</option>
                                                    <option value="3">Reasonable</option>
                                                    <option value="2">Long</option>
                                                    <option value="1">Very Long</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="facility_cleanliness">Facility Cleanliness:</label>
                                                <select id="facility_cleanliness" name="facility_cleanliness">
                                                    <option value="">Rate Cleanliness</option>
                                                    <option value="5">Excellent</option>
                                                    <option value="4">Very Good</option>
                                                    <option value="3">Good</option>
                                                    <option value="2">Fair</option>
                                                    <option value="1">Poor</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="comments">Additional Comments:</label>
                                            <textarea id="comments" name="comments" placeholder="Share your experience or suggestions for improvement"></textarea>
                                        </div>
                                        <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        function switchTab(tabId) {
            // Hide all tab panels
            const tabPanels = document.querySelectorAll('.tab-panel');
            tabPanels.forEach(panel => panel.classList.remove('active'));

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));

            // Show selected tab panel
            const selectedTab = document.getElementById(tabId);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }

            // Add active class to clicked button
            const clickedButton = document.querySelector(`[onclick="switchTab('${tabId}')"]`);
            if (clickedButton) {
                clickedButton.classList.add('active');
            }
        }

        function searchMedicines() {
            const searchTerm = document.getElementById('medicine_search').value;
            const resultsDiv = document.getElementById('medicine-results');

            if (searchTerm.length < 2) {
                resultsDiv.innerHTML = '<p style="color: #666; text-align: center;">Enter at least 2 characters to search</p>';
                return;
            }

            // This would typically make an AJAX call to search medicines
            // For now, show a placeholder
            resultsDiv.innerHTML = `
                <div class="alert-card">
                    <strong>Search Results for "${searchTerm}":</strong><br>
                    Searching clinic medicine database...<br>
                    <small style="color: #666;">Note: This feature requires server-side implementation for real medicine search.</small>
                </div>
            `;
        }

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-card');
            alerts.forEach(alert => {
                if (!alert.classList.contains('warning')) {
                    alert.style.display = 'none';
                }
            });
        }, 5000);
    </script>
</body>
</html>
                    <div id="daily-health" class="health-form" style="display: none;">
                        <h4><i class="fa-solid fa-chart-line"></i> Daily Health Monitoring</h4>
                        <?php if ($todayHealthData): ?>
                            <div class="health-metrics">
                                <div class="metric-card">
                                    <div class="metric-value"><?= $todayHealthData['bmi'] ?? 'N/A' ?></div>
                                    <div class="metric-label">BMI</div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-value"><?= $todayHealthData['sleep_hours'] ?? 'N/A' ?>h</div>
                                    <div class="metric-label">Sleep</div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-value"><?= $todayHealthData['water_intake_liters'] ?? 'N/A' ?>L</div>
                                    <div class="metric-label">Water Intake</div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-value"><?= $todayHealthData['weight_kg'] ?? 'N/A' ?>kg</div>
                                    <div class="metric-label">Weight</div>
                                </div>
                            </div>
                            <div class="alert-card">
                                <strong>Health Status:</strong> <?= htmlspecialchars($todayHealthData['health_status']) ?>
                                <?php if ($todayHealthData['recommendations']): ?>
                                    <br><strong>Recommendations:</strong> <?= htmlspecialchars($todayHealthData['recommendations']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="sleep_hours">Sleep Hours:</label>
                                    <input type="number" id="sleep_hours" name="sleep_hours" step="0.5" min="0" max="24" value="<?= $todayHealthData['sleep_hours'] ?? '' ?>">
                                </div>
                                <div class="form-group">
                                    <label for="water_intake_liters">Water Intake (Liters):</label>
                                    <input type="number" id="water_intake_liters" name="water_intake_liters" step="0.1" min="0" value="<?= $todayHealthData['water_intake_liters'] ?? '' ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="height_cm">Height (cm):</label>
                                    <input type="number" id="height_cm" name="height_cm" step="0.1" min="50" max="250" value="<?= $todayHealthData['height_cm'] ?? '' ?>">
                                </div>
                                <div class="form-group">
                                    <label for="weight_kg">Weight (kg):</label>
                                    <input type="number" id="weight_kg" name="weight_kg" step="0.1" min="20" max="300" value="<?= $todayHealthData['weight_kg'] ?? '' ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="mood">Today's Mood:</label>
                                <select id="mood" name="mood">
                                    <option value="">Select Mood</option>
                                    <option value="excellent" <?= ($todayHealthData['mood'] ?? '') === 'excellent' ? 'selected' : '' ?>>Excellent</option>
                                    <option value="good" <?= ($todayHealthData['mood'] ?? '') === 'good' ? 'selected' : '' ?>>Good</option>
                                    <option value="fair" <?= ($todayHealthData['mood'] ?? '') === 'fair' ? 'selected' : '' ?>>Fair</option>
                                    <option value="poor" <?= ($todayHealthData['mood'] ?? '') === 'poor' ? 'selected' : '' ?>>Poor</option>
                                    <option value="stressed" <?= ($todayHealthData['mood'] ?? '') === 'stressed' ? 'selected' : '' ?>>Stressed</option>
                                </select>
                            </div>
                            <button type="submit" name="save_daily_health" class="btn btn-primary">Save Daily Health Data</button>
                        </form>
                    </div>

                    <!-- Medicine Check Section -->
                    <div id="medicine-check" class="health-form" style="display: none;">
                        <h4><i class="fa-solid fa-pills"></i> Medicine Availability Check</h4>
                        <div class="form-group">
                            <label for="medicine_search">Search Medicine:</label>
                            <input type="text" id="medicine_search" placeholder="Enter medicine name..." onkeyup="searchMedicines()">
                        </div>
                        <div id="medicine-results">
                            <p style="color: #666; text-align: center;">Enter a medicine name to check availability</p>
                        </div>
                    </div>

                    <!-- Clearance Request Section -->
                    <div id="clearance-request" class="health-form" style="display: none;">
                        <h4><i class="fa-solid fa-clipboard-check"></i> Medical Clearance Request</h4>

                        <?php if (count($userClearanceRequests) > 0): ?>
                            <h5>Your Clearance Requests</h5>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userClearanceRequests as $request): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($request['request_type']) ?></td>
                                                <td><?= htmlspecialchars($request['purpose']) ?></td>
                                                <td><span class="status-<?= str_replace('_', '-', $request['status']) ?>"><?= ucfirst(str_replace('_', ' ', $request['status'])) ?></span></td>
                                                <td><?= date('M j, Y', strtotime($request['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <h5>Submit New Clearance Request</h5>
                        <form method="POST">
                            <div class="form-group">
                                <label for="request_type">Clearance Type:</label>
                                <select id="request_type" name="request_type" required>
                                    <option value="">Select Type</option>
                                    <option value="professional_development">Professional Development</option>
                                    <option value="overseas_assignment">Overseas Assignment</option>
                                    <option value="health_screening">Health Screening</option>
                                    <option value="insurance">Insurance Application</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="purpose">Purpose/Details:</label>
                                <textarea id="purpose" name="purpose" required placeholder="Please provide details about why you need medical clearance"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="documents_uploaded">Supporting Documents (optional):</label>
                                <textarea id="documents_uploaded" name="documents_uploaded" placeholder="List any documents you've uploaded or plan to submit"></textarea>
                            </div>
                            <button type="submit" name="submit_clearance_request" class="btn btn-primary">Submit Clearance Request</button>
                        </form>
                    </div>

                    <!-- QR Visit Section -->
                    <div id="qr-visit" class="health-form" style="display: none;">
                        <h4><i class="fa-solid fa-qrcode"></i> Clinic Visit Check-in</h4>
                        <div style="text-align: center; margin-bottom: 20px;">
                            <p>Scan the QR code at the clinic entrance or use the form below to check in.</p>
                            <div style="background: white; display: inline-block; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <!-- QR Code would be generated here -->
                                <div style="width: 200px; height: 200px; border: 2px dashed #ddd; display: flex; align-items: center; justify-content: center; color: #666;">
                                    <i class="fa-solid fa-qrcode" style="font-size: 48px;"></i><br>
                                    <small>QR Code</small>
                                </div>
                            </div>
                        </div>
                        <div class="alert-card warning">
                            <strong>Note:</strong> If QR scanning is not available, please inform the clinic staff and they will assist you with manual check-in.
                        </div>
                    </div>

                    <!-- Feedback Section -->
                    <div id="feedback" class="health-form" style="display: none;">
                        <h4><i class="fa-solid fa-comments"></i> Clinic Service Feedback</h4>
                        <form method="POST">
                            <div class="form-group">
                                <label>Overall Rating:</label>
                                <div class="rating-stars">
                                    <input type="radio" id="star5" name="rating" value="5"><label for="star5">★</label>
                                    <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                                    <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                                    <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                                    <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="service_quality">Service Quality:</label>
                                    <select id="service_quality" name="service_quality">
                                        <option value="">Rate Service Quality</option>
                                        <option value="5">Excellent</option>
                                        <option value="4">Very Good</option>
                                        <option value="3">Good</option>
                                        <option value="2">Fair</option>
                                        <option value="1">Poor</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="staff_attitude">Staff Attitude:</label>
                                    <select id="staff_attitude" name="staff_attitude">
                                        <option value="">Rate Staff Attitude</option>
                                        <option value="5">Excellent</option>
                                        <option value="4">Very Good</option>
                                        <option value="3">Good</option>
                                        <option value="2">Fair</option>
                                        <option value="1">Poor</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="waiting_time">Waiting Time:</label>
                                    <select id="waiting_time" name="waiting_time">
                                        <option value="">Rate Waiting Time</option>
                                        <option value="5">Very Short</option>
                                        <option value="4">Short</option>
                                        <option value="3">Reasonable</option>
                                        <option value="2">Long</option>
                                        <option value="1">Very Long</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="facility_cleanliness">Facility Cleanliness:</label>
                                    <select id="facility_cleanliness" name="facility_cleanliness">
                                        <option value="">Rate Cleanliness</option>
                                        <option value="5">Excellent</option>
                                        <option value="4">Very Good</option>
                                        <option value="3">Good</option>
                                        <option value="2">Fair</option>
                                        <option value="1">Poor</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="comments">Additional Comments:</label>
                                <textarea id="comments" name="comments" placeholder="Share your experience or suggestions for improvement"></textarea>
                            </div>
                            <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        function switchTab(tabId) {
            // Hide all tab panels
            const tabPanels = document.querySelectorAll('.tab-panel');
            tabPanels.forEach(panel => panel.classList.remove('active'));

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));

            // Show selected tab panel
            const selectedTab = document.getElementById(tabId);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }

            // Add active class to clicked button
            const clickedButton = document.querySelector(`[onclick="switchTab('${tabId}')"]`);
            if (clickedButton) {
                clickedButton.classList.add('active');
            }
        }

        function searchMedicines() {
            const searchTerm = document.getElementById('medicine_search').value;
            const resultsDiv = document.getElementById('medicine_results');

            if (searchTerm.length < 2) {
                resultsDiv.innerHTML = '<p style="color: #666; text-align: center;">Enter at least 2 characters to search</p>';
                return;
            }

            // This would typically make an AJAX call to search medicines
            // For now, show a placeholder
            resultsDiv.innerHTML = `
                <div class="alert-card">
                    <strong>Search Results for "${searchTerm}":</strong><br>
                    Searching clinic medicine database...<br>
                    <small style="color: #666;">Note: This feature requires server-side implementation for real medicine search.</small>
                </div>
            `;
        }

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-card');
            alerts.forEach(alert => {
                if (!alert.classList.contains('warning')) {
                    alert.style.display = 'none';
                }
            });
        }, 5000);
    </script>
</body>
</html>