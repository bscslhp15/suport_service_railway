<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

if ($user['role'] !== 'teacher' || $user['head_service'] !== 'guidance') {
    if (in_array($user['role'], ['student', 'teacher'], true)) {
        header('Location: guidance_dashboard.php');
    } else {
        header('Location: ../auth/student_login.php');
    }
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
ensure_guidance_schema();

$message = '';
$messageType = 'success';
$studentSearchResult = null;
$studentSearchStatus = null;
$caseDetailForUpdate = null;

// ========== HANDLE FORM SUBMISSIONS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Accept new case
    if ($action === 'accept_case') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        if ($caseId > 0 && update_guidance_case($caseId, ['status' => 'under_counseling'])) {
            $message = 'Case accepted and moved to Under Counseling.';
            $messageType = 'success';
        } else {
            $message = 'Unable to accept case.';
            $messageType = 'error';
        }
    }
    
    // Return case for clarification
    elseif ($action === 'return_case') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $clarificationNotes = trim($_POST['clarification_notes'] ?? '');
        if ($caseId > 0 && update_guidance_case($caseId, [
            'status' => 'returned_for_clarification',
            'internal_notes' => $clarificationNotes ?: 'Case returned for clarification.'
        ])) {
            $message = 'Case returned for clarification. Reporter has been notified.';
            $messageType = 'success';
        } else {
            $message = 'Unable to return case.';
            $messageType = 'error';
        }
    }
    
    // Log counseling session with observation
    elseif ($action === 'log_session') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $sessionCategory = $_POST['session_category'] ?? 'under_guidance_counseling';
        $participationScope = $_POST['participation_scope'] ?? 'student_only';
        $attendanceStatus = $_POST['attendance_status'] ?? 'pending';
        $observationNotes = trim($_POST['observation_notes'] ?? '');
        
        // Validate observation for diagnostic terms
        $validation = validate_observation_text($observationNotes);
        if (!$validation['valid']) {
            $message = 'Observation validation failed: ' . $validation['message'];
            $messageType = 'error';
        } elseif ($caseId > 0) {
            if (add_guidance_session([
                'case_id' => $caseId,
                'counselor_id' => $user['id'],
                'session_date' => (new DateTime())->format('Y-m-d H:i:s'),
                'session_category' => $sessionCategory,
                'participation_scope' => $participationScope,
                'attendance_status' => $attendanceStatus,
                'notes' => $observationNotes,
            ])) {
                update_guidance_case($caseId, ['status' => 'under_counseling']);
                $message = 'Session logged successfully. Attendance status: ' . htmlspecialchars($attendanceStatus) . '.';
                $messageType = 'success';
            } else {
                $message = 'Unable to log session.';
                $messageType = 'error';
            }
        }
    }
    
    // Update dual-layer resolution
    elseif ($action === 'update_resolution') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $externalResolution = $_POST['external_resolution'] ?? 'none';
        $internalResolution = $_POST['internal_resolution'] ?? 'none';
        $internalNotes = trim($_POST['internal_notes'] ?? '');
        
        $newStatus = ($internalResolution === 'closed_case' || $externalResolution === 'case_resolved') ? 'closed' : 'under_counseling';
        
        if ($caseId > 0 && update_guidance_case($caseId, [
            'external_resolution' => $externalResolution,
            'internal_resolution' => $internalResolution,
            'internal_notes' => $internalNotes,
            'status' => $newStatus,
        ])) {
            $message = 'Resolution updated successfully.';
            $messageType = 'success';
        } else {
            $message = 'Unable to update resolution.';
            $messageType = 'error';
        }
    }
    
    // Search student for Good Moral evaluation
    elseif ($action === 'search_student') {
        $query = trim($_POST['student_query'] ?? '');
        if ($query !== '') {
            $studentSearchResult = find_user_by_student_id($query);
            if (!$studentSearchResult) {
                $results = search_student_by_name_or_id($query, 10);
                if (count($results) === 1) {
                    $studentSearchResult = $results[0];
                }
            }
            if ($studentSearchResult) {
                $studentSearchStatus = compute_guidance_good_moral_status((int)$studentSearchResult['id']);
            } else {
                $studentSearchStatus = ['status' => 'not_found', 'reason' => 'Student not found.'];
            }
        }
    }
}

