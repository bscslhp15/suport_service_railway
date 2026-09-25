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

ensure_guidance_schema();
$message = '';
$messageType = 'success';
$studentSearchResult = null;
$studentSearchStatus = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'accept_case') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        if ($caseId > 0 && update_guidance_case($caseId, ['status' => 'under_counseling'])) {
            $message = 'Case moved to Under Counseling.';
            $messageType = 'success';
        } else {
            $message = 'Unable to update case status.';
            $messageType = 'error';
        }
    } elseif ($action === 'return_case') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        if ($caseId > 0 && update_guidance_case($caseId, ['status' => 'returned_for_clarification'])) {
            $message = 'Case returned for clarification.';
            $messageType = 'success';
        } else {
            $message = 'Unable to return the case.';
            $messageType = 'error';
        }
    } elseif ($action === 'add_session') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $sessionCategory = $_POST['session_category'] ?? 'under_guidance_counseling';
        $participationScope = $_POST['participation_scope'] ?? 'student_only';
        $attendanceStatus = $_POST['attendance_status'] ?? 'pending';
        $notes = trim($_POST['session_notes'] ?? '');
        if ($caseId > 0 && add_guidance_session([
            'case_id' => $caseId,
            'counselor_id' => $user['id'],
            'session_date' => (new DateTime())->format('Y-m-d H:i:s'),
            'session_category' => $sessionCategory,
            'participation_scope' => $participationScope,
            'attendance_status' => $attendanceStatus,
            'notes' => $notes,
        ])) {
            update_guidance_case($caseId, ['status' => 'under_counseling']);
            $message = 'Session entry recorded successfully.';
            $messageType = 'success';
        } else {
            $message = 'Unable to save session entry.';
            $messageType = 'error';
        }
    } elseif ($action === 'update_resolution') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $externalResolution = $_POST['external_resolution'] ?? 'none';
        $internalResolution = $_POST['internal_resolution'] ?? 'none';
        $internalNotes = trim($_POST['internal_notes'] ?? '');
        $caseStatus = $externalResolution === 'case_resolved' || $internalResolution === 'closed_case' ? 'closed' : 'under_counseling';
        if ($caseId > 0 && update_guidance_case($caseId, [
            'external_resolution' => $externalResolution,
            'internal_resolution' => $internalResolution,
            'internal_notes' => $internalNotes,
            'status' => $caseStatus,
        ])) {
            $message = 'Resolution updated successfully.';
            $messageType = 'success';
        } else {
            $message = 'Unable to update resolution.';
            $messageType = 'error';
        }
    } elseif ($action === 'search_student') {
        $query = trim($_POST['student_query'] ?? '');
        if ($query !== '') {
            $studentSearchResult = find_user_by_student_id($query);
            if (!$studentSearchResult) {
                $students = search_student_by_name_or_id($query, 10);
                if (count($students) === 1) {
                    $studentSearchResult = $students[0];
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

$currentPage = basename($_SERVER['PHP_SELF']);
$servicesOpen = in_array($currentPage, ['guidance_home.php', 'librarian_home.php', 'nurse_home.php', 'ssc_head_home.php', 'ssaa_student_home.php'], true);
$manageOpen = in_array($currentPage, ['guidance_dashboard.php'], true) || isset($_GET['tab']) && in_array($_GET['tab'], ['good-moral', 'reports'], true);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

// Get all cases for dashboard
$allCases = get_guidance_cases_for_counselor();
$newCases = array_filter($allCases, fn($item) => $item['status'] === 'pending_review');
$activeCases = array_filter($allCases, fn($item) => in_array($item['status'], ['under_counseling', 'returned_for_clarification', 'awaiting_session'], true));
$closedCases = array_filter($allCases, fn($item) => $item['status'] === 'closed');

// Handle tab navigation
$currentTab = $_GET['tab'] ?? 'dashboard';
$tabContent = '';

if ($currentTab === 'good-moral') {
    // Good Moral tab content
    $tabContent = '
        <section class="dashboard-section">
            <h2>Good Moral Certificate Requests</h2>
            <div class="form-section">
                <h3>Student Search</h3>
                <form method="post" class="form-inline">
                    <input type="hidden" name="action" value="search_student">
                    <input type="text" name="student_query" placeholder="Enter Student ID or Name" required>
                    <button type="submit" class="btn-primary">Search</button>
                </form>
            </div>
            ' . ($studentSearchResult ? '
            <div class="student-result">
                <h3>Student Information</h3>
                <p><strong>Name:</strong> ' . htmlspecialchars($studentSearchResult['full_name']) . '</p>
                <p><strong>Student ID:</strong> ' . htmlspecialchars($studentSearchResult['student_id']) . '</p>
                <p><strong>Course:</strong> ' . htmlspecialchars($studentSearchResult['course_year']) . '</p>
                <p><strong>Status:</strong> <span class="' . ($studentSearchStatus['status'] === 'eligible' ? 'status-eligible' : 'status-not-eligible') . '">' . htmlspecialchars($studentSearchStatus['status']) . '</span></p>
                ' . ($studentSearchStatus['reason'] ? '<p><strong>Reason:</strong> ' . htmlspecialchars($studentSearchStatus['reason']) . '</p>' : '') . '
            </div>
            ' : '') . '
        </section>';
} elseif ($currentTab === 'reports') {
    // Reports tab content
    $tabContent = '
        <section class="dashboard-section">
            <h2>Guidance Reports</h2>
            <div class="reports-grid">
                <div class="report-card">
                    <h3>Case Statistics</h3>
                    <p>Total Cases: ' . count($allCases) . '</p>
                    <p>New Cases: ' . count($newCases) . '</p>
                    <p>Active Cases: ' . count($activeCases) . '</p>
                    <p>Closed Cases: ' . count($closedCases) . '</p>
                </div>
                <div class="report-card">
                    <h3>Session Summary</h3>
                    <p>This month\'s sessions will be displayed here.</p>
                </div>
                <div class="report-card">
                    <h3>Good Moral Requests</h3>
                    <p>Good Moral certificate statistics will be displayed here.</p>
                </div>
            </div>
        </section>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Guidance Counselor Dashboard | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .cases-table-wrapper { overflow-x:auto; }
        .cases-table { width:100%; border-collapse: collapse; margin-bottom: 20px; }
        .cases-table th, .cases-table td { padding: 12px 10px; border: 1px solid #e2e8f0; text-align:left; }
        .cases-table th { background: #f8fafc; }
        .card-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:16px; margin-bottom:24px; }
        .small-card { border:1px solid #e2e8f0; padding:20px; border-radius:12px; background:#ffffff; }
        .form-inline { display:grid; gap:12px; }
        .reports-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:16px; margin-top:20px; }
        .report-card { border:1px solid #e2e8f0; padding:20px; border-radius:12px; background:#ffffff; }
        .report-card h3 { margin-top:0; color:#1f2937; }
        .student-result { border:1px solid #e2e8f0; padding:20px; border-radius:12px; background:#ffffff; margin-top:20px; }
        .status-eligible { color:#059669; font-weight:bold; }
        .status-not-eligible { color:#dc2626; font-weight:bold; }
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
                <a href="guidance_home.php" class="active"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Dashboard</a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $servicesOpen ? 'true' : 'false' ?>">
                        <span class="nav-icon">▸</span>
                        Services
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu<?= $servicesOpen ? ' open' : '' ?>" aria-hidden="<?= $servicesOpen ? 'false' : 'true' ?>">
                        <a href="guidance_home.php"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Guidance</a>
                        <a href="librarian_home.php"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Library</a>
                        <a href="nurse_home.php"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>Clinic</a>
                        <a href="ssc_head_home.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>SSC / Scholarship</a>
                        <a href="ssc_head_home.php#scholarship"><span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>Scholarship</a>
                        <a href="ssaa_student_home.php"><span class="nav-icon"><i class="fa-solid fa-users"></i></span>SSAA</a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $manageOpen ? 'true' : 'false' ?>">
                        <span class="nav-icon">▸</span>
                        Manage
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu<?= $manageOpen ? ' open' : '' ?>" aria-hidden="<?= $manageOpen ? 'false' : 'true' ?>">
                        <a href="guidance_dashboard.php"><span class="nav-icon"><i class="fa-solid fa-file-circle-plus"></i></span>Student/Teacher Requests</a>
                        <a href="guidance_home.php?tab=good-moral"><span class="nav-icon"><i class="fa-solid fa-certificate"></i></span>Good Moral</a>
                        <a href="guidance_home.php?tab=reports"><span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>Reports</a>
                    </div>
                </div>
                <a href="#notifications"><span class="nav-icon">⚑</span>Notifications</a>
                <a href="#support"><span class="nav-icon">⚙</span>Support</a>
                <a href="#profile"><span class="nav-icon">👤</span>Profile</a>
                <a href="about.php"><span class="nav-icon">ℹ</span>About</a>
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
                        <input type="search" placeholder="Search services, requests, or help" />
                        <button type="button" class="mic-button"><i class="fa-solid fa-microphone"></i></button>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($user['course'] ?? ($user['course_year'] ?? 'Course')) ?> · <?= htmlspecialchars($user['year_level'] ?? 'Year Level') ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></button>
                    <button type="button" class="topbar-icon" aria-label="About"><i class="fa-solid fa-question-circle"></i></button>
                </div>
            </header>
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Guidance · Counselor</span>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel">
                    <!-- Welcome Header -->
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow">Guidance Management System</p>
                            <h1>Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</h1>
                            <p class="dashboard-subtitle">Monitor guidance cases, manage counseling sessions, and track student support from your command center.</p>
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
                        </div>
                    </section>
                    <?php if ($tabContent !== ''): ?>
                        <?= $tabContent ?>
                    <?php else: ?>
                    <?php if ($message !== ''): ?>
                        <div class="alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                    <div class="card-grid">
                        <div class="small-card">
                            <h3>New Cases</h3>
                            <p><?= count($newCases) ?></p>
                        </div>
                        <div class="small-card">
                            <h3>Active Cases</h3>
                            <p><?= count($activeCases) ?></p>
                        </div>
                        <div class="small-card">
                            <h3>Closed Cases</h3>
                            <p><?= count($closedCases) ?></p>
                        </div>
                    </div>
                    <section id="appointments" class="service-panel">
                        <h2>New Cases Queue</h2>
                        <?php if (empty($newCases)): ?>
                            <p>No new guidance cases at the moment.</p>
                        <?php else: ?>
                            <div class="cases-table-wrapper">
                                <table class="cases-table">
                                    <thead>
                                        <tr>
                                            <th>Case No.</th>
                                            <th>Student</th>
                                            <th>Reporter</th>
                                            <th>Concern</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($newCases as $case): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($case['case_number']) ?></td>
                                                <td><?= htmlspecialchars($case['reported_student_name']) ?></td>
                                                <td><?= htmlspecialchars($case['reporter_name']) ?> (<?= htmlspecialchars($case['reporter_role']) ?>)</td>
                                                <td><?= htmlspecialchars($case['type_of_concern']) ?></td>
                                                <td><?= htmlspecialchars(str_replace('_', ' ', ucfirst($case['status']))) ?></td>
                                                <td>
                                                    <form method="post" style="display:inline-block; margin-right:6px;">
                                                        <input type="hidden" name="action" value="accept_case">
                                                        <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                        <button type="submit" class="button button-secondary">Accept</button>
                                                    </form>
                                                    <form method="post" style="display:inline-block;">
                                                        <input type="hidden" name="action" value="return_case">
                                                        <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                        <button type="submit" class="button button-secondary">Return</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                    <section id="records" class="service-panel">
                        <h2>Active and Pending Cases</h2>
                        <?php if (empty($activeCases)): ?>
                            <p>There are no active guidance cases currently.</p>
                        <?php else: ?>
                            <div class="cases-table-wrapper">
                                <table class="cases-table">
                                    <thead>
                                        <tr>
                                            <th>Case No.</th>
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
                                                <td><?= htmlspecialchars($case['case_number']) ?></td>
                                                <td><?= htmlspecialchars($case['reported_student_name']) ?></td>
                                                <td><?= htmlspecialchars(str_replace('_', ' ', ucfirst($case['status']))) ?></td>
                                                <td><?= htmlspecialchars($case['type_of_concern']) ?></td>
                                                <td><?= htmlspecialchars(str_replace('_', ' ', ucfirst($case['external_resolution']))) ?></td>
                                                <td>
                                                    <form method="post" style="margin-bottom:8px;">
                                                        <input type="hidden" name="action" value="add_session">
                                                        <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                        <select name="attendance_status" required>
                                                            <option value="attended">Attended</option>
                                                            <option value="non_appearance">Non-Appearance</option>
                                                            <option value="pending">Pending</option>
                                                        </select>
                                                        <button type="submit" class="button button-secondary">Log Session</button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="update_resolution">
                                                        <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                        <button type="submit" class="button button-secondary">Update Resolution</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                    <section id="resolution" class="service-panel">
                        <h2>Resolution & Case Closure</h2>
                        <?php if (empty($activeCases)): ?>
                            <p>Select an active case from the queue above before updating resolutions.</p>
                        <?php else: ?>
                            <form method="post" class="form-inline">
                                <input type="hidden" name="action" value="update_resolution">
                                <label>
                                    Case
                                    <select name="case_id" required>
                                        <?php foreach ($activeCases as $case): ?>
                                            <option value="<?= (int)$case['id'] ?>"><?= htmlspecialchars($case['case_number'] . ' — ' . $case['reported_student_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    External Resolution
                                    <select name="external_resolution">
                                        <option value="none">No external resolution yet</option>
                                        <option value="counseling_completed_follow_up">Counseling completed; follow-up required</option>
                                        <option value="advised_behavioral_improvement">Advised behavioral improvement</option>
                                        <option value="under_monitoring">Under monitoring</option>
                                        <option value="case_resolved">Case resolved</option>
                                        <option value="scheduled_for_further_sessions">Scheduled for further sessions</option>
                                    </select>
                                </label>
                                <label>
                                    Internal Resolution
                                    <select name="internal_resolution">
                                        <option value="none">No internal resolution yet</option>
                                        <option value="with_reformation">With reformation</option>
                                        <option value="without_reformation">Without reformation</option>
                                        <option value="with_warning">With warning</option>
                                        <option value="under_monitoring">Under monitoring</option>
                                        <option value="escalated_case">Escalated case</option>
                                        <option value="ongoing_case">Ongoing case</option>
                                        <option value="closed_case">Closed case</option>
                                    </select>
                                </label>
                                <label>
                                    Internal Notes (confidential)
                                    <textarea name="internal_notes" rows="3" placeholder="Sensitive counselor-only notes"></textarea>
                                </label>
                                <button type="submit" class="button button-primary">Save Resolution</button>
                            </form>
                        <?php endif; ?>
                    </section>
                    <section id="good-moral" class="service-panel">
                        <h2>Good Moral Certificate Evaluation</h2>
                        <form method="post" class="form-inline" style="margin-bottom:16px;">
                            <input type="hidden" name="action" value="search_student">
                            <input type="text" name="student_query" placeholder="Enter student ID or full name" required>
                            <button type="submit" class="button button-primary">Analyze Student</button>
                        </form>
                        <?php if ($studentSearchStatus !== null): ?>
                            <?php if ($studentSearchStatus['status'] === 'not_found'): ?>
                                <p><?= htmlspecialchars($studentSearchStatus['reason']) ?></p>
                            <?php else: ?>
                                <div class="small-card" style="padding:18px;">
                                    <h3>Student</h3>
                                    <p><strong><?= htmlspecialchars($studentSearchResult['full_name']) ?></strong> (<?= htmlspecialchars($studentSearchResult['student_id'] ?? 'N/A') ?>)</p>
                                    <p><strong>Decision Support:</strong> <?= htmlspecialchars(str_replace('_', ' ', strtoupper($studentSearchStatus['status']))) ?></p>
                                    <p><?= htmlspecialchars($studentSearchStatus['reason']) ?></p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js" defer></script>
</body>
</html>
