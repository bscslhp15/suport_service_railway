<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

if ($user['role'] !== 'teacher' || $user['head_service'] !== 'guidance') {
    header('Location: guidance_home.php');
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
ensure_guidance_schema();

$caseId = (int)($_GET['case_id'] ?? 0);
$message = '';
$messageType = 'success';

$case = $caseId > 0 ? get_guidance_case_by_id($caseId) : null;
if (!$case) {
    header('Location: case_management.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'accept_case') {
        $sessionDate = $_POST['session_date'] ?? '';
        $sessionTime = $_POST['session_time'] ?? '';
        $sessionMode = $_POST['session_mode'] ?? '';
        $locationLink = trim($_POST['location_link'] ?? '');
        $participationScope = $_POST['participation_scope'] ?? '';
        $participationOther = trim($_POST['participation_other'] ?? '');
        $additionalAttendees = trim($_POST['additional_attendees'] ?? '');
        $sessionNotes = trim($_POST['session_notes'] ?? '');
        
        if (!$sessionDate || !$sessionTime || !$sessionMode) {
            $message = 'Session date, time, and mode are required.';
            $messageType = 'error';
        } elseif ($caseId > 0) {
            $pdo = get_db();
            $sessionDateTime = $sessionDate . ' ' . $sessionTime . ':00';
            
            // Check for double booking
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM guidance_sessions WHERE counselor_id = ? AND session_date = ? AND session_time = ?");
            $stmt->execute([$user['id'], $sessionDate, $sessionTime . ':00']);
            $existingSessions = $stmt->fetchColumn();
            
            if ($existingSessions > 0) {
                $message = 'You already have a session scheduled at this date and time. Please choose a different time.';
                $messageType = 'error';
            } else {
                $participationScopeStr = ($participationScope === 'Other') ? $participationOther : $participationScope;
                if ($additionalAttendees) {
                    $participationScopeStr .= ' (' . $additionalAttendees . ')';
                }
                
                $participantsFieldValue = guidance_participants_label($participationScopeStr);
                
                $notes = 'First session scheduled upon case acceptance.';
                if ($sessionNotes) $notes .= ' ' . $sessionNotes;
                $notes .= ' Mode: ' . $sessionMode . '. Location/Link: ' . $locationLink . '. Participants: ' . $participationScopeStr;
                
                if (add_guidance_session([
                    'case_id' => $caseId,
                    'counselor_id' => $user['id'],
                    'session_date' => $sessionDate,
                    'session_time' => $sessionTime . ':00',
                    'session_category' => 'under_guidance_counseling',
                    'participation_scope' => $participationScopeStr,
                    'attendance_status' => 'pending',
                    'notes' => $notes,
                ]) && update_guidance_case($caseId, ['status' => 'in_progress', 'counselor_id' => $user['id'], 'participants' => $participantsFieldValue])) {
                    header('Location: case_management.php?message=Case+accepted+and+session+scheduled');
                    exit;
                } else {
                    $message = 'Unable to accept case and schedule session.';
                    $messageType = 'error';
                }
            }
        }
    } elseif ($action === 'reject_case') {
        $rejectCategory = trim($_POST['reject_category'] ?? '');
        $customReasonText = trim($_POST['custom_reason_text'] ?? '');
        $rejectNote = trim($_POST['reject_note'] ?? '');
        
        $rejectReason = ($rejectCategory === 'custom') ? $customReasonText : $rejectCategory;
        $fullReason = $rejectReason . ($rejectNote ? ' | Additional note: ' . $rejectNote : '');
        
        if ($caseId > 0 && update_guidance_case($caseId, [
            'status' => 'escalated',
            'internal_notes' => $fullReason
        ])) {
            header('Location: case_management.php?message=Case+rejected+and+moved+to+Recent+Activity');
            exit;
        } else {
            $message = 'Unable to reject case.';
            $messageType = 'error';
        }
    }
    $case = get_guidance_case_by_id($caseId);
}

$attachments = get_guidance_case_attachments($caseId);
$attachmentBaseUrl = '../';

function is_image_file(string $path): bool {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
}

function is_embed_supported(string $path): bool {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['pdf', 'txt', 'html', 'htm'], true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Case | Guidance System</title>
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

        .review-grid { display: grid; gap: 18px; grid-template-columns: 1.3fr 0.7fr; margin-bottom: 24px; }
        .review-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; }
        .review-card h2 { margin-top: 0; font-size: 22px; }
        .case-meta { display: grid; gap: 12px; margin-top: 16px; }
        .case-meta-item { display: grid; grid-template-columns: 120px 1fr; gap: 8px; }
        .case-meta-item strong { color: #334155; }
        .case-description { white-space: pre-wrap; line-height: 1.6; color: #475569; margin-top: 14px; }
        .attachment-preview img { width: 100%; height: auto; border-radius: 10px; margin-bottom: 16px; cursor: pointer; }
        .attachment-preview iframe { width: 100%; min-height: 640px; border: 1px solid #d1d5db; border-radius: 10px; margin-bottom: 16px; }
        .attachment-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px; background: #f8fafc; }
        .attachment-card a { text-decoration: none; color: #1d4ed8; font-weight: 600; }
        .button-stack { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        .button { padding: 10px 16px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-danger { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .btn-danger:hover { background: #f5c2c7; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        .lightbox-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
        .lightbox-modal.open { display: flex; }
        .lightbox-content { max-width: 90%; max-height: 90%; overflow: hidden; border-radius: 12px; background: #000; position: relative; }
        .lightbox-content img { width: 100%; height: auto; display: block; }
        .lightbox-close { position: absolute; top: 12px; right: 12px; background: rgba(255,255,255,0.85); border: none; border-radius: 50%; width: 34px; height: 34px; font-size: 18px; cursor: pointer; }
        .form-group label { font-weight: 600; margin-bottom: 6px; display: block; }
        .form-group textarea { min-height: 120px; }
        .status-pill { display: inline-block; padding: 6px 12px; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-weight: 600; font-size: 13px; }
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
                <button type="button" class="nav-search-btn" aria-label="Search" data-tooltip="Search">
                    <i class="fa-solid fa-search"></i>
                </button>
            </div>
            <div class="nav-section">
                <a href="guidance_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
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
                        <a href="clinic_dashboard.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc_head_home.php" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="scholarship_dashboard.php" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="ssaa_student_home.php" data-tooltip="Alumni">
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
                        <a href="case_management.php" class="active" data-tooltip="Case Management">
                            <span class="nav-icon"><i class="fa-solid fa-file-circle-plus"></i></span>
                            <span class="nav-text">Case Management</span>
                        </a>
                        <a href="good_moral.php" data-tooltip="Good Moral">
                            <span class="nav-icon"><i class="fa-solid fa-certificate"></i></span>
                            <span class="nav-text">Good Moral</span>
                        </a>
                        <a href="reports.php" data-tooltip="Reports">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span class="nav-text">Reports</span>
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
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open mobile menu"><i class="fa-solid fa-bars"></i></button>
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
            <div class="main-scroll">
                <div class="page-overlay" id="pageOverlay"></div>
                <div class="content-panel">
                    <section class="dashboard-intro">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
                            <div>
                                <h1 style="color: #000;">Review Case</h1>
                                <p class="dashboard-subtitle" style="color: #000;">Inspect the student case details, review attachments, and decide whether to accept or reject.</p>
                            </div>
                            <a href="case_management.php" class="button btn-secondary" style="padding: 10px 16px; text-decoration: none; white-space: nowrap;">Back to Case Management</a>
                        </div>
                    </section>

                    <?php if ($message !== ''): ?>
                        <div class="alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="review-grid">
                        <div class="review-card">
                            <h2>Case Information</h2>
                            <div class="case-meta">
                                <div class="case-meta-item"><strong>Case Number:</strong><span><?= htmlspecialchars($case['case_number']) ?></span></div>
                                <div class="case-meta-item"><strong>Student:</strong><span><?= htmlspecialchars($case['reported_student_name'] ?? 'Unknown') ?></span></div>
                                <div class="case-meta-item"><strong>Reporter:</strong><span><?= htmlspecialchars($case['reporter_name'] ?? 'Unknown') ?> (<?= htmlspecialchars(ucfirst($case['reporter_role'])) ?>)</span></div>
                                <div class="case-meta-item"><strong>Concern:</strong><span><?= htmlspecialchars($case['type_of_concern']) ?></span></div>
                                <div class="case-meta-item"><strong>Course / Year:</strong><span><?= htmlspecialchars($case['course_year'] ?? 'N/A') ?></span></div>
                                <div class="case-meta-item"><strong>Status:</strong><span class="status-pill"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($case['status']))) ?></span></div>
                                <div class="case-meta-item"><strong>Submitted:</strong><span><?= date('M d, Y h:i A', strtotime($case['created_at'])) ?></span></div>
                            </div>

                            <h3 style="margin-top: 24px;">Description / Reason</h3>
                            <div class="case-description"><?= nl2br(htmlspecialchars($case['incident_description'])) ?></div>
                        </div>

                        <div class="review-card">
                            <h2>Actions</h2>
                            <div class="button-stack">
                                <button type="button" class="button btn-primary" style="width: 100%; flex: 1;" onclick="openModal('accept_modal')">Accept Case</button>
                                <button type="button" class="button btn-danger" style="width: 100%; flex: 1;" onclick="openModal('reject_modal')">Reject Case</button>
                            </div>

                            <!-- Accept Case Modal -->
                            <div class="modal" id="accept_modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                                <div style="background: white; padding: 24px; border-radius: 8px; max-width: 500px; width: 90%;">
                                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Accept Case & Schedule First Session</div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="accept_case">
                                        <div class="form-group">
                                            <label for="session_date">Date and Time:</label>
                                            <div style="display: flex; gap: 8px;">
                                                <input type="date" name="session_date" id="session_date" required style="flex: 1;">
                                                <input type="time" name="session_time" id="session_time" required style="flex: 1;">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="session_mode">Session Mode:</label>
                                            <select name="session_mode" id="session_mode" required onchange="toggleLocationLabel('case_review')">
                                                <option value="">-- Select mode --</option>
                                                <option value="Face-to-Face">Face-to-Face</option>
                                                <option value="Online">Online</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="location_link" id="location_label_case_review">Location/Link:</label>
                                            <textarea name="location_link" id="location_link" placeholder="Specify details..." required></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="participation_scope">Participation Scope:</label>
                                            <select name="participation_scope" id="participation_scope" required onchange="toggleParticipationOther('case_review')">
                                                <option value="">-- Select participation --</option>
                                                <option value="Student only">Student only</option>
                                                <option value="Student and Parent/Guardian">Student and Parent/Guardian</option>
                                                <option value="Student and Teacher">Student and Teacher</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <div id="other_participation_case_review" style="display: none; margin-top: 8px;">
                                                <textarea name="participation_other" id="participation_other_case_review" placeholder="Specify custom participation scope..." style="margin-bottom: 8px;"></textarea>
                                                <textarea name="additional_attendees" id="additional_attendees" placeholder="Specify additional attendees if needed..."></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="session_notes">Notes / Description:</label>
                                            <textarea name="session_notes" id="session_notes" placeholder="Add any notes or comments about the case..."></textarea>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="submit" class="button btn-primary">Accept & Schedule</button>
                                            <button type="button" class="button btn-secondary" onclick="closeModal('accept_modal')">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Reject Case Modal -->
                            <div class="modal" id="reject_modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                                <div style="background: white; padding: 24px; border-radius: 8px; max-width: 500px; width: 90%;">
                                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Reject Case</div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="reject_case">
                                        <div class="form-group">
                                            <label for="reject_category">Rejection Reason:</label>
                                            <select name="reject_category" id="reject_category" required onchange="toggleCustomReason('case_review')">
                                                <option value="">-- Select a reason --</option>
                                                <option value="Insufficient Information">Insufficient Information</option>
                                                <option value="Outside Guidance Scope">Outside Guidance Scope</option>
                                                <option value="Duplicate Request">Duplicate Request</option>
                                                <option value="Non-Counseling Needs">Non-Counseling Needs</option>
                                                <option value="Requires Clarification">Requires Clarification</option>
                                                <option value="Invalid/Prank Report">Invalid/Prank Report</option>
                                                <option value="custom">Other (Please specify)</option>
                                            </select>
                                        </div>
                                        <div class="form-group" id="custom_reason_case_review" style="display: none;">
                                            <label for="custom_reason_text">Please specify your reason:</label>
                                            <textarea name="custom_reason_text" id="custom_reason_text" placeholder="Enter your custom rejection reason..."></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="reject_note">Additional Notes (Optional):</label>
                                            <textarea name="reject_note" id="reject_note" placeholder="Add any additional comments..."></textarea>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="submit" class="button btn-danger">Reject Case</button>
                                            <button type="button" class="button btn-secondary" onclick="closeModal('reject_modal')">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <h3 style="margin-top: 24px;">Attachments</h3>
                            <?php if (empty($attachments)): ?>
                                <p style="color: #6b7280;">No documents or files were attached to this case.</p>
                            <?php else: ?>
                                <?php foreach ($attachments as $attachment): ?>
                                    <?php $fileUrl = $attachmentBaseUrl . ltrim($attachment['filepath'], '/'); ?>
                                    <div class="attachment-card">
                                        <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center;">
                                            <div><strong><?= htmlspecialchars($attachment['filename']) ?></strong></div>
                                            <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank">Open full file</a>
                                        </div>
                                        <div class="attachment-preview" style="margin-top: 12px;">
                                            <?php if (is_image_file($attachment['filepath'])): ?>
                                                <img src="<?= htmlspecialchars($fileUrl) ?>" alt="Attachment image" onclick="openLightbox('<?= htmlspecialchars($fileUrl) ?>')">
                                            <?php elseif (is_embed_supported($attachment['filepath'])): ?>
                                                <iframe src="<?= htmlspecialchars($fileUrl) ?>"></iframe>
                                            <?php else: ?>
                                                <p style="color: #475569; margin-top: 12px;">This file type cannot be previewed directly. Use the link above to open it in a new tab.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="margin-top: 16px;">
                        <a href="case_management.php" class="button btn-secondary" style="text-decoration: none;">Back to Case Management</a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="lightbox-modal" id="lightboxModal" onclick="closeLightbox(event)">
        <div class="lightbox-content">
            <button type="button" class="lightbox-close" onclick="closeLightbox(event)">&times;</button>
            <img id="lightboxImage" src="" alt="Enlarged attachment">
        </div>
    </div>

    <script>
        function openLightbox(imageUrl) {
            const modal = document.getElementById('lightboxModal');
            const image = document.getElementById('lightboxImage');
            image.src = imageUrl;
            modal.classList.add('open');
        }

        function closeLightbox(event) {
            if (event.target.id === 'lightboxModal' || event.target.classList.contains('lightbox-close')) {
                const modal = document.getElementById('lightboxModal');
                const image = document.getElementById('lightboxImage');
                image.src = '';
                modal.classList.remove('open');
                event.preventDefault();
            }
        }

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.style.display = 'none';
        }

        function toggleCustomReason(context) {
            const rejectCategory = document.getElementById('reject_category');
            const customReasonField = document.getElementById('custom_reason_' + context);
            const customTextarea = document.getElementById('custom_reason_text');
            
            if (rejectCategory.value === 'custom') {
                customReasonField.style.display = 'block';
                if (customTextarea) customTextarea.required = true;
            } else {
                customReasonField.style.display = 'none';
                if (customTextarea) customTextarea.required = false;
            }
        }
        
        function toggleLocationLabel(context) {
            const mode = document.getElementById('session_mode').value;
            const label = document.getElementById('location_label_' + context);
            const textarea = document.getElementById('location_link');
            if (mode === 'Face-to-Face') {
                label.textContent = 'Specify Room/Venue';
                textarea.placeholder = 'e.g., Guidance Office Room 101';
            } else if (mode === 'Online') {
                label.textContent = 'Provide Meeting Link/Platform';
                textarea.placeholder = 'e.g., Google Meet link or Zoom room';
            } else {
                label.textContent = 'Location/Link';
                textarea.placeholder = 'Specify details...';
            }
        }
        
        function toggleParticipationOther(context) {
            const scope = document.getElementById('participation_scope').value;
            const otherDiv = document.getElementById('other_participation_' + context);
            if (scope === 'Other') {
                otherDiv.style.display = 'block';
            } else {
                otherDiv.style.display = 'none';
            }
        }
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
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
    document.addEventListener('DOMContentLoaded', function(){
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const pageOverlay = document.getElementById('pageOverlay');
        const sideNav = document.querySelector('.side-nav');
        if (!mobileToggle || !pageOverlay || !sideNav) return;
        function openMobileNav(){ sideNav.classList.add('mobile-open'); pageOverlay.classList.add('active'); }
        function closeMobileNav(){ sideNav.classList.remove('mobile-open'); pageOverlay.classList.remove('active'); }
        mobileToggle.addEventListener('click', function(){ sideNav.classList.contains('mobile-open') ? closeMobileNav() : openMobileNav(); });
        pageOverlay.addEventListener('click', closeMobileNav);
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMobileNav(); });
    });
    </script>
    <script src="../assets/js/app.js" defer></script>
</body>
</html>