// ========== GET DATA FOR DASHBOARD ==========
$allCases = get_guidance_cases_for_counselor();
$newCases = array_filter($allCases, fn($item) => $item['status'] === 'pending_review');
$activeCases = array_filter($allCases, fn($item) => in_array($item['status'], ['under_counseling', 'returned_for_clarification', 'awaiting_session'], true));
$closedCases = array_filter($allCases, fn($item) => $item['status'] === 'closed');

// Count non-appearances for auto-flagging
$nonAppearanceFlags = 0;
$pdo = get_db();
$stmt = $pdo->query("SELECT COUNT(*) FROM guidance_sessions WHERE attendance_status = 'non_appearance'");
$nonAppearanceFlags = (int)$stmt->fetchColumn();

// Tab navigation
$currentPage = basename($_SERVER['PHP_SELF']);
$servicesOpen = in_array($currentPage, ['guidance_home.php', 'librarian_home.php', 'nurse_home.php', 'ssc_head_home.php', 'ssaa_student_home.php'], true);
$manageOpen = isset($_GET['tab']) && in_array($_GET['tab'], ['good-moral', 'reports'], true);
$currentTab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Guidance Counselor Dashboard | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .dashboard-section { margin-bottom: 32px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .cases-table-wrapper { overflow-x: auto; margin-bottom: 20px; }
        .cases-table { width: 100%; border-collapse: collapse; }
        .cases-table th, .cases-table td { padding: 12px 10px; border: 1px solid #e2e8f0; text-align: left; }
        .cases-table th { background: #f8fafc; font-weight: 600; }
        .cases-table tr:hover { background: #f9fafb; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .metric-card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; background: #ffffff; }
        .metric-card h3 { margin: 0 0 8px 0; color: #6b7280; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-card .value { font-size: 32px; font-weight: 700; color: #1f2937; margin: 8px 0; }
        .metric-card .status { font-size: 13px; color: #6b7280; }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
        .alert-error { background: #fee2e2; border-left: 4px solid #ef4444; color: #7f1d1d; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 6px; color: #1f2937; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; font-family: monospace; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .btn-group { display: flex; gap: 8px; }
        .button { padding: 10px 16px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; background: white; margin-bottom: 16px; }
        .card h3 { margin-top: 0; color: #1f2937; }
        .flagged-item { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; border-radius: 6px; margin-bottom: 10px; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #7f1d1d; }
        .private-notes-section { background: #fef3c7; border: 2px solid #f59e0b; padding: 16px; border-radius: 8px; margin-top: 12px; }
        .private-notes-section .label { color: #92400e; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .blocked-term-alert { background: #fee2e2; border-left: 4px solid #dc2626; padding: 12px; border-radius: 6px; color: #7f1d1d; margin-bottom: 12px; font-size: 13px; }
        .auto-flag-icon { color: #f59e0b; font-weight: bold; }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>Guidance Counselor</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Menu</h3>
                <a href="guidance_home.php" class="<?= $currentTab === 'dashboard' ? 'active' : '' ?>"><span class="nav-icon"><i class="fa-solid fa-home"></i></span>Dashboard</a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $servicesOpen ? 'true' : 'false' ?>">
                        <span class="nav-icon">▸</span>Services<span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu<?= $servicesOpen ? ' open' : '' ?>" aria-hidden="<?= $servicesOpen ? 'false' : 'true' ?>">
                        <a href="guidance_home.php"><i class="fa-solid fa-user-graduate"></i>Guidance</a>
                        <a href="librarian_home.php"><i class="fa-solid fa-book"></i>Library</a>
                        <a href="nurse_home.php"><i class="fa-solid fa-stethoscope"></i>Clinic</a>
                        <a href="ssc_head_home.php"><i class="fa-solid fa-award"></i>SSC/Scholarship</a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $manageOpen ? 'true' : 'false' ?>">
                        <span class="nav-icon">▸</span>Manage<span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu<?= $manageOpen ? ' open' : '' ?>" aria-hidden="<?= $manageOpen ? 'false' : 'true' ?>">
                        <a href="guidance_home.php?tab=cases"><i class="fa-solid fa-file-circle-plus"></i>Case Management</a>
                        <a href="guidance_home.php?tab=good-moral"><i class="fa-solid fa-certificate"></i>Good Moral</a>
                        <a href="guidance_home.php?tab=reports"><i class="fa-solid fa-chart-line"></i>Reports</a>
                    </div>
                </div>
                <a href="../logout.php"><span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
            </div>
        </aside>

        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="search" placeholder="Search cases or students" />
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Guidance Counselor</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></button>
                </div>
            </header>

            <div class="main-scroll">
                <div class="content-panel">
                    <!-- DASHBOARD OVERVIEW -->
                    <?php if ($currentTab === 'dashboard'): ?>
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow">Guidance Management System</p>
                            <h1>Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</h1>
                            <p class="dashboard-subtitle">Comprehensive case tracking, clinical documentation, and evidence-based decision-making for student support.</p>
                        </div>
                        <div class="dashboard-summary">
                            <div>
                                <span>Today's Date</span>
                                <strong><?= date('F j, Y') ?></strong>
                            </div>
                            <div>
                                <span>Active Cases</span>
                                <strong><?= count($activeCases) ?></strong>
                            </div>
                            <div>
                                <span>Pending Review</span>
                                <strong><?= count($newCases) ?></strong>
                            </div>
                            <div>
                                <span>Flagged Issues</span>
                                <strong><?= $nonAppearanceFlags ?></strong>
                            </div>
                        </div>
                    </section>

                    <?php if ($message !== ''): ?>
                        <div class="alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="card-grid">
                        <div class="metric-card">
                            <h3>New Cases</h3>
                            <div class="value"><?= count($newCases) ?></div>
                            <div class="status">Awaiting Initial Review</div>
                        </div>
                        <div class="metric-card">
                            <h3>Under Counseling</h3>
                            <div class="value"><?= count($activeCases) ?></div>
                            <div class="status">Active Management</div>
                        </div>
                        <div class="metric-card">
                            <h3>Closed Cases</h3>
                            <div class="value"><?= count($closedCases) ?></div>
                            <div class="status">Resolved & Archived</div>
                        </div>
                        <div class="metric-card">
                            <h3>Non-Appearances</h3>
                            <div class="value" style="color: #f59e0b;"><?= $nonAppearanceFlags ?></div>
                            <div class="status">Flagged for Review</div>
                        </div>
                    </div>

                    <!-- SECTION A: CASE MANAGEMENT HUB -->
                    <section class="dashboard-section">
                        <h2>A. Case Management Hub</h2>

                        <!-- New Cases Queue -->
                        <div class="card">
                            <h3>📋 New Cases Queue - Initial Review</h3>
                            <?php if (empty($newCases)): ?>
                                <p style="color: #6b7280;">No new cases awaiting review.</p>
                            <?php else: ?>
                                <div class="cases-table-wrapper">
                                    <table class="cases-table">
                                        <thead>
                                            <tr>
                                                <th>Case ID</th>
                                                <th>Student</th>
                                                <th>Reporter</th>
                                                <th>Concern</th>
                                                <th>Submitted</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($newCases as $case): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                    <td><?= htmlspecialchars($case['reported_student_name']) ?></td>
                                                    <td><?= htmlspecialchars($case['reporter_name']) ?> (<?= htmlspecialchars($case['reporter_role']) ?>)</td>
                                                    <td><?= htmlspecialchars(substr($case['type_of_concern'], 0, 30)) ?></td>
                                                    <td><?= date('M d, Y', strtotime($case['created_at'])) ?></td>
                                                    <td>
                                                        <form method="post" style="display: inline-block; margin-right: 6px;">
                                                            <input type="hidden" name="action" value="accept_case">
                                                            <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                            <button type="submit" class="button btn-primary" style="font-size: 12px; padding: 8px 12px;">Accept</button>
                                                        </form>
                                                        <button type="button" class="button btn-secondary" style="font-size: 12px; padding: 8px 12px;" onclick="document.getElementById('clarify_form_<?= (int)$case['id'] ?>').style.display='block';">Request Clarif.</button>
                                                    </td>
                                                </tr>
                                                <!-- Clarification Form (Hidden) -->
                                                <tr style="display: none;" id="clarify_form_<?= (int)$case['id'] ?>">
                                                    <td colspan="6" style="background: #fef3c7; padding: 16px;">
                                                        <form method="post">
                                                            <input type="hidden" name="action" value="return_case">
                                                            <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                            <div style="margin-bottom: 12px;">
                                                                <label for="clarification_<?= (int)$case['id'] ?>">Clarification Notes:</label>
                                                                <textarea name="clarification_notes" id="clarification_<?= (int)$case['id'] ?>" rows="2" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #d1d5db;" placeholder="What needs to be clarified or added?"></textarea>
                                                            </div>
                                                            <button type="submit" class="button btn-danger" style="font-size: 12px; padding: 8px 12px;">Return for Clarification</button>
                                                            <button type="button" class="button btn-secondary" style="font-size: 12px; padding: 8px 12px; margin-left: 8px;" onclick="document.getElementById('clarify_form_<?= (int)$case['id'] ?>').style.display='none';">Cancel</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Active Case Tracker -->
                        <div class="card">
                            <h3>📑 Active Case Tracker - Under Counseling</h3>
                            <?php if (empty($activeCases)): ?>
                                <p style="color: #6b7280;">No active cases in progress.</p>
                            <?php else: ?>
                                <div class="cases-table-wrapper">
                                    <table class="cases-table">
                                        <thead>
                                            <tr>
                                                <th>Case ID</th>
                                                <th>Student</th>
                                                <th>Status</th>
                                                <th>Concern</th>
                                                <th>External Resolution</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activeCases as $case): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                    <td><?= htmlspecialchars($case['reported_student_name']) ?></td>
                                                    <td><span class="status-badge badge-warning"><?= htmlspecialchars($case['status']) ?></span></td>
                                                    <td><?= htmlspecialchars(substr($case['type_of_concern'], 0, 25)) ?></td>
                                                    <td><?= htmlspecialchars($case['external_resolution'] ?: 'Pending') ?></td>
                                                    <td>
                                                        <form method="post" style="display: inline-block;">
                                                            <input type="hidden" name="action" value="log_session">
                                                            <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                            <select name="attendance_status" style="padding: 6px; font-size: 12px; border-radius: 4px; border: 1px solid #d1d5db;">
                                                                <option value="pending">Attendance</option>
                                                                <option value="attended">✓ Attended</option>
                                                                <option value="non_appearance">✗ Non-Appearance</option>
                                                            </select>
                                                            <button type="submit" class="button btn-primary" style="font-size: 12px; padding: 6px 10px;">Log</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- SECTION B: CLINICAL DOCUMENTATION -->
                    <section class="dashboard-section">
                        <h2>B. Clinical Documentation</h2>

                        <div class="card">
                            <h3>🔍 Session Logger & Observation Input</h3>
                            <?php if (empty($activeCases)): ?>
                                <p style="color: #6b7280;">Accept a case first before logging sessions.</p>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="log_session">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="session_case">Select Case:</label>
                                            <select name="case_id" id="session_case" required>
                                                <option value="">-- Select a case --</option>
                                                <?php foreach ($activeCases as $case): ?>
                                                    <option value="<?= (int)$case['id'] ?>"><?= htmlspecialchars($case['case_number'] . ' — ' . $case['reported_student_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="session_cat">Session Category:</label>
                                            <select name="session_category" id="session_cat" required>
                                                <option value="under_guidance_counseling">Under Guidance Counseling</option>
                                                <option value="parent_guardian_conference">Parent/Guardian Conference</option>
                                                <option value="teacher_awareness">With Teacher Awareness</option>
                                                <option value="professional_evaluation">Referred for Professional Evaluation</option>
                                                <option value="follow_up_session">Follow-up Session</option>
                                                <option value="behavioral_monitoring">Behavioral Monitoring</option>
                                                <option value="conflict_mediation">Conflict Mediation</option>
                                                <option value="academic_support_referral">Academic Support Referral</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="part_scope">Participation Scope:</label>
                                            <select name="participation_scope" id="part_scope" required>
                                                <option value="student_only">Student Only</option>
                                                <option value="with_parent_guardian">With Parent/Guardian</option>
                                                <option value="with_teacher_awareness">With Teacher (Limited)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="obs_notes">📝 Neutral Observation Notes (Diagnostic terms will be blocked):</label>
                                        <textarea id="obs_notes" name="observation_notes" rows="5" placeholder="Use only neutral, observation-based language. Examples: 'Student reports feeling stressed', 'Shows signs of emotional distress', 'Demonstrates low motivation', 'Difficulty concentrating observed'. BLOCKED: Depression, mental disorder, psychosis, etc." required></textarea>
                                        <small style="color: #6b7280; margin-top: 6px; display: block;">✓ Allowed: Neutral observations | ✗ Blocked: Diagnostic labels</small>
                                    </div>

                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="attend_status">Attendance Status:</label>
                                            <select name="attendance_status" id="attend_status" required>
                                                <option value="pending">Pending</option>
                                                <option value="attended">✓ Attended</option>
                                                <option value="non_appearance">✗ Non-Appearance (Auto-Flag)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <button type="submit" class="button btn-primary">Log Session & Validate Observation</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- SECTION C: DUAL-LAYER RESOLUTION MODULE -->
                    <section class="dashboard-section">
                        <h2>C. Dual-Layer Resolution Module</h2>

                        <div class="card">
                            <h3>⚖️ External & Internal Resolution Assignment</h3>
                            <?php if (empty($activeCases)): ?>
                                <p style="color: #6b7280;">Select a case to assign resolutions.</p>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="update_resolution">
                                    
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="res_case">Select Case:</label>
                                            <select name="case_id" id="res_case" required>
                                                <option value="">-- Select a case --</option>
                                                <?php foreach ($activeCases as $case): ?>
                                                    <option value="<?= (int)$case['id'] ?>"><?= htmlspecialchars($case['case_number'] . ' — ' . $case['reported_student_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- External Resolution -->
                                    <div style="background: #dbeafe; padding: 16px; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #3b82f6;">
                                        <h4 style="margin-top: 0; color: #1e40af;">🔵 EXTERNAL RESOLUTION (Visible to Student & Teacher)</h4>
                                        <p style="color: #1e40af; font-size: 13px; margin-top: 0;">Safe, neutral, constructive language only</p>
                                        <div class="form-group">
                                            <label for="ext_res">External Resolution Status:</label>
                                            <select name="external_resolution" id="ext_res">
                                                <option value="none">No external resolution yet</option>
                                                <option value="counseling_completed_follow_up">Counseling completed; follow-up required</option>
                                                <option value="advised_behavioral_improvement">Advised behavioral improvement</option>
                                                <option value="under_monitoring">Under monitoring</option>
                                                <option value="case_resolved">Case resolved</option>
                                                <option value="scheduled_for_further_sessions">Scheduled for further sessions</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Internal Resolution -->
                                    <div style="background: #fee2e2; padding: 16px; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #dc2626;">
                                        <h4 style="margin-top: 0; color: #7f1d1d;">🔴 INTERNAL RESOLUTION (Confidential - Counselor Only)</h4>
                                        <p style="color: #7f1d1d; font-size: 13px; margin-top: 0;">Clinical assessment & decision-making (hidden from students/teachers)</p>
                                        <div class="form-group">
                                            <label for="int_res">Internal Resolution Type:</label>
                                            <select name="internal_resolution" id="int_res">
                                                <option value="none">No internal resolution yet</option>
                                                <option value="with_reformation">With Reformation (Positive progress)</option>
                                                <option value="without_reformation">Without Reformation (No improvement)</option>
                                                <option value="with_warning">With Warning</option>
                                                <option value="under_monitoring">Under Monitoring</option>
                                                <option value="escalated_case">Escalated Case (Needs further action)</option>
                                                <option value="ongoing_case">Ongoing Case (Still in process)</option>
                                                <option value="closed_case">Closed Case (Complete)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Private Notes Vault -->
                                    <div class="private-notes-section">
                                        <div class="label">🔒 Private Notes Vault - Counselor Eyes Only</div>
                                        <div class="form-group">
                                            <label for="priv_notes">Confidential Internal Assessment:</label>
                                            <textarea id="priv_notes" name="internal_notes" rows="4" placeholder="Sensitive clinical observations, behavioral patterns, recommendations. NOT VISIBLE to students or teachers. Example: 'Student shows repeated non-compliance with counseling sessions', 'Low engagement observed', 'Behavioral concern – without reformation'." style="border: 1px solid #f59e0b; background: #fffbeb;"></textarea>
                                        </div>
                                    </div>

                                    <button type="submit" class="button btn-primary">Save Dual-Layer Resolution</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- SECTION D: AUTOMATED GOOD MORAL MODULE -->
                    <section class="dashboard-section">
                        <h2>D. Automated Good Moral Certificate Module</h2>

                        <div class="card">
                            <h3>🎓 Student Eligibility Evaluation</h3>
                            <form method="post" style="margin-bottom: 20px;">
                                <input type="hidden" name="action" value="search_student">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="gm_search">Search Student by ID or Name:</label>
                                        <input type="text" name="student_query" id="gm_search" placeholder="Enter student ID or full name" required>
                                    </div>
                                </div>
                                <button type="submit" class="button btn-primary">Analyze Eligibility</button>
                            </form>

                            <?php if ($studentSearchStatus !== null): ?>
                                <?php if ($studentSearchStatus['status'] === 'not_found'): ?>
                                    <div class="alert alert-error"><?= htmlspecialchars($studentSearchStatus['reason']) ?></div>
                                <?php else: ?>
                                    <div class="card" style="background: #f3f4f6; border-left: 4px solid #3b82f6;">
                                        <h4 style="margin-top: 0;">📋 Student Profile & Good Moral Analysis</h4>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                            <div>
                                                <p><strong>Name:</strong> <?= htmlspecialchars($studentSearchResult['full_name']) ?></p>
                                                <p><strong>Student ID:</strong> <?= htmlspecialchars($studentSearchResult['student_id'] ?? 'N/A') ?></p>
                                                <p><strong>Course:</strong> <?= htmlspecialchars($studentSearchResult['course_year'] ?? 'N/A') ?></p>
                                            </div>
                                            <div>
                                                <p>
                                                    <strong>Eligibility Status:</strong> 
                                                    <span class="status-badge <?= $studentSearchStatus['status'] === 'eligible' ? 'badge-success' : ($studentSearchStatus['status'] === 'not_eligible' ? 'badge-danger' : 'badge-warning') ?>">
                                                        <?= htmlspecialchars(strtoupper($studentSearchStatus['status'])) ?>
                                                    </span>
                                                </p>
                                                <p><strong>Assessment:</strong></p>
                                                <p style="margin: 0;"><?= htmlspecialchars($studentSearchStatus['reason']) ?></p>
                                            </div>
                                        </div>

                                        <!-- Auto-Flagging Engine Results -->
                                        <?php if (!empty($studentSearchStatus['flags'])): ?>
                                            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 16px 0;">
                                            <h5 style="color: #dc2626;">⚠️ Auto-Flagged Issues (System Alerts)</h5>
                                            <?php foreach ($studentSearchStatus['flags'] as $flag): ?>
                                                <div class="flagged-item">
                                                    <strong><?= htmlspecialchars($flag['type']) ?>:</strong> <?= htmlspecialchars($flag['description']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- REPORTS TAB -->
                    <?php elseif ($currentTab === 'reports'): ?>
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow">Reports & Analytics</p>
                            <h1>System Reports</h1>
                            <p class="dashboard-subtitle">Aggregate statistics, trends, and system insights.</p>
                        </div>
                    </section>

                    <div class="card-grid">
                        <div class="metric-card">
                            <h3>Total Cases</h3>
                            <div class="value"><?= count($allCases) ?></div>
                            <div class="status">All-time</div>
                        </div>
                        <div class="metric-card">
                            <h3>Resolution Rate</h3>
                            <div class="value"><?= count($allCases) > 0 ? round((count($closedCases) / count($allCases)) * 100) : 0 ?>%</div>
                            <div class="status">Closed vs Total</div>
                        </div>
                        <div class="metric-card">
                            <h3>Non-Appearances</h3>
                            <div class="value" style="color: #f59e0b;"><?= $nonAppearanceFlags ?></div>
                            <div class="status">Impact on eligibility</div>
                        </div>
                    </div>

                    <div class="card">
                        <h3>📊 Case Status Distribution</h3>
                        <table class="cases-table">
                            <tr>
                                <td>New Cases (Pending Review)</td>
                                <td><?= count($newCases) ?></td>
                            </tr>
                            <tr>
                                <td>Active Cases (Under Counseling)</td>
                                <td><?= count($activeCases) ?></td>
                            </tr>
                            <tr>
                                <td>Closed Cases</td>
                                <td><?= count($closedCases) ?></td>
                            </tr>
                        </table>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Real-time diagnostic term blocking
        document.getElementById('obs_notes')?.addEventListener('input', function() {
            const blockedTerms = ['depression', 'depressed', 'psychosis', 'psychotic', 'schizophrenia', 'bipolar', 'adhd', 'ptsd', 'mental illness', 'insanity', 'personality disorder'];
            const text = this.value.toLowerCase();
            let foundTerms = [];
            blockedTerms.forEach(term => {
                if (text.includes(term)) foundTerms.push(term);
            });
            if (foundTerms.length > 0) {
                this.style.borderColor = '#dc2626';
                this.style.background = '#fee2e2';
            } else {
                this.style.borderColor = '#d1d5db';
                this.style.background = '#ffffff';
            }
        });

        // Navigation toggle
        document.querySelectorAll('.nav-toggle').forEach(btn => {
            btn.addEventListener('click', function() {
                const submenu = this.nextElementSibling;
                const isOpen = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !isOpen);
                submenu.setAttribute('aria-hidden', isOpen);
                submenu.classList.toggle('open');
            });
        });
    </script>
</body>
</html>
