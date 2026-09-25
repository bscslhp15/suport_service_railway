<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

if (!in_array($user['role'], ['student', 'teacher'], true)) {
    header('Location: ../auth/student_login.php');
    exit;
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
$servicesOpen = in_array($currentPage, ['guidance_dashboard.php', 'library_dashboard.php', 'clinic_dashboard.php', 'ssc/ssc_dashboard.php', 'scholarship/scholarship_dashboard.php', 'ssaa_student_home.php'], true);

$service = strtolower(trim($_GET['service'] ?? ''));
// Treat teacher head for SSC/Scholarship as combined mode when no explicit service provided
$isHeadSscScholarship = $user['role'] === 'teacher' && (($user['head_service'] ?? '') === 'ssc_scholarship');
if ($service === '' && $isHeadSscScholarship) { $service = 'ssc/scholarship'; }
$isGuidanceService = $service === 'guidance';
$isLibraryService = $service === 'library';
$isClinicService = $service === 'clinic';
// Combined SSC/Scholarship support
$isSscScholarshipService = $service === 'ssc/scholarship' || ($service === 'ssc' && $isHeadSscScholarship);
$sscScholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'ssc';
$scholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'scholarship';


require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
ensure_guidance_schema();
$pdo = get_db();
$formatParticipants = static function (?string $scope): string {
    $scope = trim((string)$scope);
    if ($scope === '') {
        return 'STUDENT ONLY';
    }
    if (stripos($scope, 'teacher') !== false) {
        return 'TEACHER AND STUDENT';
    }
    if (stripos($scope, 'parent') !== false) {
        return 'Student and Parent/Guardian';
    }
    return stripos($scope, 'student') !== false ? 'STUDENT ONLY' : $scope;
};

$announcements = [];
if (function_exists('get_active_clinic_announcements')) {
    $announcements = get_active_clinic_announcements($user['role'] === 'teacher' ? 'teachers' : 'students');
}

$message = '';
$messageType = 'success';
$userRole = $user['role'];
$studentNotificationMessage = '';
if ($userRole === 'student') {
    $stmt = $pdo->prepare("SELECT notification_status FROM good_moral_records WHERE student_id = ? AND notification_status IN ('approved', 'hold') AND notification_read_at IS NULL LIMIT 1");
    $stmt->execute([$user['id']]);
    $notice = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($notice) {
        if ($notice['notification_status'] === 'approved') {
            $studentNotificationMessage = 'Your Good Moral request has been processed. Check your email or spam folder for more information.';
            $messageType = 'success';
        } elseif ($notice['notification_status'] === 'hold') {
            $studentNotificationMessage = 'Your Good Moral request is on HOLD. Check your email or spam folder for more details.';
            $messageType = 'error';
        }

        $stmt = $pdo->prepare("UPDATE good_moral_records SET notification_read_at = NOW() WHERE student_id = ? AND notification_status = ? AND notification_read_at IS NULL");
        $stmt->execute([$user['id'], $notice['notification_status']]);
    }
}

// Handle redirect message
if (isset($_GET['message'])) {
    $message = htmlspecialchars(urldecode($_GET['message']));
    $messageType = 'success';
}

// ========== HANDLE FORM SUBMISSIONS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // STUDENT: Request Good Moral
    if ($userRole === 'student' && isset($_POST['request_good_moral'])) {
        // Check if student has active cases or bad records
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as flagged FROM guidance_cases
            WHERE reported_student_id = ? AND status NOT IN ('closed')
            AND internal_resolution IN ('escalated_case', 'under_monitoring')
        ");
        $stmt->execute([$user['id']]);
        $flagged = (int)$stmt->fetchColumn();

        $goodMoralStatus = $flagged > 0 ? 'hold' : 'pending';

        // Create good moral record
        $stmt = $pdo->prepare("
            INSERT INTO good_moral_records (student_id, status, created_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = ?, updated_at = NOW()
        ");
        $stmt->execute([$user['id'], $goodMoralStatus, $goodMoralStatus]);

        if ($goodMoralStatus === 'hold') {
            $message = '⚠️ Your Good Moral request is under review. The guidance counselor detected an active case or record that requires evaluation. You will be notified once it\'s processed.';
            $messageType = 'warning';
        } else {
            $message = '✅ Good Moral request submitted! Please proceed to the Registrar to pay the processing fee. Your request will be evaluated by the guidance counselor.';
            $messageType = 'success';
        }
    }

    // STUDENT: Submit counseling request
    elseif ($userRole === 'student' && isset($_POST['submit_request'])) {
        $typeOfConcern = trim($_POST['type_of_concern'] ?? '');
        $incidentDescription = trim($_POST['incident_description'] ?? '');
        $hasAttachment = !empty($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK;

        if ($typeOfConcern === '' || $incidentDescription === '' || !$hasAttachment) {
            $message = 'Please complete all required fields and attach your letter.';
            $messageType = 'error';
        } else {
            $caseData = [
                'case_number' => generate_guidance_case_number(),
                'reported_student_id' => $user['id'],
                'reporter_user_id' => $user['id'],
                'reporter_role' => 'student',
                'report_type' => 'self_request',
                'type_of_concern' => $typeOfConcern,
                'course_year' => $user['course_year'] ?? '',
                'incident_description' => $incidentDescription,
                'parent_guardian_notified' => 0,
                'teacher_awareness_required' => 0,
            ];

            $caseId = create_guidance_case($caseData);
            if ($caseId) {
                $uploadDir = __DIR__ . '/../uploads/guidance';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = uniqid('letter_') . '_' . basename($_FILES['document']['name']);
                $filePath = $uploadDir . '/' . $fileName;
                if (move_uploaded_file($_FILES['document']['tmp_name'], $filePath)) {
                    add_guidance_case_attachment($caseId, $user['id'], 'uploads/guidance/' . $fileName, $_FILES['document']['name'], $_FILES['document']['type']);
                    header('Location: guidance_dashboard.php?message=Case+submitted+successfully');
                    exit;
                } else {
                    $message = 'Unable to submit your request. Please try again.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Unable to submit your request. Please try again.';
                $messageType = 'error';
            }
        }
    }

    // TEACHER: Submit incident report about student
    elseif ($userRole === 'teacher' && isset($_POST['submit_report'])) {
        $studentId = trim($_POST['student_id'] ?? '');
        $typeOfConcern = trim($_POST['type_of_concern'] ?? '');
        $incidentDescription = trim($_POST['incident_description'] ?? '');

        if ($studentId === '' || $typeOfConcern === '' || $incidentDescription === '') {
            $message = 'Please complete all required fields.';
            $messageType = 'error';
        } else {
            $reportedStudent = find_user_by_student_id($studentId);
            if (!$reportedStudent || $reportedStudent['role'] !== 'student') {
                $message = 'Invalid student ID. Please enter a valid student ID.';
                $messageType = 'error';
            } else {
                $caseData = [
                    'case_number' => generate_guidance_case_number(),
                    'reported_student_id' => (int)$reportedStudent['id'],
                    'reporter_user_id' => $user['id'],
                    'reporter_role' => 'teacher',
                    'report_type' => 'teacher_report',
                    'type_of_concern' => $typeOfConcern,
                    'course_year' => $reportedStudent['course_year'] ?? '',
                    'incident_description' => $incidentDescription,
                    'parent_guardian_notified' => isset($_POST['parent_notified']) ? 1 : 0,
                    'teacher_awareness_required' => 1,
                ];

                $caseId = create_guidance_case($caseData);
                if ($caseId) {
                    if (!empty($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/../uploads/guidance';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $fileName = uniqid('evidence_') . '_' . basename($_FILES['evidence']['name']);
                        $filePath = $uploadDir . '/' . $fileName;
                        if (move_uploaded_file($_FILES['evidence']['tmp_name'], $filePath)) {
                            add_guidance_case_attachment($caseId, $user['id'], 'uploads/guidance/' . $fileName, $_FILES['evidence']['name'], $_FILES['evidence']['type']);
                        }
                    }
                    header('Location: guidance_dashboard.php?message=Report+submitted+successfully');
                    exit;
                } else {
                    $message = 'Unable to submit the report. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    }

    // TEACHER: Add observation feedback
    elseif ($userRole === 'teacher' && isset($_POST['add_feedback'])) {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $observation = trim($_POST['observation'] ?? '');

        if ($caseId <= 0 || $observation === '') {
            $message = 'Please provide observation feedback.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare('INSERT INTO guidance_observations (case_id, observer_user_id, observer_role, observation) VALUES (?, ?, ?, ?)');
            if ($stmt->execute([$caseId, $user['id'], 'teacher', $observation])) {
                $message = 'Observation feedback has been recorded and sent to the counselor.';
                $messageType = 'success';
            } else {
                $message = 'Unable to save feedback.';
                $messageType = 'error';
            }
        }
    }

    // STUDENT/TEACHER: Confirm attendance to appointment
    elseif (isset($_POST['confirm_attendance'])) {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        
        if ($sessionId > 0) {
            $stmt = $pdo->prepare("UPDATE guidance_sessions SET attendance_status = 'attended' WHERE id = ?");
            if ($stmt->execute([$sessionId])) {
                $message = 'Your attendance has been confirmed. Thank you!';
                $messageType = 'success';
            } else {
                $message = 'Unable to confirm attendance. Please try again.';
                $messageType = 'error';
            }
        }
    }
}


// STUDENT/TEACHER: Get all upcoming appointments (pending sessions) - include today and future
$upcomingAppointments = [];
if (in_array($userRole, ['student', 'teacher'])) {
    $externalId = $user['student_id'] ?? $user['employee_id'] ?? null;
    $usesSeparateTime = table_has_column('guidance_sessions', 'session_time');

    // Different logic for students vs teachers
    if ($userRole === 'student') {
        // For students: Show appointments for ACTIVE cases only (open or in_progress)
        // Cases that are resolved/escalated/closed will not show appointments
        $stmt = $pdo->prepare("
            SELECT DISTINCT gs.id, gs.*, gc.case_number, gc.type_of_concern, gc.status as case_status, gc.participants,
                   gc.priority_level, gc.created_at as case_created_at, gc.updated_at as case_updated_at, 
                   u_reporter.full_name as reporter_name, gc.reporter_role
            FROM guidance_sessions gs
            JOIN guidance_cases gc ON gs.case_id = gc.id
            LEFT JOIN users u_reporter ON gc.reporter_user_id = u_reporter.id
            WHERE gs.attendance_status = 'pending'
            AND gc.status IN ('open', 'in_progress')
            AND gs.session_date >= CURDATE()
            AND gs.case_id IN (
                SELECT DISTINCT gc.id FROM guidance_cases gc
                WHERE gc.reporter_user_id = ?
                UNION
                SELECT DISTINCT crp.case_id FROM case_reported_persons crp
                WHERE crp.person_user_id = ?
            )
            ORDER BY gs.session_date ASC, COALESCE(gs.session_time, '23:59:59') ASC
        ");
        $stmt->execute([$user['id'], $user['id']]);
    } else {
        // For teachers: Show appointments for ACTIVE cases only (open or in_progress)
        // Cases that are resolved/escalated/closed will not show appointments
        $stmt = $pdo->prepare("
            SELECT DISTINCT gs.id, gs.*, gc.case_number, gc.type_of_concern, gc.status as case_status, gc.participants, 
                   gc.priority_level, gc.created_at as case_created_at, gc.updated_at as case_updated_at,
                   u_reporter.full_name as reporter_name, gc.reporter_role
            FROM guidance_sessions gs
            JOIN guidance_cases gc ON gs.case_id = gc.id
            LEFT JOIN users u_reporter ON gc.reporter_user_id = u_reporter.id
            WHERE gs.attendance_status = 'pending'
            AND gc.status IN ('open', 'in_progress')
            AND gs.session_date >= CURDATE()
            AND (
                gs.counselor_id = ?
                OR gs.case_id IN (
                    SELECT DISTINCT crp.case_id FROM case_reported_persons crp
                    WHERE crp.person_user_id = ?
                )
            )
            ORDER BY gs.session_date ASC, COALESCE(gs.session_time, '23:59:59') ASC
        ");
        $stmt->execute([$user['id'], $user['id']]);
    }

    $upcomingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// STUDENT: Get personal cases
$studentCases = [];
$studentAppointments = [];
if ($userRole === 'student') {
    $externalId = $user['student_id'] ?? null;
    $studentCases = get_guidance_cases_for_user($user['id'], $externalId);
    
    // Get session data for each case
    foreach ($studentCases as &$case) {
        $stmt = $pdo->prepare("
            SELECT gs.*, gc.participants, gc.type_of_concern, gc.status as case_status
            FROM guidance_sessions gs
            LEFT JOIN guidance_cases gc ON gs.case_id = gc.id
            WHERE gs.case_id = ? 
            ORDER BY gs.session_date ASC LIMIT 1
        ");
        $stmt->execute([$case['id']]);
        $case['session'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    unset($case); // Break reference
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM guidance_sessions WHERE case_id IN (SELECT id FROM guidance_cases WHERE reported_student_id = ?) AND attendance_status = 'pending'");
    $stmt->execute([$user['id']]);
    $pendingAppointments = (int)$stmt->fetchColumn();
}

// TEACHER: Get cases they reported + cases where they can observe
$teacherCases = [];
$casesByStudent = [];
if ($userRole === 'teacher') {
    $stmt = $pdo->prepare("
        SELECT * FROM guidance_cases 
        WHERE reporter_user_id = ? OR (teacher_awareness_required = 1 AND reported_student_id IN (SELECT id FROM users WHERE course_year LIKE ?))
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['id'], '%' . ($user['course_year'] ?? '') . '%']);
    $teacherCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentPage = basename($_SERVER['PHP_SELF']);
$servicesOpen = in_array($currentPage, ['guidance_home.php', 'guidance_dashboard.php'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
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

        .guidance-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 28px;
            min-height: 138px;
            margin-bottom: 24px;
            padding: 26px 30px;
            border-left: 5px solid #8f0011;
            border-radius: 18px;
            background: linear-gradient(110deg, #fffdfb, #fffaf4);
            border-top: 1px solid #eadfd8;
            border-right: 1px solid #eadfd8;
            border-bottom: 1px solid #eadfd8;
            box-shadow: 0 10px 24px rgba(109, 38, 20, 0.08);
        }

        .guidance-page-header-main {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
            flex: 0 1 430px;
        }

        .guidance-page-header-icon {
            display: grid;
            flex: 0 0 54px;
            place-items: center;
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: linear-gradient(135deg, #8f0011, #b43a2b);
            color: #ffffff;
            font-size: 23px;
        }

        .guidance-page-header-eyebrow {
            margin: 0 0 4px;
            color: #92756b;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        .guidance-page-header h1 {
            margin: 0;
            color: #8f0011;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 30px;
            line-height: 1.1;
        }

        .guidance-page-header-welcome {
            margin: 7px 0 0;
            color: #756d69;
            font-size: 13px;
        }

        .guidance-page-header-welcome strong { color: #8f0011; }

        .guidance-header-center {
            display: flex;
            align-items: center;
            gap: 11px;
            flex: 1 1 auto;
            min-width: 190px;
            padding: 11px 18px;
            border-left: 1px solid #eadfd8;
            border-right: 1px solid #eadfd8;
            color: #756d69;
        }

        .guidance-header-center i {
            color: #8f0011;
            font-size: 18px;
        }

        .guidance-header-center strong {
            display: block;
            margin-bottom: 3px;
            color: #8f0011;
            font-size: 12px;
        }

        .guidance-header-center span {
            display: block;
            font-size: 11px;
            line-height: 1.4;
        }

        .guidance-header-profile {
            flex: 0 1 430px;
            text-align: right;
        }

        .guidance-header-profile strong {
            color: #8f0011;
            font-size: 17px;
        }

        .guidance-header-profile div {
            margin-top: 6px;
            color: #756d69;
            font-size: 14px;
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .guidance-page-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 14px;
                min-height: 0;
                padding: 20px;
            }

            .guidance-page-header-main {
                width: 100%;
                flex: none;
            }

            .guidance-page-header-icon {
                flex-basis: 50px;
                width: 50px;
                height: 50px;
                font-size: 21px;
            }

            .guidance-header-center {
                width: 100%;
                flex: none;
                min-width: 0;
                box-sizing: border-box;
                padding: 12px 0;
                border-top: 1px solid #eadfd8;
                border-right: 0;
                border-bottom: 1px solid #eadfd8;
                border-left: 0;
            }

            .guidance-header-profile {
                width: 100%;
                flex: none;
                min-width: 0;
                text-align: left;
            }

            .guidance-page-header h1 {
                font-size: 26px;
            }
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guidance Services - <?= ucfirst($userRole) ?> Portal | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        .dashboard-section { margin-bottom: 32px; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .card h3 { margin-top: 0; color: #1f2937; }
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .metric-card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; background: #ffffff; }
        .metric-card h3 { margin: 0 0 8px 0; color: #6b7280; font-size: 14px; font-weight: 500; text-transform: uppercase; }
        .metric-card .value { font-size: 32px; font-weight: 700; color: #1f2937; margin: 8px 0; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #1f2937; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; min-height: 120px; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .button { padding: 10px 16px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border: 1px solid #dbeafe;
            border-radius: 6px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            cursor: pointer;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        .btn-link:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e40af;
        }
        .btn-link:focus-visible {
            outline: 3px solid rgba(59, 130, 246, 0.25);
            outline-offset: 2px;
        }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
        .alert-error { background: #fee2e2; border-left: 4px solid #ef4444; color: #7f1d1d; }
        .case-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .case-table th, .case-table td { padding: 12px 10px; border: 1px solid #e2e8f0; text-align: left; }
        .case-table th { background: #f8fafc; font-weight: 600; }
        .case-table tr:hover { background: #f9fafb; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }

        /* Mobile Card/Block Transformation for Tables */
        @media (max-width: 768px) {
            .case-table,
            .case-table thead,
            .case-table tbody,
            .case-table th,
            .case-table td,
            .case-table tr {
                display: block;
                width: 100%;
            }

            .case-table thead { display: none; }

            .case-table tbody tr {
                display: flex;
                flex-direction: column;
                margin-bottom: 16px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            }

            .case-table tbody tr:hover {
                background: #ffffff;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            }

            .case-table td {
                padding: 12px 16px;
                border: none;
                border-bottom: 1px solid #f3f4f6;
                position: relative;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .case-table td:last-child { border-bottom: none; }

            .case-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #6b7280;
                min-width: 120px;
                margin-right: 12px;
            }

            .case-table td:first-child::before { content: "Case ID"; }
            .case-table td:nth-child(2)::before { content: "Submitted"; }
            .case-table td:nth-child(3)::before { content: "Concern"; }
            .case-table td:nth-child(4)::before { content: "Status"; }
            .case-table td:nth-child(5)::before { content: "Decision"; }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .case-table { font-size: 13px; }
            .case-table th, .case-table td { padding: 10px 8px; }
        }
        .access-restricted { background: #fee2e2; border-left: 4px solid #dc2626; padding: 16px; border-radius: 6px; color: #7f1d1d; margin-bottom: 20px; }
        .resolution-section { background: #dbeafe; border-left: 4px solid #2563eb; padding: 16px; border-radius: 6px; margin-bottom: 16px; }
        .resolution-section h4 { margin-top: 0; color: #1e40af; }
        .privacy-lock { color: #10b981; font-weight: 600; }

        @keyframes spin-refresh {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        /* Swipe-to-Refresh for Mobile (320px to 768px) */
        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }
    </style>
</head>
<body>
    <!-- Swipe-to-Refresh Spinner (Mobile Only) -->
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;">
        <div style="text-align: center;">
            <div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div>
            <p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p>
        </div>
    </div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p><?= htmlspecialchars($topbarInfo['displayMeta']) ?></p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar" <?= $isGuidanceService ? 'onclick="toggleGuidanceSidebar()"' : 'onclick="toggleSidebar()"' ?>>
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="nav-section">
                <?php if ($isGuidanceService): ?>
                    <a href="guidance_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=guidance" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                <?php elseif ($isLibraryService): ?>
                    <a href="librarian_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=library" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                <?php elseif ($isClinicService): ?>
                    <a href="nurse_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=clinic" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($dashboardLink) ?>" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                        <a href="school_announcements.php" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                <?php endif; ?>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <?php if ($isGuidanceService): ?>
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
                        <?php elseif ($isLibraryService): ?>
                            <a href="guidance_dashboard.php?service=library" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=library" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=library" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php?service=library" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=library" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=library" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        <?php elseif ($isClinicService): ?>
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
                        <?php elseif ($isSscScholarshipService): ?>
                            <a href="guidance_dashboard.php?service=ssc/scholarship" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=ssc/scholarship" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=ssc/scholarship" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc_head_home.php?service=ssc/scholarship" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=ssc/scholarship" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=ssc/scholarship" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        <?php else: ?>
                            <a href="guidance_dashboard.php" class="active" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php" data-tooltip="Supreme Student Council">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">Supreme Student Council</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($isGuidanceService): ?>
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
                <?php elseif ($isLibraryService): ?>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="../library_checkin.php" data-tooltip="QR Check-In">
                                <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                                <span class="nav-text">QR Check-In</span>
                            </a>
                            <a href="../library_catalog.php" data-tooltip="Digital Catalog">
                                <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                                <span class="nav-text">Digital Catalog</span>
                            </a>
                            <a href="../library_inventory.php" data-tooltip="Inventory">
                                <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                                <span class="nav-text">Inventory</span>
                            </a>
                            <a href="../library_reports.php" data-tooltip="Reports">
                                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                                <span class="nav-text">Reports</span>
                            </a>
                            <a href="../library_settings.php" data-tooltip="Settings">
                                <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                                <span class="nav-text">Settings</span>
                            </a>
                            <a href="../library_qr.php" data-tooltip="Library QR">
                                <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                                <span class="nav-text">Library QR</span>
                            </a>
                        </div>
                    </div>
                    <?php elseif ($isClinicService): ?>
                        <div class="nav-group">
                            <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage">
                                <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                                <span class="nav-text">Manage</span>
                                <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                            </button>
                            <div class="submenu" aria-hidden="true">
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
                    <?php elseif ($isSscScholarshipService): ?>
                        <div class="nav-group">
                            <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage SSC">
                                <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                                <span class="nav-text">Manage SSC</span>
                                <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                            </button>
                            <div class="submenu" aria-hidden="true">
                                <a href="ssc_manage_events.php" data-tooltip="SSC Events">
                                    <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                                    <span class="nav-text">SSC Events</span>
                                </a>
                                <a href="ssc_manage_candidates.php" data-tooltip="Candidates">
                                    <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                                    <span class="nav-text">Candidates</span>
                                </a>
                                <a href="ssc_reports.php" data-tooltip="Reports & Analytics">
                                    <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                    <span class="nav-text">Reports & Analytics</span>
                                </a>
                            </div>
                        </div>
                        <div class="nav-group">
                            <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                                <span class="nav-text">Manage Scholarship</span>
                                <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                            </button>
                            <div class="submenu" aria-hidden="true">
                                <a href="scholarship_create_announcement.php" data-tooltip="Scholarship Announcements">
                                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                    <span class="nav-text">Scholarship Announcements</span>
                                </a>
                                <a href="scholarship_reports.php" data-tooltip="Reports & Analytics">
                                    <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                    <span class="nav-text">Reports & Analytics</span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <a href="<?= $isGuidanceService ? 'profile.php?service=guidance' : ($isLibraryService ? 'profile.php?service=library' : ($isClinicService ? 'profile.php?service=clinic' : 'profile.php')) ?>" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="<?= $isGuidanceService ? 'about.php?service=guidance' : ($isLibraryService ? 'about.php?service=library' : ($isClinicService ? 'about.php?service=clinic' : 'about.php')) ?>" data-tooltip="About">
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
                            <p>Guidance Services</p>
                        </div>
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

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Announcements menu">
                        <div class="menu-header">
                            <strong>Announcements</strong>
                            <span class="menu-note">Latest updates</span>
                        </div>
                        <?php if (!empty($announcements)): ?>
                            <div class="menu-items">
                                <?php foreach ($announcements as $announcement): ?>
                                    <div class="menu-item notification-item" role="menuitem">
                                        <p class="notification-title"><i class="fa-solid fa-bullhorn"></i> <?= htmlspecialchars($announcement['title']) ?></p>
                                        <p class="notification-meta"><?= htmlspecialchars(date('M j, Y', strtotime($announcement['created_at']))) ?> · Announcement</p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="menu-empty">No new announcements.</div>
                        <?php endif; ?>
                        <a class="menu-link menu-footer-link" href="school_announcements.php?service=guidance">View all announcements</a>
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
                    <div class="guidance-page-header">
                        <div class="guidance-page-header-main">
                            <div class="guidance-page-header-icon"><i class="fa-solid fa-user-graduate" aria-hidden="true"></i></div>
                            <div>
                                <p class="guidance-page-header-eyebrow">Campus Portal</p>
                                <h1>Guidance Services</h1>
                                <p class="guidance-page-header-welcome">Welcome back, <strong><?= htmlspecialchars($user['full_name']) ?></strong></p>
                            </div>
                        </div>
                        <div class="guidance-header-center">
                            <i class="fa-solid fa-shield-heart" aria-hidden="true"></i>
                            <div>
                                <strong>Confidential support</strong>
                                <span>Track your guidance cases and stay connected with your counselor.</span>
                            </div>
                        </div>
                        <div class="guidance-header-profile">
                            <strong><?= htmlspecialchars($roleLabel) ?></strong>
                            <div><?= htmlspecialchars($topbarInfo['displayMeta']) ?></div>
                        </div>
                    </div>

                    <?php if (!empty($studentNotificationMessage)): ?>
                        <div class="alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>" style="display: flex; justify-content: space-between; align-items: center;">
                            <span><?= htmlspecialchars($studentNotificationMessage) ?></span>
                            <?php if ($messageType === 'success' && strpos($studentNotificationMessage, 'Good Moral') !== false && strpos($studentNotificationMessage, 'processed') !== false): ?>
                                <button type="button" class="button btn-primary" style="padding: 8px 16px; background: #2563eb; border: none; color: white; border-radius: 4px; cursor: pointer; white-space: nowrap; margin-left: 16px;" onclick="showGoodMoralFeedbackModal()">Submit Feedback</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- ========== SHARED: APPOINTMENT ALERTS (shown to both students and teachers) ========== -->
                    <?php if (!empty($upcomingAppointments)): ?>
                        <?php $displayAppointment = $upcomingAppointments[0]; ?>
                        <div style="margin-bottom: 20px;">
                            <div class="card" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 3px solid #f59e0b; border-radius: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding: 16px 16px 0 16px;">
                                    <h2 style="color: #92400e; margin: 0; font-size: 18px; font-weight: bold;">GUIDANCE OFFICE APPOINTMENT</h2>
                                    <span style="background-color: #f59e0b; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">Pending Confirmation</span>
                                </div>

                                <hr style="border: none; border-top: 1px solid #fcd34d; margin: 16px 0;">

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; padding: 0 16px;">
                                    <?php
                                        $sessionDateTime = $displayAppointment['session_date'];
                                        if (!empty($displayAppointment['session_time'])) {
                                            $sessionDateTime .= ' ' . $displayAppointment['session_time'];
                                        }
                                    ?>
                                    <div>
                                        <strong style="color: #374151; font-size: 14px;">📅 Date/Time:</strong><br>
                                        <span style="color: #6b7280; font-size: 15px;"><?= date('M d, Y', strtotime($sessionDateTime)) ?> | <?= date('g:i A', strtotime($sessionDateTime)) ?></span>
                                    </div>

                                    <div>
                                        <?php
                                            $notes = $displayAppointment['notes'] ?? '';
                                            $modeLabel = 'Face-to-Face';
                                            if (preg_match('/Mode:\s*([^\.\n]+)/i', $notes, $modeMatch)) {
                                                $modeLabel = trim($modeMatch[1], '.');
                                            }
                                            $isOnline = stripos($modeLabel, 'online') !== false || stripos($modeLabel, 'virtual') !== false;
                                            $venueLabel = $isOnline ? 'Meeting Link/Platform' : 'Venue/Location';
                                            $venueValue = 'TO BE ANNOUNCED';
                                            if (preg_match('/(?:Venue\/Location|Meeting Link\/Platform|Location\/Link|Venue|Link):\s*(.*?)(?:\.\s*Participants:|$)/i', $notes, $linkMatch)) {
                                                $venueValue = trim($linkMatch[1], '.');
                                            }
                                        ?>
                                        <strong style="color: #374151; font-size: 14px;">📍 <?= $venueLabel ?>:</strong><br>
                                        <span style="color: #6b7280; font-size: 15px;"><?= htmlspecialchars($venueValue) ?></span>
                                    </div>

                                    <div>
                                        <strong style="color: #374151; font-size: 14px;">🔄 Session Mode:</strong><br>
                                        <span style="color: #6b7280; font-size: 15px;"><?= htmlspecialchars($modeLabel) ?></span>
                                    </div>

                                    <div>
                                        <strong style="color: #374151; font-size: 14px;">👥 Participants:</strong><br>
                                        <span style="color: #6b7280; font-size: 15px;"><?= htmlspecialchars($formatParticipants($displayAppointment['participants'] ?? ($displayAppointment['participation_scope'] ?? ''))) ?></span>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <?php if (count($upcomingAppointments) > 1): ?>
                            <div style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                                <span style="color: #4b5563; font-size: 14px;">Showing 1 of <?= count($upcomingAppointments) ?> upcoming guidance appointments.</span>
                                <button type="button" class="button btn-primary" style="padding: 10px 18px; background: #2563eb; border: none; color: #ffffff; border-radius: 8px; cursor: pointer;" onclick="openModal('allAppointmentsModal')">View All Appointments</button>
                            </div>

                            <div class="modal" id="allAppointmentsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.55); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
                                <div style="background: #ffffff; border-radius: 16px; width: min(100%, 960px); max-height: 90vh; overflow: hidden; box-shadow: 0 24px 48px rgba(0,0,0,0.2); display: flex; flex-direction: column;">
                                    <div style="padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb;">
                                        <div>
                                            <h2 style="margin: 0; font-size: 20px; color: #111827;">All Upcoming Guidance Appointments</h2>
                                            <p style="margin: 6px 0 0; color: #6b7280; font-size: 14px;">Review all pending guidance office appointments in one place.</p>
                                        </div>
                                        <button type="button" onclick="closeModal('allAppointmentsModal')" style="background: transparent; border: none; color: #374151; font-size: 22px; cursor: pointer; line-height: 1;">×</button>
                                    </div>
                                    <div style="padding: 18px 24px; overflow-y: auto;">
                                        <?php foreach ($upcomingAppointments as $appointment): ?>
                                            <?php
                                                $sessionDateTime = $appointment['session_date'];
                                                if (!empty($appointment['session_time'])) {
                                                    $sessionDateTime .= ' ' . $appointment['session_time'];
                                                }
                                                $notes = $appointment['notes'] ?? '';
                                                $modeLabel = 'Face-to-Face';
                                                if (preg_match('/Mode:\s*([^\.\n]+)/i', $notes, $modeMatch)) {
                                                    $modeLabel = trim($modeMatch[1], '.');
                                                }
                                                $isOnline = stripos($modeLabel, 'online') !== false || stripos($modeLabel, 'virtual') !== false;
                                                $venueLabel = $isOnline ? 'Meeting Link/Platform' : 'Venue/Location';
                                                $venueValue = 'TO BE ANNOUNCED';
                                                if (preg_match('/(?:Venue\/Location|Meeting Link\/Platform|Location\/Link|Venue|Link):\s*(.*?)(?:\.\s*Participants:|$)/i', $notes, $linkMatch)) {
                                                    $venueValue = trim($linkMatch[1], '.');
                                                }
                                            ?>
                                            <div style="margin-bottom: 18px; padding: 16px; border: 1px solid #e5e7eb; border-radius: 12px; background: #f8fafc;">
                                                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; align-items: flex-start; margin-bottom: 12px;">
                                                    <div style="flex: 1 1 220px;">
                                                        <div style="font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px;">Appointment</div>
                                                        <div style="font-size: 16px; font-weight: 700; color: #111827;"><?= htmlspecialchars($appointment['case_number'] ?? 'Case') ?></div>
                                                    </div>
                                                    <div style="flex: 1 1 220px; text-align: right; color: #374151; font-size: 14px;">
                                                        <?= date('M d, Y', strtotime($sessionDateTime)) ?> • <?= date('g:i A', strtotime($sessionDateTime)) ?>
                                                    </div>
                                                </div>
                                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                                                    <div>
                                                        <div style="font-size: 13px; color: #374151; font-weight: 600; margin-bottom: 4px;">Mode</div>
                                                        <div style="color: #4b5563; font-size: 14px;"><?= htmlspecialchars($modeLabel) ?></div>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 13px; color: #374151; font-weight: 600; margin-bottom: 4px;">Venue</div>
                                                        <div style="color: #4b5563; font-size: 14px;"><?= htmlspecialchars($venueValue) ?></div>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 13px; color: #374151; font-weight: 600; margin-bottom: 4px;">Participants</div>
                                                        <div style="color: #4b5563; font-size: 14px;"><?= htmlspecialchars($formatParticipants($appointment['participants'] ?? ($appointment['participation_scope'] ?? ''))) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="padding: 16px 24px; border-top: 1px solid #e5e7eb; text-align: right;">
                                        <button type="button" class="button btn-secondary" style="padding: 10px 18px; border-radius: 8px;" onclick="closeModal('allAppointmentsModal')">Close</button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- ========== STUDENT PORTAL ========== -->
                    <?php if ($userRole === 'student'): ?>
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow">Guidance Services</p>
                            <h1 style="color: #000000;">Request Counseling Support</h1>
                            <p class="dashboard-subtitle" style="color: #000000;">Submit confidential counseling requests, track your case status, and access guidance counselor decisions.</p>
                        </div>
                    </section>

                    <?php if ($message !== ''): ?>
                        <div class="alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Row 1: Stats Cards -->
                    <div class="card-grid" style="margin-bottom: 20px;">
                        <div class="metric-card">
                            <h3>Your Cases</h3>
                            <div class="value"><?= count($studentCases) ?></div>
                            <div style="font-size: 13px; color: #6b7280;">Total submissions</div>
                        </div>
                        <div class="metric-card">
                            <h3>Under Review</h3>
                            <div class="value"><?= count(array_filter($studentCases, fn($c) => in_array($c['status'], ['open', 'in_progress']))) ?></div>
                            <div style="font-size: 13px; color: #6b7280;">Active cases</div>
                        </div>
                    </div>

                    <!-- Row 2: Full Width Appointment Alert - NOW MOVED TO TOP (SHARED SECTION) -->

                    <!-- Submit Counseling Request / Request Good Moral - TABBED -->
                    <div class="card">
                        <div class="tabs-container">
                            <div class="tabs-header">
                                <button class="tab-button active" data-tab="tab-counseling">
                                    <i class="fa-solid fa-comments"></i> Submit Counseling Request
                                </button>
                                <button class="tab-button" data-tab="tab-good-moral">
                                    <i class="fa-solid fa-certificate"></i> Request Good Moral
                                </button>
                            </div>
                            <div class="tabs-swipe-hint">Swipe left/right to switch tabs on mobile.</div>

                            <!-- Tab 1: Counseling Request -->
                            <div id="tab-counseling" class="tab-content active">
                                <h3><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Submit Counseling Request</h3>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="submit_request" value="1">
                                    <div class="form-group">
                                        <label for="concern">Type of Concern:</label>
                                        <select name="type_of_concern" id="concern" required>
                                            <option value="">-- Select a concern --</option>
                                            <option value="Academic Performance">Academic Performance Issues</option>
                                            <option value="Peer Relationship">Peer Relationship/Social Conflict</option>
                                            <option value="Family Matters">Family Matters</option>
                                            <option value="Financial Concern">Financial Concern</option>
                                            <option value="Health Issue">Health/Wellness Issue</option>
                                            <option value="Stress Management">Stress Management</option>
                                            <option value="Career Planning">Career Planning & Guidance</option>
                                            <option value="Other Concern">Other Concern</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">Describe Your Situation (Required):</label>
                                        <textarea name="incident_description" id="description" placeholder="Please provide detailed information about your concern..." required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="document">📎 Attach Letter (Required):</label>
                                        <input type="file" name="document" id="document" accept=".pdf,.doc,.docx,.jpg,.png,.txt" required>
                                        <small style="color: #6b7280;">Max 5MB. Accepted: PDF, Word, images, text files</small>
                                    </div>
                                    <button type="submit" class="button btn-primary">Submit Counseling Request</button>
                                </form>
                            </div>

                            <!-- Tab 2: Good Moral Request -->
                            <div id="tab-good-moral" class="tab-content">
                                <h3><i class="fa-solid fa-scroll" aria-hidden="true"></i> Request Good Moral Certificate</h3>
                                <div style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 12px; margin-bottom: 20px; border-radius: 4px;">
                                    <p><strong><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Important Information:</strong></p>
                                    <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 14px;">
                                        <li>A guidance counselor will review your records for any active cases or disciplinary issues.</li>
                                        <li>Once approved, you must pay the processing fee at the Registrar's Office to obtain the certificate.</li>
                                        <li>You will receive an email notification with further instructions.</li>
                                    </ul>
                                </div>
                                <form method="post">
                                    <input type="hidden" name="request_good_moral" value="1">
                                    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin-bottom: 20px; border-radius: 4px;">
                                        <p style="margin: 0; font-size: 14px;"><strong><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Before requesting:</strong> Ensure you have no outstanding disciplinary cases or bad academic records. The guidance counselor will evaluate your eligibility.</p>
                                    </div>
                                    <button type="submit" class="button btn-primary">Request Good Moral Certificate</button>
                                </form>
                            </div>
                        </div>
                    </div>



                    <!-- Personal Case Tracker -->
                    <div class="card">
                        <h3><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> Your Cases - Personal Tracker</h3>
                        <?php $activeCases = array_filter($studentCases, fn($c) => in_array($c['status'], ['open', 'in_progress'])); ?>
                        <?php if (empty($activeCases)): ?>
                            <p style="color: #6b7280;">You haven't submitted any active counseling requests yet.</p>
                        <?php else: ?>
                            <table class="case-table">
                                <thead>
                                    <tr>
                                        <th>Case ID</th>
                                        <th>Submitted Date</th>
                                        <th>Concern</th>
                                        <th>Status</th>
                                        <th>Counselor Decision</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeCases as $case): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                            <td><?= date('M d, Y', strtotime($case['created_at'])) ?></td>
                                            <td><?= htmlspecialchars(substr($case['type_of_concern'], 0, 25)) ?></td>
                                            <td>
                                                <?php
                                                $statusText = 'Pending';
                                                $statusClass = 'badge-warning';
                                                if ($case['status'] === 'in_progress') {
                                                    $statusText = 'Accepted';
                                                    $statusClass = 'badge-success';
                                                } elseif ($case['status'] === 'escalated') {
                                                    $statusText = 'Rejected';
                                                    $statusClass = 'badge-danger';
                                                } elseif ($case['status'] === 'closed') {
                                                    $statusText = 'Resolved';
                                                    $statusClass = 'badge-info';
                                                }
                                                ?>
                                                <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                            </td>
                                            <td>
                                                <?php if ($case['status'] === 'in_progress'): ?>
                                                    <button type="button" class="btn-link" onclick="openModal('schedule_<?= $case['id'] ?>')">View Schedule</button>
                                                <?php elseif ($case['status'] === 'escalated'): ?>
                                                    <button type="button" class="btn-link" onclick="openModal('reason_<?= $case['id'] ?>')">View Reason</button>
                                                <?php else: ?>
                                                    <span style="color: #6b7280;">Awaiting decision</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Activity - Rejected/Escalated/Closed Cases -->
                    <div class="card">
                        <h3><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Recent Activity</h3>
                        <?php $recentActivity = array_filter($studentCases, fn($c) => in_array($c['status'], ['escalated', 'closed'])); ?>
                        <?php if (empty($recentActivity)): ?>
                            <p style="color: #6b7280;">No recent activity.</p>
                        <?php else: ?>
                            <table class="case-table">
                                <thead>
                                    <tr>
                                        <th>Case ID</th>
                                        <th>Date</th>
                                        <th>Concern</th>
                                        <th>Status</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivity as $case): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                            <td><?= date('M d, Y', strtotime($case['updated_at'] ?? $case['created_at'])) ?></td>
                                            <td><?= htmlspecialchars(substr($case['type_of_concern'], 0, 25)) ?></td>
                                            <td>
                                                <?php
                                                    if ($case['status'] === 'closed') {
                                                        echo '<span class="status-badge" style="background: #d1d5db; color: #374151;">Case Closed</span>';
                                                    } else {
                                                        echo '<span class="status-badge" style="background: #fed7aa; color: #92400e;">Escalated</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                <?php if ($case['status'] === 'closed'): ?>
                                                    <button type="button" class="btn-link" onclick="openModal('closure_<?= $case['id'] ?>')"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> View Resolution</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-link" onclick="openModal('reason_recent_<?= $case['id'] ?>')"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> View Reason</button>
                                                <?php endif; ?>
                                                <button type="button" class="btn-link" onclick="showFeedbackModal(<?= $case['id'] ?>, '<?= htmlspecialchars($case['case_number'], ENT_QUOTES) ?>')"><i class="fa-solid fa-comment" aria-hidden="true"></i> Add Feedback</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        
                        <!-- Feedback Modal -->
                        <div class="modal" id="guidance-feedback-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                            <div style="background: white; padding: 24px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
                                <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Submit Feedback</div>
                                <div id="feedback-modal-body">
                                    <!-- Content loaded dynamically -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Good Moral Feedback Modal -->
                        <div class="modal" id="good-moral-feedback-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                            <div style="background: white; padding: 24px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
                                <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Submit Feedback</div>
                                <div style="margin-bottom: 16px;">
                                    <div style="margin-bottom: 16px;">
                                        <strong>Good Moral Certificate Request</strong>
                                    </div>
                                    <div style="margin-bottom: 16px;">
                                        <label><strong>Rate the Service (1-5 stars):</strong></label>
                                        <div style="margin: 8px 0;">
                                            <div id="gm-service-rating" style="font-size: 24px;">
                                                <span class="rating-star" data-for="gm-service" data-rating="1" style="cursor: pointer; margin-right: 4px; color: #d1d5db;">★</span>
                                                <span class="rating-star" data-for="gm-service" data-rating="2" style="cursor: pointer; margin-right: 4px; color: #d1d5db;">★</span>
                                                <span class="rating-star" data-for="gm-service" data-rating="3" style="cursor: pointer; margin-right: 4px; color: #d1d5db;">★</span>
                                                <span class="rating-star" data-for="gm-service" data-rating="4" style="cursor: pointer; margin-right: 4px; color: #d1d5db;">★</span>
                                                <span class="rating-star" data-for="gm-service" data-rating="5" style="cursor: pointer; margin-right: 4px; color: #d1d5db;">★</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 16px;">
                                        <label for="gm-feedback-text"><strong>Your Feedback (Required):</strong></label>
                                        <textarea id="gm-feedback-text" style="width: 100%; height: 100px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: Arial; font-size: 14px;" placeholder="Share your feedback about the Good Moral Certificate service..." required></textarea>
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <button type="button" class="button btn-primary" onclick="submitGoodMoralFeedback()">Submit Feedback</button>
                                        <button type="button" class="button btn-secondary" onclick="document.getElementById('good-moral-feedback-modal').style.display = 'none'">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Schedule Modals -->
                        <?php foreach ($activeCases as $case): ?>
                            <?php if ($case['status'] === 'in_progress' && isset($case['session'])): ?>
                                <div class="modal" id="schedule_<?= $case['id'] ?>" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                                    <div style="background: white; padding: 24px; border-radius: 8px; max-width: 500px; width: 90%;">
                                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Session Schedule</div>
                                        <div style="margin-bottom: 16px;">
                                            <strong>Case:</strong> <?= htmlspecialchars($case['case_number']) ?><br>
                                            <?php
                                                $caseSessionDateTime = $case['session']['session_date'];
                                                if (!empty($case['session']['session_time'])) {
                                                    $caseSessionDateTime .= ' ' . $case['session']['session_time'];
                                                }
                                            ?>
                                            <strong>Date & Time:</strong> <?= date('M d, Y H:i', strtotime($caseSessionDateTime)) ?><br>
                                            <?php
                                            $notes = $case['session']['notes'] ?? '';
                                            // Parse the notes to extract session mode and location
                                            $mode = 'Face-to-Face';
                                            $location = 'TO BE ANNOUNCED'; 
                                            
                                            // Parse Session Mode from notes (format: "Mode: Face-to-Face" or "Mode: Online")
                                            if (preg_match('/Mode:\s*([^\.\n]+)/i', $notes, $matches)) {
                                                $mode = trim($matches[1], '.');
                                            }
                                            // Parse Venue or Link from notes (format: "Venue: Main Building,Guidance Office" or "Link: https://zoom...")
                                            if (preg_match('/(?:Venue\/Location|Meeting Link\/Platform|Location\/Link|Venue|Link):\s*(.*?)(?:\.\s*Participants:|$)/i', $notes, $matches)) {
                                                $location = trim($matches[1], '.');
                                            }
                                            $participants = $formatParticipants($case['session']['participants'] ?? ($case['session']['participation_scope'] ?? ''));
                                            $isOnlineFromNotes = stripos($mode, 'online') !== false || stripos($mode, 'virtual') !== false;
                                            ?>
                                        <strong>Session Mode:</strong> <?= htmlspecialchars($mode) ?><br>
                                            <strong><?= ($isOnlineFromNotes ? 'Meeting Link/Platform' : 'Venue/Location') ?>:</strong> <?= htmlspecialchars($location) ?><br>
                                            <strong>Participants:</strong> <?= htmlspecialchars($participants) ?><br>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="button btn-secondary" onclick="closeModal('schedule_<?= $case['id'] ?>')">Close</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($case['status'] === 'escalated'): ?>
                                <div class="modal" id="reason_<?= $case['id'] ?>" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                                    <div style="background: white; padding: 24px; border-radius: 8px; max-width: 500px; width: 90%;">
                                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Rejection Reason</div>
                                        <div style="margin-bottom: 16px;">
                                            <strong>Case:</strong> <?= htmlspecialchars($case['case_number']) ?><br>
                                            <strong>Reason:</strong><br>
                                            <em><?= htmlspecialchars($case['internal_notes'] ?: 'No specific reason provided.') ?></em>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="button btn-secondary" onclick="closeModal('reason_<?= $case['id'] ?>')">Close</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- Recent Activity Reason Modals -->
                        <?php foreach ($recentActivity as $case): ?>
                            <?php if ($case['status'] === 'closed'): ?>
                                <!-- Modal for Closed Case - Display Resolution -->
                                <div class="modal" id="closure_<?= $case['id'] ?>" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                                    <div style="background: white; padding: 24px; border-radius: 8px; max-width: 500px; width: 90%;">
                                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Case Closed</div>
                                        <div style="margin-bottom: 16px;">
                                            <strong>Case:</strong> <?= htmlspecialchars($case['case_number']) ?><br>
                                            <strong>Concern:</strong> <?= htmlspecialchars($case['type_of_concern']) ?><br>
                                            <strong>Status:</strong> <span class="status-badge" style="background: #d1d5db; color: #374151;">Case Closed</span><br>
                                            <strong>Closed Date:</strong> <?= date('M d, Y', strtotime($case['updated_at'] ?? $case['created_at'])) ?>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="button btn-secondary" onclick="closeModal('closure_<?= $case['id'] ?>')">Close</button>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Modal for Escalated Case - Display Rejection Reason -->
                                <?php
                                $reason = $case['internal_notes'] ?? '';
                                $additionalNotes = '';
                                
                                // Try to parse if it's in the new format
                                if (strpos($reason, ' | Additional note: ') !== false) {
                                    list($reason, $additionalNotes) = explode(' | Additional note: ', $reason, 2);
                                }
                                
                                $rejectionOptions = [
                                    'Insufficient Information',
                                    'Outside Guidance Scope', 
                                    'Duplicate Request',
                                    'Non-Counseling Needs',
                                    'Requires Clarification',
                                    'Invalid/Prank Report',
                                    'custom'
                                ];
                                
                                $selectedReason = 'custom'; // default
                                foreach ($rejectionOptions as $option) {
                                    if (strpos($reason, $option) !== false) {
                                        $selectedReason = $option;
                                        break;
                                    }
                                }
                                ?>
                                <div class="modal" id="reason_recent_<?= $case['id'] ?>" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                                    <div style="background: white; padding: 24px; border-radius: 8px; max-width: 500px; width: 90%;">
                                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Rejection Reason</div>
                                        <div style="margin-bottom: 16px;">
                                            <strong>Case:</strong> <?= htmlspecialchars($case['case_number']) ?><br>
                                            <div style="margin-top: 12px;">
                                                <strong>Rejection Reason:</strong><br>
                                                <select disabled style="width: 100%; padding: 8px; margin: 4px 0; border: 1px solid #d1d5db; border-radius: 4px;">
                                                    <option value="<?= htmlspecialchars($selectedReason) ?>" selected>
                                                        <?php if ($selectedReason === 'custom'): ?>
                                                            Other (Please specify)
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($selectedReason) ?>
                                                        <?php endif; ?>
                                                    </option>
                                                </select>
                                                <?php if ($selectedReason === 'custom'): ?>
                                                <div style="margin-top: 8px;">
                                                    <strong>Please specify your reason:</strong><br>
                                                    <div style="padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; margin-top: 4px;">
                                                        <?= htmlspecialchars($reason) ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($additionalNotes): ?>
                                                <div style="margin-top: 8px;">
                                                    <strong>Additional Notes:</strong><br>
                                                    <div style="padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; margin-top: 4px;">
                                                        <?= htmlspecialchars($additionalNotes) ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="button btn-secondary" onclick="closeModal('reason_recent_<?= $case['id'] ?>')">Close</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- ========== TEACHER PORTAL ========== -->
                    <?php else: ?>

                    <?php if ($message !== ''): ?>
                        <div class="alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="card-grid">
                        <div class="metric-card">
                            <h3>Reports Submitted</h3>
                            <div class="value"><?= count(array_filter($teacherCases, fn($c) => $c['reporter_user_id'] == $user['id'])) ?></div>
                            <div style="font-size: 13px; color: #6b7280;">Your incident reports</div>
                        </div>
                        <div class="metric-card">
                            <h3>Visible Cases</h3>
                            <div class="value"><?= count($teacherCases) ?></div>
                            <div style="font-size: 13px; color: #6b7280;">Cases with teacher awareness</div>
                        </div>
                    </div>

                    <!-- Submit Incident Report -->
                    <div class="card">
                        <h3>📝 Submit Incident Report</h3>
                        <p style="color: #6b7280; font-size: 14px; margin-bottom: 16px;">Document observed student behavior, academic concerns, or incidents that may benefit from guidance counselor intervention.</p>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="submit_report" value="1">
                            <div class="form-group">
                                <label for="student_id">Student ID (Required):</label>
                                <input type="text" name="student_id" id="student_id" placeholder="Enter the student's ID number" required>
                            </div>
                            <div class="form-group">
                                <label for="teacher_concern">Concern Category:</label>
                                <select name="type_of_concern" id="teacher_concern" required>
                                    <option value="">-- Select category --</option>
                                    <option value="Academic Performance">Poor Academic Performance</option>
                                    <option value="Class Behavior">Disruptive Classroom Behavior</option>
                                    <option value="Chronic Absenteeism">Chronic Absenteeism/Tardiness</option>
                                    <option value="Social Conflict">Social Conflict with Peers</option>
                                    <option value="Emotional Distress">Apparent Emotional Distress</option>
                                    <option value="Other Behavior">Other Behavioral Concern</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="teacher_description">Detailed Observation Report (Required):</label>
                                <textarea name="incident_description" id="teacher_description" placeholder="Describe specific incidents, observed behaviors, or concerns. Include dates and situations when possible." required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="evidence">📎 Attach Supporting Evidence (Optional):</label>
                                <input type="file" name="evidence" id="evidence" accept=".pdf,.doc,.docx,.jpg,.png,.txt">
                                <small style="color: #6b7280;">Supporting documents: lesson observation, anecdotal records, photos</small>
                            </div>
                            <button type="submit" class="button btn-primary">Submit Incident Report</button>
                        </form>
                    </div>

                    <!-- Submitted Reports -->
                    <div class="card">
                        <h3>📋 Submitted Reports</h3>
                        <?php if (empty($teacherCases)): ?>
                            <p style="color: #6b7280;">No cases at this time.</p>
                        <?php else: ?>
                            <table class="case-table">
                                <thead>
                                    <tr>
                                        <th>Case ID</th>
                                        <th>Student</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Decision</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacherCases as $case): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($case['reported_student_name']) ?></td>
                                            <td><?= htmlspecialchars(substr($case['type_of_concern'], 0, 20)) ?></td>
                                            <td><span class="status-badge badge-warning"><?= htmlspecialchars($case['status']) ?></span></td>
                                            <td>
                                                <?php if ($case['external_resolution'] && $case['external_resolution'] !== 'none'): ?>
                                                    <em style="font-size: 13px;"><?= htmlspecialchars(substr($case['external_resolution'], 0, 30)) ?></em>
                                                <?php else: ?>
                                                    <span style="color: #6b7280; font-size: 13px;">Pending counselor decision</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="button btn-secondary" style="font-size: 12px; padding: 6px 10px;" onclick="toggleFeedback(<?= (int)$case['id'] ?>)">Add Feedback</button>
                                            </td>
                                        </tr>
                                        <tr style="display: none;" id="feedback_form_<?= (int)$case['id'] ?>">
                                            <td colspan="6" style="background: #f3f4f6; padding: 20px;">
                                                <form method="post">
                                                    <input type="hidden" name="add_feedback" value="1">
                                                    <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                    <div class="form-group">
                                                        <label for="obs_<?= (int)$case['id'] ?>">Observation to Submit to Counselor:</label>
                                                        <textarea name="observation" id="obs_<?= (int)$case['id'] ?>" rows="3" placeholder="Additional classroom observations or behavioral updates to support the counselor's assessment." required></textarea>
                                                    </div>
                                                    <button type="submit" class="button btn-primary" style="font-size: 12px; padding: 8px 12px;">Submit Feedback</button>
                                                    <button type="button" class="button btn-secondary" style="font-size: 12px; padding: 8px 12px; margin-left: 8px;" onclick="toggleFeedback(<?= (int)$case['id'] ?>)">Cancel</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>



                    <?php endif; ?>
            </div>
        </main>
        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="footer-card">
                    <h3>About</h3>
                    <p>PASS College is dedicated to supporting student success through integrated academic, guidance, and health services.</p>
                </div>
                <div class="footer-card">
                    <h3>Address</h3>
                    <?php
                    $contacts = json_decode(get_library_setting('footer_contacts', '[]'), true) ?: [];
                    $locations = json_decode(get_library_setting('footer_locations', '[]'), true) ?: [];
                    $social = json_decode(get_library_setting('footer_social', '[]'), true) ?: [];
                    ?>
                    <?php if (!empty($locations)): ?>
                        <?php foreach ($locations as $loc):
                            $lname = htmlspecialchars($loc['name'] ?? $loc[0] ?? 'Location');
                            $laddr = htmlspecialchars($loc['address'] ?? $loc[2] ?? '');
                            $llink = htmlspecialchars($loc['link'] ?? $loc[1] ?? '#');
                        ?>
                            <p style="margin-bottom:8px;"><strong><?= $lname ?></strong><br>
                            <?= $laddr ? $laddr . '<br>' : '' ?>
                            <?php if (!empty($llink) && $llink !== '#'): ?>
                                <a href="<?= $llink ?>" target="_blank" rel="noopener noreferrer">View on map</a>
                            <?php endif; ?>
                            </p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No address / location set.</p>
                    <?php endif; ?>
                </div>
                <div class="footer-card">
                    <h3>Connect</h3>
                    <?php if (!empty($contacts)): ?>
                        <ul style="list-style:none;padding:0;margin:0 0 8px 0;">
                        <?php foreach ($contacts as $c):
                            $label = htmlspecialchars($c['label'] ?? $c[0] ?? '');
                            $value = trim($c['value'] ?? $c[1] ?? '');
                            $escaped = htmlspecialchars($value);
                            $href = '';
                            if (preg_match('#^https?://#i', $value)) {
                                $href = $escaped;
                            } elseif (strpos($value, '@') !== false) {
                                $href = 'mailto:' . $escaped;
                            } elseif (preg_match('/^[+0-9() \-]+$/', $value)) {
                                $href = 'tel:' . preg_replace('/[^0-9+]/', '', $value);
                            }
                        ?>
                            <li style="margin-bottom:6px;"><strong><?= $label ?>:</strong>
                                <?php if ($href): ?>
                                    <a href="<?= $href ?>" target="_blank" rel="noopener noreferrer"><?= $escaped ?></a>
                                <?php else: ?>
                                    <?= $escaped ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No contacts set.</p>
                    <?php endif; ?>

                    <?php if (!empty($social)): ?>
                        <p>
                        <?php
                            $iconMap = [
                                'facebook' => 'fab fa-facebook',
                                'facebook-messenger' => 'fab fa-facebook-messenger',
                                'tiktok' => 'fab fa-tiktok',
                                'x' => 'fab fa-x',
                                'youtube' => 'fab fa-youtube',
                                'instagram' => 'fab fa-instagram',
                                'threads' => 'fab fa-internet-explorer',
                                'whatsapp' => 'fab fa-whatsapp',
                                'telegram' => 'fab fa-telegram',
                                'discord' => 'fab fa-discord',
                                'reddit' => 'fab fa-reddit',
                                'pinterest' => 'fab fa-pinterest',
                                'quora' => 'fab fa-quora',
                                'others' => 'fas fa-link'
                            ];
                            foreach ($social as $i => $s) {
                                $platRaw = strtolower(trim((string)($s['platform'] ?? $s[0] ?? '')));
                                $label = htmlspecialchars($s['platform'] ?? $s[0] ?? 'Link');
                                $link = htmlspecialchars($s['link'] ?? $s[1] ?? '#');
                                $iconClass = $iconMap[$platRaw] ?? 'fas fa-link';
                        ?>
                            <a href="<?= $link ?>" target="_blank" rel="noopener noreferrer"><i class="<?= $iconClass ?>" style="margin-right:6px"></i><?= $label ?></a><?= $i < count($social)-1 ? ' · ' : '' ?>
                        <?php } ?>
                        </p>
                    <?php else: ?>
                        <p>No social links set.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-divider"></div>
            <div class="footer-copyright">© 2026 PASS College. All rights reserved.</div>
        </footer>
        <div class="page-overlay" id="pageOverlay"></div>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        function toggleFeedback(caseId) {
            const form = document.getElementById('feedback_form_' + caseId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'table-row';
            } else {
                form.style.display = 'none';
            }
        }

        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function updateLocationLabel(sessionId) {
            const modeSelect = document.getElementById('session_mode_' + sessionId);
            const locationLabel = document.getElementById('location_label_' + sessionId);
            const locationInput = document.getElementById('location_' + sessionId);
            const mode = modeSelect ? modeSelect.value : 'Face-to-Face';

            if (mode === 'Online') {
                if (locationLabel) locationLabel.textContent = 'Meeting Link/Platform:';
                if (locationInput) locationInput.placeholder = 'e.g., Zoom Link or Platform';
            } else {
                if (locationLabel) locationLabel.textContent = 'Venue/Location:';
                if (locationInput) locationInput.placeholder = 'e.g., Guidance Office Room 101';
            }
        }

        // Feedback Functions
        function showFeedbackModal(caseId, caseNumber) {
            const modal = document.getElementById('guidance-feedback-modal');
            const body = document.getElementById('feedback-modal-body');
            
            fetch('/THESIS/SUPPORTSERVICESYSTEM/includes/api_guidance_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get',
                    case_id: caseId,
                    student_id: '<?= $_SESSION['user_id'] ?? 0 ?>'
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    // Display existing feedback
                    body.innerHTML = `
                        <div style="margin-bottom: 16px;">
                            <strong>Case:</strong> ${htmlEscape(caseNumber)}<br>
                            <strong>Your Rating:</strong><br>
                            <div style="margin: 8px 0;">${createStarRating(data.data.service_rating)}</div>
                            <strong>Your Feedback:</strong><br>
                            <div style="padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; margin-top: 4px;">
                                ${htmlEscape(data.data.feedback_text || 'No additional feedback provided.')}
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="button btn-primary" onclick="document.getElementById('guidance-feedback-modal').style.display = 'none'">Close</button>
                        </div>
                    `;
                } else {
                    // Show feedback form
                    body.innerHTML = `
                        <div style="margin-bottom: 16px;">
                            <div style="margin-bottom: 16px;">
                                <strong>Case: ${htmlEscape(caseNumber)}</strong>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <label><strong>Rate the Service (1-5 stars):</strong></label>
                                <div style="margin: 8px 0;">
                                    <div id="service-rating" style="font-size: 24px;">
                                        ${[1,2,3,4,5].map(i => `<span class="rating-star" data-for="service" data-rating="${i}" style="cursor: pointer; margin-right: 4px; color: #d1d5db;">★</span>`).join('')}
                                    </div>
                                </div>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <label for="feedback-text"><strong>Your Feedback (Required):</strong></label>
                                <textarea id="feedback-text" style="width: 100%; height: 100px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: Arial; font-size: 14px;" placeholder="Share your feedback about the guidance service..." required></textarea>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="button btn-primary" onclick="submitGuidanceFeedback(${caseId})">Submit Feedback</button>
                                <button type="button" class="button btn-secondary" onclick="document.getElementById('guidance-feedback-modal').style.display = 'none'">Cancel</button>
                            </div>
                        </div>
                    `;
                    
                    // Add star rating interactivity
                    setTimeout(() => {
                        document.querySelectorAll('.rating-star[data-for="service"]').forEach(star => {
                            star.addEventListener('click', function() {
                                const rating = this.dataset.rating;
                                document.querySelectorAll('.rating-star[data-for="service"]').forEach((s, idx) => {
                                    s.style.color = (idx + 1) <= rating ? '#fbbf24' : '#d1d5db';
                                });
                                document.getElementById('service-rating').dataset.rating = rating;
                            });
                        });
                    }, 100);
                }
                modal.style.display = 'flex';
            });
        }

        function submitGuidanceFeedback(caseId) {
            const rating = parseInt(document.getElementById('service-rating').dataset.rating || 0);
            const feedback = document.getElementById('feedback-text').value.trim();
            
            if (rating < 1 || rating > 5) {
                alert('Please select a rating');
                return;
            }
            
            if (feedback === '') {
                alert('Please provide your feedback - it is required');
                return;
            }
            
            fetch('/THESIS/SUPPORTSERVICESYSTEM/includes/api_guidance_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'save',
                    case_id: caseId,
                    student_id: '<?= $_SESSION['user_id'] ?? 0 ?>',
                    service_rating: rating,
                    feedback_text: feedback
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Feedback submitted successfully!');
                    document.getElementById('guidance-feedback-modal').style.display = 'none';
                    // Reset form for next submission
                    document.getElementById('service-rating').dataset.rating = 0;
                    document.getElementById('feedback-text').value = '';
                } else {
                    alert('Error: ' + (data.message || 'Failed to submit feedback'));
                }
            });
        }

        function createStarRating(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<span style="color: ${i <= rating ? '#fbbf24' : '#d1d5db'}; font-size: 16px;">★</span>`;
            }
            return stars;
        }

        function htmlEscape(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showGoodMoralFeedbackModal() {
            const modal = document.getElementById('good-moral-feedback-modal');
            modal.style.display = 'flex';
            
            // Add star rating interactivity
            setTimeout(() => {
                document.querySelectorAll('.rating-star[data-for="gm-service"]').forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = this.dataset.rating;
                        document.querySelectorAll('.rating-star[data-for="gm-service"]').forEach((s, idx) => {
                            s.style.color = (idx + 1) <= rating ? '#fbbf24' : '#d1d5db';
                        });
                        document.getElementById('gm-service-rating').dataset.rating = rating;
                    });
                });
            }, 100);
        }

        function submitGoodMoralFeedback() {
            const rating = parseInt(document.getElementById('gm-service-rating').dataset.rating || 0);
            const feedback = document.getElementById('gm-feedback-text').value.trim();
            
            if (rating < 1 || rating > 5) {
                alert('Please select a rating');
                return;
            }
            
            if (feedback === '') {
                alert('Please provide your feedback - it is required');
                return;
            }
            
            fetch('/THESIS/SUPPORTSERVICESYSTEM/includes/api_good_moral_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'save',
                    student_id: <?= (int)($_SESSION['user_id'] ?? 0) ?>,
                    service_rating: rating,
                    feedback_text: feedback
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Feedback submitted successfully!');
                    document.getElementById('good-moral-feedback-modal').style.display = 'none';
                    // Reset form for next submission
                    document.getElementById('gm-service-rating').dataset.rating = 0;
                    document.getElementById('gm-feedback-text').value = '';
                } else {
                    alert('Error: ' + (data.message || 'Failed to submit feedback'));
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Swipe-to-Refresh Functionality (Mobile Only: 320px - 768px)
            const swipeRefreshSpinner = document.getElementById('swipe-refresh-spinner');
            let touchStartY = 0;
            let touchEndY = 0;
            let isRefreshing = false;
            const SWIPE_THRESHOLD = 100; // minimum pixels to swipe
            const SWIPE_START_LIMIT = 100; // only detect swipe from top 100px

            function isScreenMobile() {
                const width = window.innerWidth;
                return width >= 320 && width <= 768;
            }

            function showRefreshSpinner() {
                if (isScreenMobile() && !isRefreshing && swipeRefreshSpinner) {
                    isRefreshing = true;
                    swipeRefreshSpinner.style.display = 'flex';
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                }
            }

            document.addEventListener('touchstart', function(e) {
                if (!isScreenMobile()) return;
                const touch = e.touches[0];
                touchStartY = touch.clientY;
            }, { passive: true });

            document.addEventListener('touchend', function(e) {
                if (!isScreenMobile()) return;
                const touch = e.changedTouches[0];
                touchEndY = touch.clientY;

                // Detect swipe down from top of page
                if (touchStartY <= SWIPE_START_LIMIT && touchEndY - touchStartY >= SWIPE_THRESHOLD) {
                    // Check if we're near the top of the page
                    if (window.scrollY <= 10) {
                        e.preventDefault();
                        showRefreshSpinner();
                    }
                }
            }, { passive: true });

            // Existing layout initialization code
            const sidebar = document.querySelector('.side-nav');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const pageShell = document.querySelector('.page-shell');
            const topbar = document.querySelector('.topbar');

            const updateLayout = function() {
                if (!sidebar || !pageShell || !topbar) {
                    return;
                }
                pageShell.style.paddingLeft = sidebar.classList.contains('collapsed') ? '80px' : '280px';
                topbar.style.left = sidebar.classList.contains('collapsed') ? '80px' : '280px';
            };

            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    updateLayout();
                });
            }

            updateLayout();

            const searchBtn = document.querySelector('.nav-search-btn');
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    alert('Search functionality coming soon!');
                });
            }
        });

        function toggleGuidanceSidebar() {
            const sidebar = document.querySelector('.side-nav');
            const pageShell = document.querySelector('.page-shell');
            const topbar = document.querySelector('.topbar');
            if (!sidebar) return;
            sidebar.classList.toggle('collapsed');
            if (pageShell) {
                pageShell.style.paddingLeft = sidebar.classList.contains('collapsed') ? '80px' : '280px';
            }
            if (topbar) {
                topbar.style.left = sidebar.classList.contains('collapsed') ? '80px' : '280px';
            }
        }

        // Mobile Menu Toggle Functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sideNav = document.querySelector('.side-nav');
        const pageOverlay = document.getElementById('pageOverlay');

        if (mobileMenuToggle && sideNav && pageOverlay) {
            // Toggle menu when hamburger icon is clicked
            mobileMenuToggle.addEventListener('click', function() {
                sideNav.classList.toggle('mobile-open');
                pageOverlay.classList.toggle('active');
            });

            // Close menu when overlay is clicked
            pageOverlay.addEventListener('click', function() {
                sideNav.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            });

            // Close menu when any navigation link is clicked
            const navLinks = sideNav.querySelectorAll('a, .nav-toggle');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Don't close if clicking toggle buttons for submenus
                    if (!this.classList.contains('nav-toggle')) {
                        sideNav.classList.remove('mobile-open');
                        pageOverlay.classList.remove('active');
                    }
                });
            });

            // Close menu on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sideNav.classList.contains('mobile-open')) {
                    sideNav.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                }
            });
        }

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

            const tabContainers = document.querySelectorAll('.tabs-container');
            tabContainers.forEach(container => {
                let touchStartX = 0;
                let touchEndX = 0;
                const swipeThreshold = 60;

                container.addEventListener('touchstart', function(event) {
                    touchStartX = event.changedTouches[0].clientX;
                }, { passive: true });

                container.addEventListener('touchend', function(event) {
                    touchEndX = event.changedTouches[0].clientX;
                    const dx = touchEndX - touchStartX;

                    if (Math.abs(dx) < swipeThreshold || window.innerWidth > 768) {
                        return;
                    }

                    const buttons = Array.from(container.querySelectorAll('.tab-button'));
                    const activeButton = container.querySelector('.tab-button.active');
                    const activeIndex = buttons.indexOf(activeButton);

                    if (dx < 0 && activeIndex < buttons.length - 1) {
                        buttons[activeIndex + 1].click();
                    } else if (dx > 0 && activeIndex > 0) {
                        buttons[activeIndex - 1].click();
                    }
                }, { passive: true });
            });
        });

        // Add CSS for tabs if not already defined
        const style = document.createElement('style');
        style.textContent = `
            .tabs-container {
                width: 100%;
                background: white;
                border-radius: 10px;
                margin-bottom: 20px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
                padding: 0.25rem;
            }
            .tabs-header {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
            }
            .tab-button {
                flex: 1;
                padding: 0.9rem 1rem;
                text-align: center;
                background: #f8f9fa;
                border: none;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                color: #111827;
                border-radius: 8px;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            .tab-button i {
                color: #800000;
            }
            .tab-button:hover:not(.active) {
                background: #800000;
                color: white;
            }
            .tab-button:hover:not(.active) i {
                color: #D4AF37;
            }
            .tab-button.active {
                background: #800000;
                color: white;
            }
            .tab-button.active i {
                color: #D4AF37;
            }
            .tab-content {
                display: none;
            }
            .tab-content.active {
                display: block;
                animation: fadeIn 0.3s ease;
            }
            .tabs-swipe-hint {
                display: none;
                font-size: 13px;
                color: #6b7280;
                margin-bottom: 16px;
                text-align: center;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @media (max-width: 768px) {
                .tabs-header {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }
                /* Ensure tabs header stays on top and laid out horizontally */
                .tabs-container { display: block !important; width: 100% !important; }
                .tabs-container .tabs-header { display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; align-items: center !important; width: 100% !important; }
                .tabs-container .tabs-header { order: 0 !important; }
                .tabs-container .tabs-swipe-hint { width: 100% !important; order: 1 !important; }
                .tabs-container .tab-content { width: 100% !important; display: none !important; order: 2 !important; }
                .tabs-container .tab-content.active { display: block !important; }
                .tabs-header::-webkit-scrollbar {
                    display: none;
                }
                .tab-button {
                    flex: 0 0 auto;
                    white-space: nowrap;
                    min-width: 110px;
                    padding: 10px 12px;
                    font-size: 13px;
                }
                /* allow buttons to shrink if necessary on very small screens */
                @media (max-width: 420px) {
                    .tab-button { min-width: 90px; padding: 8px 10px; }
                }
                .tabs-swipe-hint {
                    display: block;
                }
                .tab-content {
                    touch-action: pan-y;
                }
            }
        `;
        if (!document.querySelector('style[data-tabs]')) {
            style.setAttribute('data-tabs', 'true');
            document.head.appendChild(style);
        }
    </script>
    <script>
        // Generic tab switcher for guidance dashboard
        function switchTabGuidance(tabId, btn) {
            const container = btn ? btn.closest('.tabs-container') : null;
            const tabContainer = container || document.querySelector('.tabs-container');
            if (!tabContainer) return;

            const allTabs = tabContainer.querySelectorAll('.tab-content');
            allTabs.forEach(t => t.classList.remove('active'));

            const allButtons = tabContainer.querySelectorAll('.tabs-header .tab-button');
            allButtons.forEach(b => b.classList.remove('active'));

            const tabEl = tabContainer.querySelector('#' + tabId);
            if (tabEl) tabEl.classList.add('active');
            if (btn) btn.classList.add('active');
        }

        // Wire up tab buttons and add mobile swipe support
        document.addEventListener('DOMContentLoaded', function () {
            const tabButtons = document.querySelectorAll('.tabs-header .tab-button');
            tabButtons.forEach(btn => {
                btn.addEventListener('click', function (e) {
                    const target = btn.getAttribute('data-tab');
                    if (target) switchTabGuidance(target, btn);
                });
            });

            const container = document.querySelector('.tabs-container');
            if (!container) return;
            let touchStartX = 0;
            let touchEndX = 0;

            function handleGuidanceSwipe() {
                if (Math.abs(touchEndX - touchStartX) < 50) return;
                const buttons = Array.from(document.querySelectorAll('.tabs-header .tab-button'));
                const activeIndex = buttons.findIndex(b => b.classList.contains('active'));
                if (activeIndex === -1) return;
                if (touchEndX < touchStartX) {
                    if (activeIndex < buttons.length - 1) buttons[activeIndex + 1].click();
                } else {
                    if (activeIndex > 0) buttons[activeIndex - 1].click();
                }
            }

            container.addEventListener('touchstart', function (e) {
                if (window.innerWidth < 320 || window.innerWidth > 768) return;
                if (e.touches && e.touches.length === 1) touchStartX = e.touches[0].clientX;
            }, { passive: true });

            container.addEventListener('touchend', function (e) {
                if (window.innerWidth < 320 || window.innerWidth > 768) return;
                if (e.changedTouches && e.changedTouches.length === 1) {
                    touchEndX = e.changedTouches[0].clientX;
                    handleGuidanceSwipe();
                }
            }, { passive: true });
        });
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>

















