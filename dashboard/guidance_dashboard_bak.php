<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

if (!in_array($user['role'], ['student', 'teacher'], true)) {
    header('Location: ../auth/student_login.php');
    exit;
}

ensure_guidance_schema();

$message = '';
$messageType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_guidance_request'])) {
        // Handle guidance request submission
        $typeOfConcern = trim($_POST['type_of_concern'] ?? '');
        $incidentDescription = trim($_POST['incident_description'] ?? '');
        $teacherAwarenessRequired = isset($_POST['teacher_awareness_required']) ? 1 : 0;
        $parentGuardianNotified = isset($_POST['parent_guardian_notified']) ? 1 : 0;

        if ($user['role'] === 'teacher') {
            $reportedStudentId = null;
            $studentIdInput = trim($_POST['student_id'] ?? '');
            if ($studentIdInput === '') {
                $message = 'Please enter the student ID of the reported student.';
                $messageType = 'error';
            } else {
                $reportedStudent = find_user_by_student_id($studentIdInput);
                if (!$reportedStudent || $reportedStudent['role'] !== 'student') {
                    $message = 'Student ID not found. Please enter a valid student ID.';
                    $messageType = 'error';
                } else {
                    $reportedStudentId = $reportedStudent['id'];
                }
            }
            $reportType = $_POST['report_type'] === 'teacher_report' ? 'teacher_report' : 'student_report';
        } else {
            $reportedStudentId = $user['id'];
            $reportType = 'self_request';
        }

        if ($message === '' && ($typeOfConcern === '' || $incidentDescription === '')) {
            $message = 'Please complete the concern and incident description.';
            $messageType = 'error';
        }

        if ($message === '') {
            $caseData = [
                'case_number' => generate_guidance_case_number(),
                'reported_student_id' => $reportedStudentId,
                'reporter_user_id' => $user['id'],
                'reporter_role' => $user['role'],
                'report_type' => $reportType,
                'type_of_concern' => $typeOfConcern,
                'course_year' => $user['course_year'] ?? '',
                'incident_description' => $incidentDescription,
                'parent_guardian_notified' => $parentGuardianNotified,
                'teacher_awareness_required' => $teacherAwarenessRequired,
            ];

            $caseId = create_guidance_case($caseData);
            if ($caseId) {
                if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $uploadDirectory = __DIR__ . '/uploads/guidance';
                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0755, true);
                    }

                    $fileName = uniqid('guidance_') . '_' . basename($_FILES['attachment']['name']);
                    $filePath = $uploadDirectory . '/' . $fileName;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $filePath)) {
                        add_guidance_case_attachment($caseId, $user['id'], 'uploads/guidance/' . $fileName, $_FILES['attachment']['name'], $_FILES['attachment']['type']);
                    }
                }
                $message = 'Your guidance request has been submitted and forwarded to the counselor.';
                $messageType = 'success';
            } else {
                $message = 'Unable to submit the guidance request. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif (isset($_POST['add_session'])) {
        // Handle counselor session logging
        if ($user['role'] === 'teacher' && $user['head_service'] === 'guidance') {
            $caseId = (int)($_POST['case_id'] ?? 0);
            $sessionDate = $_POST['session_date'] ?? '';
            $sessionCategory = $_POST['session_category'] ?? '';
            $participationScope = $_POST['participation_scope'] ?? '';
            $attendanceStatus = $_POST['attendance_status'] ?? '';
            $notes = trim($_POST['notes'] ?? '');

            if ($caseId <= 0 || empty($sessionDate) || empty($sessionCategory) || empty($participationScope) || empty($attendanceStatus)) {
                $message = 'Please fill in all required session details.';
                $messageType = 'error';
            } else {
                $sessionData = [
                    'case_id' => $caseId,
                    'counselor_id' => $user['id'],
                    'session_date' => $sessionDate,
                    'session_category' => $sessionCategory,
                    'participation_scope' => $participationScope,
                    'attendance_status' => $attendanceStatus,
                    'notes' => $notes,
                ];

                if (add_guidance_session($sessionData)) {
                    $message = 'Session logged successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Unable to log the session. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    } elseif (isset($_POST['update_case'])) {
        // Handle counselor case updates
        if ($user['role'] === 'teacher' && $user['head_service'] === 'guidance') {
            $caseId = (int)($_POST['case_id'] ?? 0);
            $externalResolution = $_POST['external_resolution'] ?? '';
            $internalResolution = $_POST['internal_resolution'] ?? '';
            $internalNotes = trim($_POST['internal_notes'] ?? '');

            if ($caseId <= 0) {
                $message = 'Invalid case ID.';
                $messageType = 'error';
            } else {
                $updateData = [
                    'external_resolution' => $externalResolution,
                    'internal_resolution' => $internalResolution,
                    'internal_notes' => $internalNotes,
                ];

                if (update_guidance_case($caseId, $updateData)) {
                    $message = 'Case updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Unable to update the case. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get data for display
$cases = get_guidance_cases_for_user($user['id']);
$allCases = ($user['role'] === 'teacher' && $user['head_service'] === 'guidance') ? get_guidance_cases_for_counselor() : [];
$sessions = [];
if ($user['role'] === 'teacher' && $user['head_service'] === 'guidance') {
    foreach ($allCases as $case) {
        $caseSessions = get_guidance_sessions_for_case($case['id']);
        $sessions[$case['id']] = $caseSessions;
    }
}

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
$topbarInfo = build_user_dashboard_header_info($user);
$roleLabel = $user['role'] === 'teacher' ? 'Teacher' : 'Student';
$dashboardLink = $user['role'] === 'teacher' ? 'teacher_home.php' : 'student_home.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$servicesOpen = in_array($currentPage, ['guidance_home.php', 'nurse_home.php', 'ssc_head_home.php', 'ssaa_home.php'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Guidance Services | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .guidance-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .guidance-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .guidance-card h3 {
            margin: 0 0 8px 0;
            font-size: 16px;
            color: #1e293b;
        }

        .guidance-card p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .guidance-card .status-value {
            font-size: 24px;
            font-weight: 600;
            color: #7a2b2b;
            margin-bottom: 4px;
        }

        .guidance-tab-bar {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
            background: #ffffff;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }

        .guidance-tab-button {
            padding: 12px 24px;
            border: none;
            background: #f8fafc;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 3px solid transparent;
        }

        .guidance-tab-button:hover {
            background: #f1f5f9;
            color: #475569;
        }

        .guidance-tab-button.active {
            background: #ffffff;
            color: #7a2b2b;
            border-bottom-color: #7a2b2b;
        }

        .guidance-panel {
            display: none;
            background: #ffffff;
            border-radius: 0 0 8px 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .guidance-panel.active {
            display: block;
        }

        body.guidance-tab-loading .guidance-panel {
            display: none !important;
        }

        body.guidance-tab-loading .guidance-panel.active {
            display: block !important;
        }

        .case-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .case-table th,
        .case-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .case-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }

        .case-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending_review { background: #fef3c7; color: #d97706; }
        .status-under_counseling { background: #dbeafe; color: #2563eb; }
        .status-returned_for_clarification { background: #fee2e2; color: #dc2626; }
        .status-awaiting_session { background: #f3e8ff; color: #7c3aed; }
        .status-closed { background: #d1fae5; color: #059669; }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            background: #ffffff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
        }

        .toast-success {
            border-left: 4px solid #10b981;
        }

        .toast-error {
            border-left: 4px solid #ef4444;
        }

        .toast-content {
            flex: 1;
        }

        .toast-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #64748b;
        }
    </style>
</head>
<body class="guidance-tab-loading">
    <div id="toast-container" class="toast-container"></div>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>Guidance Portal</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Menu</h3>
                <a href="<?= htmlspecialchars($dashboardLink) ?>"><span class="nav-icon">⌂</span>Dashboard</a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="true">
                        <span class="nav-icon">▸</span>
                        Services
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu open" aria-hidden="false">
                        <a href="guidance_dashboard.php" class="active"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Guidance</a>
                        <a href="library_dashboard.php"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Library</a>
                        <a href="clinic_dashboard.php"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>Clinic</a>
                        <a href="ssc/ssc_dashboard.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>SSC</a>
                        <a href="scholarship/scholarship_dashboard.php"><span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>Scholarship</a>
                        <a href="ssaa_student_home.php"><span class="nav-icon"><i class="fa-solid fa-users"></i></span>SSAA</a>
                    </div>
                </div>
                <a href="profile.php"><span class="nav-icon">👤</span>Profile</a>
                <a href="about.php"><span class="nav-icon">ℹ</span>About</a>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link"><span class="nav-icon">↪</span>Logout</a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="search" placeholder="Search guidance services" />
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($topbarInfo['displayMeta']) ?></span>
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
            <div class="main-scroll">
                <div class="content-panel">
                    <?php if (!empty($message)): ?>
                        <div class="dashboard-alert <?= $messageType === 'error' ? 'alert error' : 'alert success' ?>" style="margin-bottom: 20px;">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="guidance-header">
                        <div>
                            <h1><i class="fa-solid fa-user-graduate"></i> Guidance Services</h1>
                            <p>Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</p>
                        </div>
                        <div class="guidance-header-meta">
                            <div><strong><?= htmlspecialchars($roleLabel) ?></strong></div>
                            <div><?= htmlspecialchars($topbarInfo['displayMeta']) ?></div>
                        </div>
                    </div>

                    <section class="guidance-status-grid">
                        <div class="guidance-card status-card">
                            <span class="status-label">My Cases</span>
                            <strong><?= htmlspecialchars(count($cases)) ?></strong>
                            <p>Active guidance cases</p>
                        </div>
                        <?php if ($user['role'] === 'teacher' && $user['head_service'] === 'guidance'): ?>
                        <div class="guidance-card status-card">
                            <span class="status-label">Total Cases</span>
                            <strong><?= htmlspecialchars(count($allCases)) ?></strong>
                            <p>All guidance cases under management</p>
                        </div>
                        <div class="guidance-card status-card">
                            <span class="status-label">Pending Review</span>
                            <strong><?= htmlspecialchars(count(array_filter($allCases, fn($c) => $c['status'] === 'pending_review'))) ?></strong>
                            <p>Cases awaiting counselor review</p>
                        </div>
                        <?php endif; ?>
                    </section>

                    <section class="guidance-tab-bar" role="tablist" aria-label="Guidance navigation">
                        <button type="button" class="guidance-tab-button active" data-tab="submit">Submit Request</button>
                        <button type="button" class="guidance-tab-button" data-tab="cases">My Cases</button>
                        <?php if ($user['role'] === 'teacher' && $user['head_service'] === 'guidance'): ?>
                        <button type="button" class="guidance-tab-button" data-tab="counselor">Counselor Hub</button>
                        <?php endif; ?>
                    </section>

                    <!-- Submit Request Tab -->
                    <section class="guidance-panel active" id="submit">
                        <div class="service-panel">
                            <h2>Submit Guidance Request</h2>
                            <p>Submit a counseling or behavior support request that will be reviewed by the guidance counselor.</p>

                            <form method="post" enctype="multipart/form-data" style="max-width: 600px;">
                                <?php if ($user['role'] === 'teacher'): ?>
                                <div class="form-group">
                                    <label for="report_type">Report Type</label>
                                    <select name="report_type" id="report_type" required>
                                        <option value="teacher_report">Teacher Report</option>
                                        <option value="student_report">Student Report</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="student_id">Student ID</label>
                                    <input type="text" name="student_id" id="student_id" placeholder="Enter student ID" required>
                                </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label for="type_of_concern">Type of Concern</label>
                                    <select name="type_of_concern" id="type_of_concern" required>
                                        <option value="">Select concern type</option>
                                        <option value="Academic">Academic</option>
                                        <option value="Behavioral">Behavioral</option>
                                        <option value="Personal">Personal</option>
                                        <option value="Social">Social</option>
                                        <option value="Career">Career</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="incident_description">Description</label>
                                    <textarea name="incident_description" id="incident_description" rows="4" placeholder="Describe the incident or concern in detail" required></textarea>
                                </div>

                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="parent_guardian_notified" value="1">
                                        Parent/Guardian has been notified
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="teacher_awareness_required" value="1">
                                        Teacher awareness required
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label for="attachment">Attachment (optional)</label>
                                    <input type="file" name="attachment" id="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <small>Supported formats: PDF, DOC, DOCX, JPG, JPEG, PNG</small>
                                </div>

                                <button type="submit" name="submit_guidance_request" class="btn btn-primary">Submit Request</button>
                            </form>
                        </div>
                    </section>

                    <!-- My Cases Tab -->
                    <section class="guidance-panel" id="cases">
                        <div class="service-panel">
                            <h2>My Guidance Cases</h2>
                            <p>Track the status of your guidance requests and counseling sessions.</p>

                            <?php if (empty($cases)): ?>
                                <div class="empty-state">
                                    <i class="fa-solid fa-inbox"></i>
                                    <h3>No cases found</h3>
                                    <p>You haven't submitted any guidance requests yet.</p>
                                </div>
                            <?php else: ?>
                                <table class="case-table">
                                    <thead>
                                        <tr>
                                            <th>Case Number</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cases as $case): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($case['case_number']) ?></td>
                                            <td><?= htmlspecialchars($case['type_of_concern']) ?></td>
                                            <td>
                                                <span class="case-status status-<?= htmlspecialchars(str_replace('_', '-', $case['status'])) ?>">
                                                    <?= htmlspecialchars(str_replace('_', ' ', ucfirst($case['status']))) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars(date('M d, Y', strtotime($case['created_at']))) ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm" onclick="viewCaseDetails(<?= $case['id'] ?>)">View Details</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Counselor Hub Tab (only for guidance counselors) -->
                    <?php if ($user['role'] === 'teacher' && $user['head_service'] === 'guidance'): ?>
                    <section class="guidance-panel" id="counselor">
                        <div class="service-panel">
                            <h2>Counselor Dashboard</h2>
                            <p>Manage guidance cases, log counseling sessions, and track student progress.</p>

                            <div style="margin-bottom: 32px;">
                                <h3>All Cases</h3>
                                <?php if (empty($allCases)): ?>
                                    <div class="empty-state">
                                        <i class="fa-solid fa-folder-open"></i>
                                        <h3>No cases found</h3>
                                        <p>No guidance cases have been submitted yet.</p>
                                    </div>
                                <?php else: ?>
                                    <table class="case-table">
                                        <thead>
                                            <tr>
                                                <th>Case Number</th>
                                                <th>Student</th>
                                                <th>Reporter</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Submitted</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allCases as $case): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($case['case_number']) ?></td>
                                                <td><?= htmlspecialchars($case['reported_student_name']) ?></td>
                                                <td><?= htmlspecialchars($case['reporter_name']) ?></td>
                                                <td><?= htmlspecialchars($case['type_of_concern']) ?></td>
                                                <td>
                                                    <span class="case-status status-<?= htmlspecialchars(str_replace('_', '-', $case['status'])) ?>">
                                                        <?= htmlspecialchars(str_replace('_', ' ', ucfirst($case['status']))) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars(date('M d, Y', strtotime($case['created_at']))) ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm" onclick="manageCase(<?= $case['id'] ?>)">Manage</button>
                                                    <button type="button" class="btn btn-sm" onclick="logSession(<?= $case['id'] ?>)">Log Session</button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals for case management -->
    <div id="caseModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Case Details</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="caseModalBody">
                <!-- Case details will be loaded here -->
            </div>
        </div>
    </div>

    <div id="sessionModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Log Counseling Session</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" id="sessionForm">
                    <input type="hidden" name="case_id" id="sessionCaseId">

                    <div class="form-group">
                        <label for="session_date">Session Date</label>
                        <input type="date" name="session_date" id="session_date" required>
                    </div>

                    <div class="form-group">
                        <label for="session_category">Session Category</label>
                        <select name="session_category" id="session_category" required>
                            <option value="under_guidance_counseling">Under Guidance Counseling</option>
                            <option value="parent_guardian_conference">Parent/Guardian Conference</option>
                            <option value="teacher_awareness">Teacher Awareness</option>
                            <option value="professional_evaluation">Professional Evaluation</option>
                            <option value="follow_up_session">Follow-up Session</option>
                            <option value="behavioral_monitoring">Behavioral Monitoring</option>
                            <option value="conflict_mediation">Conflict Mediation</option>
                            <option value="academic_support_referral">Academic Support Referral</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="participation_scope">Participation Scope</label>
                        <select name="participation_scope" id="participation_scope" required>
                            <option value="student_only">Student Only</option>
                            <option value="with_parent_guardian">With Parent/Guardian</option>
                            <option value="with_teacher_awareness">With Teacher Awareness</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="attendance_status">Attendance Status</label>
                        <select name="attendance_status" id="attendance_status" required>
                            <option value="attended">Attended</option>
                            <option value="non_appearance">Non-appearance</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Session Notes</label>
                        <textarea name="notes" id="notes" rows="4" placeholder="Document the counseling session details, outcomes, and next steps"></textarea>
                    </div>

                    <button type="submit" name="add_session" class="btn btn-primary">Log Session</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        const tabs = document.querySelectorAll('.guidance-tab-button');
        const panels = document.querySelectorAll('.guidance-panel');
        const storageKey = 'guidanceDashboardActiveTab';

        const activateTab = (targetId, save = true) => {
            if (!targetId) return;
            tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === targetId));
            panels.forEach(panel => panel.classList.toggle('active', panel.id === targetId));

            if (save) {
                try {
                    sessionStorage.setItem(storageKey, targetId);
                } catch (e) {
                    // ignore storage failures
                }
                window.location.hash = targetId;
            }
        };

        tabs.forEach(button => {
            button.addEventListener('click', function () {
                const target = this.dataset.tab;
                activateTab(target);
            });
        });

        const initialHash = window.location.hash.substring(1);
        const storedTab = sessionStorage.getItem(storageKey);
        if (initialHash) {
            activateTab(initialHash, false);
        } else if (storedTab) {
            activateTab(storedTab, false);
        } else {
            activateTab('submit', false);
        }

        // Modal functionality
        function viewCaseDetails(caseId) {
            // Load case details via AJAX or show modal
            document.getElementById('caseModal').style.display = 'block';
            // TODO: Load case details
        }

        function manageCase(caseId) {
            // Open case management modal
            document.getElementById('caseModal').style.display = 'block';
            // TODO: Load case management form
        }

        function logSession(caseId) {
            document.getElementById('sessionCaseId').value = caseId;
            document.getElementById('sessionModal').style.display = 'block';
        }

        // Close modals
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.modal').style.display = 'none';
            });
        });

        // Toast notifications
        function showToast(message, type = 'success', duration = 5000) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fa-solid ${type === 'error' ? 'fa-exclamation-triangle' : 'fa-check-circle'}"></i>
                    <span>${message}</span>
                </div>
                <button type="button" class="toast-close">&times;</button>
            `;

            container.appendChild(toast);

            const removeToast = () => {
                toast.remove();
            };

            toast.querySelector('.toast-close').addEventListener('click', removeToast);

            if (duration > 0) {
                setTimeout(removeToast, duration);
            }
        }

        // Show toast for messages
        const dashboardAlert = document.querySelector('.dashboard-alert');
        if (dashboardAlert) {
            const isError = dashboardAlert.classList.contains('error');
            const message = dashboardAlert.textContent.trim();
            showToast(message, isError ? 'error' : 'success', 5000);
        }

        // Remove tab loading class after initialization
        document.body.classList.remove('guidance-tab-loading');
    </script>
</body>
</html>