<?php
require_once '../includes/session.php';
require_login();
$user = current_user();

if ($user['role'] !== 'admin' && $user['role'] !== 'teacher') {
    header('Location: guidance_home.php');
    exit;
}

// Check if case ID is provided
$caseId = (int)($_GET['id'] ?? 0);
if (!$caseId) {
    header('Location: case_management.php');
    exit;
}

// Get case details
$case = get_guidance_case_by_id($caseId);
if (!$case) {
    header('Location: case_management.php');
    exit;
}

$message = '';
$messageType = '';
$attachments = [];

// Check for upload success
if (isset($_GET['upload_success'])) {
    $message = 'Document uploaded successfully.';
    $messageType = 'success';
}

$pdo = get_db();
$sessionStmt = $pdo->prepare('SELECT participation_scope FROM guidance_sessions WHERE case_id = ? ORDER BY session_date ASC, session_time ASC LIMIT 1');
$sessionStmt->execute([$caseId]);
$scheduledScope = $sessionStmt->fetchColumn();
$displayParticipants = guidance_participants_label($scheduledScope ?: ($case['participants'] ?? ''));

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_reporter' && isset($_GET['reporter_id'])) {
        $reporterId = (int)$_GET['reporter_id'];
        $stmt = $pdo->prepare("SELECT * FROM case_reporters WHERE id = ? AND case_id = ?");
        $stmt->execute([$reporterId, $caseId]);
        $reporter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reporter) {
            echo json_encode(['success' => true, 'reporter' => $reporter]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Reporter not found']);
        }
        exit;
    }
    
    if ($_GET['ajax'] === 'get_reported_person' && isset($_GET['person_id'])) {
        $personId = (int)$_GET['person_id'];
        $stmt = $pdo->prepare("SELECT * FROM case_reported_persons WHERE id = ? AND case_id = ?");
        $stmt->execute([$personId, $caseId]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($person) {
            echo json_encode(['success' => true, 'person' => $person]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Person not found']);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_participants') {
        $participants = $_POST['participants'] ?? 'STUDENT ONLY';
        
        if (update_guidance_case($caseId, ['participants' => $participants])) {
            $message = 'Participants updated successfully.';
            $messageType = 'success';
            // Refresh case data
            $case = get_guidance_case_by_id($caseId);
        } else {
            $message = 'Unable to update participants.';
            $messageType = 'error';
        }
    } elseif ($action === 'update_resolution') {
        $externalResolution = $_POST['external_resolution'] ?? 'none';
        $internalResolution = $_POST['internal_resolution'] ?? 'none';
        $internalNotes = trim($_POST['internal_notes'] ?? '');

        if ($internalResolution === 'closed_case') {
            $newStatus = 'closed';
        } elseif ($externalResolution === 'case_resolved') {
            $newStatus = 'resolved';
        } else {
            $newStatus = 'in_progress';
        }

        if (update_guidance_case($caseId, [
            'external_resolution' => $externalResolution,
            'internal_resolution' => $internalResolution,
            'internal_notes' => $internalNotes,
            'status' => $newStatus
        ])) {
            $message = 'Resolution updated successfully.';
            $messageType = 'success';
            // Refresh case data
            $case = get_guidance_case_by_id($caseId);
        } else {
            $message = 'Unable to update resolution.';
            $messageType = 'error';
        }
    } elseif ($action === 'log_session') {
        $attendanceStatus = $_POST['attendance_status'] ?? 'pending';
        $sessionMode = trim($_POST['session_mode'] ?? '');
        $locationLink = trim($_POST['location_link'] ?? '');
        $observationNotes = trim($_POST['observation_notes'] ?? '');

        $validation = validate_observation_text($observationNotes);
        if (!$validation['valid']) {
            $message = 'Observation validation failed: ' . $validation['message'];
            $messageType = 'error';
        } else {
            $sessionNotes = '';
            if ($sessionMode) {
                $sessionNotes .= 'Mode: ' . $sessionMode . '. ';
            }
            if ($locationLink) {
                $sessionNotes .= 'Venue: ' . $locationLink . '. ';
            }
            $sessionNotes .= $observationNotes;

            if (add_guidance_session([
                'case_id' => $caseId,
                'counselor_id' => $user['id'],
                'session_date' => (new DateTime())->format('Y-m-d'),
                'session_time' => (new DateTime())->format('H:i:s'),
                'session_category' => 'follow_up_session',
                'participation_scope' => 'student_only',
                'attendance_status' => $attendanceStatus,
                'notes' => $sessionNotes,
            ])) {
                update_guidance_case($caseId, ['status' => 'in_progress', 'counselor_id' => $user['id']]);
                $message = 'Session logged. Attendance: ' . htmlspecialchars($attendanceStatus);
                $messageType = 'success';
                // Refresh case data
                $case = get_guidance_case_by_id($caseId);
            } else {
                $message = 'Unable to log session.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'edit_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $sessionDate = $_POST['session_date'] ?? '';
        $sessionTime = $_POST['session_time'] ?? '';
        $sessionMode = trim($_POST['session_mode'] ?? '');
        $locationLink = trim($_POST['location_link'] ?? '');

        if ($sessionId && $sessionDate && $sessionTime && $sessionMode) {
            $sessionDateTime = $sessionDate . ' ' . $sessionTime . ':00';
            
            $notes = 'Mode: ' . $sessionMode;
            if ($locationLink) {
                $notes .= '. ' . ($sessionMode === 'Face-to-Face' ? 'Venue' : 'Link') . ': ' . $locationLink;
            }

            if (table_has_column('guidance_sessions', 'session_time')) {
                $stmt = $pdo->prepare("UPDATE guidance_sessions SET session_date = ?, session_time = ?, notes = ? WHERE id = ? AND case_id = ? AND attendance_status = 'pending'");
                $success = $stmt->execute([$sessionDate, $sessionTime . ':00', $notes, $sessionId, $caseId]);
            } else {
                $stmt = $pdo->prepare("UPDATE guidance_sessions SET session_date = ?, notes = ? WHERE id = ? AND case_id = ? AND attendance_status = 'pending'");
                $success = $stmt->execute([$sessionDateTime, $notes, $sessionId, $caseId]);
            }

            if ($success) {
                $message = 'Session updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to update session.';
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        }
    } elseif ($action === 'update_reporters') {
        $selectedReporters = json_decode($_POST['selected_reporters'] ?? '[]', true);
        
        if (!empty($selectedReporters)) {
            // Add new reporters without deleting existing ones
            $stmt = $pdo->prepare("INSERT INTO case_reporters (case_id, reporter_type, reporter_name, reporter_id, reporter_course, mobile_number) VALUES (?, ?, ?, ?, ?, ?)");
            $success = true;
            
            foreach ($selectedReporters as $reporter) {
                $mobile = $reporter['mobile'] ?? '';
                if (!$stmt->execute([$caseId, $reporter['type'], $reporter['name'], $reporter['id'] ?? '', $reporter['course'] ?? '', $mobile])) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                $message = 'Reporters added successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to add reporters.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update_reported_persons') {
        $selectedReportedPersons = json_decode($_POST['selected_reported_persons'] ?? '[]', true);
        
        if (!empty($selectedReportedPersons)) {
            // Add new reported persons without deleting existing ones
            $stmt = $pdo->prepare("INSERT INTO case_reported_persons (case_id, person_type, person_name, person_id, person_user_id, person_course) VALUES (?, ?, ?, ?, ?, ?)");
            $success = true;
            
            foreach ($selectedReportedPersons as $person) {
                $personUserId = $person['user_id'] ?? $person['id'] ?? null;
                $personExternalId = $person['external_id'] ?? $person['id'] ?? null;
                
                // If we don't have user_id but have external_id, look it up from the database
                if (!$personUserId && $personExternalId && ($person['type'] ?? '') !== 'course') {
                    $lookupStmt = $pdo->prepare(
                        'SELECT id FROM users WHERE (student_id = ? OR employee_id = ?) LIMIT 1'
                    );
                    $lookupStmt->execute([$personExternalId, $personExternalId]);
                    $foundUser = $lookupStmt->fetchColumn();
                    if ($foundUser) {
                        $personUserId = (int)$foundUser;
                    }
                }
                
                if (!$stmt->execute([$caseId, $person['type'], $person['name'], $personExternalId, $personUserId, $person['course'] ?? ''])) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                $message = 'Involved parties added successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to add involved parties.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'update_description') {
        $description = trim($_POST['incident_description'] ?? '');
        
        if (update_guidance_case($caseId, ['incident_description' => $description])) {
            $message = 'Case description updated successfully.';
            $messageType = 'success';
            $case = get_guidance_case_by_id($caseId);
        } else {
            $message = 'Unable to update case description.';
            $messageType = 'error';
        }
    } elseif ($action === 'update_notes') {
        $notes = trim($_POST['internal_notes'] ?? '');
        
        if (update_guidance_case($caseId, ['internal_notes' => $notes])) {
            $message = 'Internal notes updated successfully.';
            $messageType = 'success';
            $case = get_guidance_case_by_id($caseId);
        } else {
            $message = 'Unable to update internal notes.';
            $messageType = 'error';
        }
    } elseif ($action === 'upload_document') {
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['document'];
            $fileName = $file['name'];
            $fileTmp = $file['tmp_name'];
            $fileSize = $file['size'];
            
            // Validate file size (5MB max)
            if ($fileSize > 5 * 1024 * 1024) {
                $message = 'File size too large. Maximum 5MB allowed.';
                $messageType = 'error';
            } else {
                // Validate file type
                $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if (!in_array($fileExt, $allowedTypes)) {
                    $message = 'Invalid file type. Allowed: PDF, Word, images, text files.';
                    $messageType = 'error';
                } else {
                    // Create uploads directory if it doesn't exist
                    $uploadDir = __DIR__ . '/../uploads/case_documents/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $uniqueName = uniqid('case_' . $caseId . '_') . '.' . $fileExt;
                    $filePath = $uploadDir . $uniqueName;
                    
                    if (move_uploaded_file($fileTmp, $filePath)) {
                        // Save to database
                        $stmt = $pdo->prepare("INSERT INTO guidance_case_attachments (case_id, uploaded_by, filepath, filename) VALUES (?, ?, ?, ?)");
                        $webPath = '/uploads/case_documents/' . $uniqueName;
                        
                        if ($stmt->execute([$caseId, $user['id'], $webPath, $fileName])) {
                            // Redirect to reload the page with success message
                            header('Location: ?id=' . $caseId . '&upload_success=1');
                            exit;
                        } else {
                            unlink($filePath); // Delete file if DB insert failed
                            $message = 'Unable to save document to database.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Unable to upload file.';
                        $messageType = 'error';
                    }
                }
            }
        }
    } elseif ($action === 'remove_reporter') {
        $reporterId = (int)($_POST['reporter_id'] ?? 0);
        
        if ($reporterId) {
            $stmt = $pdo->prepare("DELETE FROM case_reporters WHERE id = ? AND case_id = ?");
            if ($stmt->execute([$reporterId, $caseId])) {
                $message = 'Reporter removed successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to remove reporter.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid reporter ID.';
            $messageType = 'error';
        }
    } elseif ($action === 'remove_reported_person') {
        $personId = (int)($_POST['person_id'] ?? 0);
        
        if ($personId) {
            $stmt = $pdo->prepare("DELETE FROM case_reported_persons WHERE id = ? AND case_id = ?");
            if ($stmt->execute([$personId, $caseId])) {
                $message = 'Involved party removed successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to remove involved party.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid person ID.';
            $messageType = 'error';
        }
    } elseif ($action === 'update_single_reporter') {
        $reporterId = (int)($_POST['reporter_id'] ?? 0);
        $reporterType = trim($_POST['reporter_type'] ?? '');
        $reporterName = trim($_POST['reporter_name'] ?? '');
        $reporterIdField = trim($_POST['reporter_id_field'] ?? '');
        $reporterCourse = trim($_POST['reporter_course'] ?? '');
        $mobileNumber = trim($_POST['mobile_number'] ?? '');
        
        if ($reporterId && $reporterType && $reporterName) {
            $stmt = $pdo->prepare("UPDATE case_reporters SET reporter_type = ?, reporter_name = ?, reporter_id = ?, reporter_course = ?, mobile_number = ? WHERE id = ? AND case_id = ?");
            if ($stmt->execute([$reporterType, $reporterName, $reporterIdField, $reporterCourse, $mobileNumber, $reporterId, $caseId])) {
                $message = 'Reporter updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to update reporter.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid reporter data.';
            $messageType = 'error';
        }
    } elseif ($action === 'update_single_reported_person') {
        $personId = (int)($_POST['person_id'] ?? 0);
        $personType = trim($_POST['person_type'] ?? '');
        $personName = trim($_POST['person_name'] ?? '');
        $personIdField = trim($_POST['person_id_field'] ?? '');
        $personCourse = trim($_POST['person_course'] ?? '');
        
        if ($personId && $personType && $personName) {
            $stmt = $pdo->prepare("UPDATE case_reported_persons SET person_type = ?, person_name = ?, person_id = ?, person_course = ? WHERE id = ? AND case_id = ?");
            if ($stmt->execute([$personType, $personName, $personIdField, $personCourse, $personId, $caseId])) {
                $message = 'Involved party updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to update involved party.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid person data.';
            $messageType = 'error';
        }
    }
}

$pageTitle = 'Case Details - ' . htmlspecialchars($case['case_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Guidance System</title>
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

        .case-details-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .case-header {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .case-title {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 16px;
        }

        .case-meta {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            font-size: 16px;
        }

        .case-meta div {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .case-meta strong {
            color: #374151;
        }

        .case-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .case-section {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .case-section h3 {
            margin-top: 0;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
            font-size: 18px;
            font-weight: 600;
        }

        .case-description {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 16px;
            color: #374151;
            line-height: 1.6;
        }

        .session-item {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 16px;
            overflow: hidden;
        }

        .session-header {
            background: #f9fafb;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .session-content {
            padding: 16px;
        }

        .session-mode {
            background: #eef2ff;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            font-weight: 600;
            color: #1e40af;
        }

        .session-notes {
            background: #f3f4f6;
            border-radius: 4px;
            padding: 12px;
            color: #4b5563;
            font-size: 14px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1f2937;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
        }

        .message-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .message-error {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fca5a5;
        }

        .actions {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        @media (max-width: 720px) {
            .case-details-container {
                padding: 16px 12px;
            }
            .case-header {
                padding: 18px;
            }
            .case-title {
                font-size: 24px;
            }
            .case-meta {
                gap: 12px;
                font-size: 14px;
            }
            .case-meta div {
                flex: 1 1 100%;
                min-width: 0;
            }
            .case-content {
                display: block;
                gap: 16px;
            }
            .case-section {
                padding: 16px;
            }
            .case-section h3 {
                font-size: 17px;
                margin-bottom: 14px;
            }
            .case-description,
            .session-content,
            .session-notes,
            .actions,
            .btn,
            .form-group input,
            .form-group select,
            .form-group textarea {
                width: 100%;
            }
            .btn {
                justify-content: center;
                padding: 10px 14px;
                font-size: 14px;
                flex: 1 1 100%;
            }
            .status-badge {
                white-space: normal;
                padding: 6px 10px;
                font-size: 12px;
            }
            .session-header {
                flex-wrap: wrap;
                gap: 12px;
            }
            .session-header > div {
                flex: 1 1 100%;
            }
            .session-item {
                margin-bottom: 14px;
            }
            .message {
                font-size: 14px;
            }
            .case-section button.btn {
                width: auto;
                display: inline-flex;
                padding: 8px 12px;
                margin-top: 8px;
            }
            .case-section h3 button {
                float: none;
            }
            .case-section h3 {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }
            .case-section h3 .btn {
                margin-top: 0;
            }
            .case-section > p,
            .case-section > div:not(.case-description) {
                font-size: 14px;
            }
            .case-section > div {
                overflow-wrap: break-word;
            }
            .case-details-container > div[style*="display: flex; gap: 12px;"] {
                flex-direction: column;
            }
            .case-details-container > div[style*="display: flex; justify-content: space-between;"] {
                flex-direction: column;
                align-items: stretch;
            }
            .case-details-container > div[style*="display: flex; justify-content: space-between;"] .btn {
                width: 100%;
            }
            .case-details-container [style*="display: flex; justify-content: space-between; align-items: flex-start;"] {
                flex-direction: column;
                align-items: stretch;
            }
            .case-details-container [style*="display: flex; justify-content: space-between; align-items: flex-start;"] .btn {
                width: 100%;
                margin-top: 10px;
            }
            .case-details-container [style*="display: grid; gap: 12px;"] {
                display: block;
            }
            .case-details-container [style*="display: grid; gap: 12px;"] > div {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .case-details-container {
                padding: 12px 10px;
            }
            .case-header {
                padding: 14px;
            }
            .case-title {
                font-size: 20px;
            }
            .case-meta {
                font-size: 13px;
            }
            .case-section {
                padding: 14px;
            }
            .case-section h3 {
                font-size: 15px;
            }
            .btn {
                padding: 10px 12px;
                font-size: 13px;
            }
            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 13px;
            }
            .session-header {
                gap: 10px;
            }
            .case-meta div {
                gap: 6px;
            }
        }

        .case-details-container {
            color: #68494a;
        }

        .case-header,
        .case-section {
            border-color: #eadfd8;
            border-radius: 16px;
            background: #fffdfb;
            box-shadow: 0 10px 24px rgba(92, 31, 35, 0.08);
        }

        .case-title,
        .case-section h3,
        .case-details-container h2 {
            color: #68151c;
        }

        .case-section h3 {
            border-bottom-color: #eadfd8;
        }

        .case-meta strong,
        .case-details-container strong,
        .form-group label {
            color: #8f0011;
        }

        .case-description,
        .session-notes {
            background: #fffaf4;
            border-color: #eee3dc;
            color: #68494a;
        }

        .session-item {
            border-color: #eadfd8;
            border-radius: 12px;
        }

        .session-header {
            background: #f9ebe5;
            border-bottom-color: #eadfd8;
        }

        .session-mode {
            background: #f4e2d9;
            color: #8f0011;
        }

        .status-badge,
        .badge-primary {
            background: #f4e2d9;
            color: #8f0011;
        }

        .badge-success {
            background: #e8f0dc;
            color: #49652e;
        }

        .badge-danger {
            background: #f9e0dc;
            color: #8f0011;
        }

        .badge-warning {
            background: #f8edcf;
            color: #8a641c;
        }

        .btn-primary,
        .btn-success {
            background: linear-gradient(135deg, #68151c, #9e3030);
            color: #ffffff;
            border: 1px solid #68151c;
        }

        .btn-primary:hover,
        .btn-success:hover {
            background: linear-gradient(135deg, #531017, #8f0011);
        }

        .btn-secondary {
            background: #fbf4e4;
            color: #5c1f23;
            border: 1px solid #d8b8a8;
        }

        .btn-secondary:hover {
            background: #f4e2d9;
            color: #8f0011;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            border-color: #e5d5cc;
            border-radius: 10px;
            background: #fffaf4;
            color: #513b39;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #9b0b16;
            box-shadow: 0 0 0 3px rgba(155, 11, 22, 0.1);
        }

        .modal-content {
            border: 1px solid #eadfd8;
            border-radius: 16px;
            background: #fffdfb;
            box-shadow: 0 24px 60px rgba(92, 31, 35, 0.2);
        }

        .modal-header {
            color: #68151c;
        }

        .actions {
            border-top-color: #eadfd8;
        }

        .message-success {
            background: #e8f0dc;
            color: #49652e;
            border-color: #c8d9b2;
        }

        .message-error {
            background: #f9e0dc;
            color: #8f0011;
            border-color: #e4b9b0;
        }

        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <script src="../assets/js/app.js" defer></script>

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
                    <div class="case-details-container">
        <!-- Back Button -->
        <div style="margin-bottom: 20px;">
            <a href="case_management.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Case Management
            </a>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="message message-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Case Header -->
        <div class="case-header">
            <div class="case-title">Case <?php echo htmlspecialchars($case['case_number']); ?></div>
            <div class="case-meta">
                <div><strong>Priority:</strong>
                    <span style="font-weight: 600; padding: 2px 8px; border-radius: 3px; background: <?php echo ($case['priority_level'] ?? 'Medium') === 'Emergency' ? '#fca5a5' : (($case['priority_level'] ?? 'Medium') === 'High' ? '#fbbf24' : '#bfdbfe'); ?>;">
                        <?php echo htmlspecialchars($case['priority_level'] ?? 'Medium'); ?>
                    </span>
                </div>
                <div><strong>Participants:</strong>
                    <span style="font-weight: 600; padding: 2px 8px; border-radius: 3px; background: #e0e7ff; color: #3730a3;">
                        <?php echo htmlspecialchars($displayParticipants); ?>
                    </span>
                    <button type="button" class="btn btn-secondary" style="font-size: 11px; padding: 2px 6px; margin-left: 8px;" onclick="openModal('edit_participants')">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                </div>
                <div><strong>Status:</strong> <span class="status-badge badge-primary"><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($case['status']))); ?></span></div>
                <div><strong>Created:</strong> <?php echo date('M d, Y', strtotime($case['created_at'])); ?></div>
                <div><strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($case['updated_at'] ?? $case['created_at'])); ?></div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="margin-bottom: 24px; display: flex; gap: 12px; flex-wrap: wrap;">
            <button type="button" class="btn btn-success" onclick="openModal('session')">
                <i class="fas fa-plus"></i> Log Session
            </button>
            <button type="button" class="btn btn-success" onclick="openModal('resolution')">
                <i class="fas fa-edit"></i> Update Resolution
            </button>
        </div>

        <!-- Left Column: Reporter Details -->
            <div class="case-section">
                <h3>Reporter Details 
                    <button type="button" class="btn btn-success" style="float: right; font-size: 12px; padding: 4px 8px;" onclick="openModal('edit_reporter')">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </h3>
                <?php
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM case_reporters WHERE case_id = ?");
                        $stmt->execute([$caseId]);
                        $reporters = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $reporters = [];
                    }
                ?>
                <?php if (!empty($reporters)): ?>
                    <?php foreach ($reporters as $reporter): ?>
                        <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="flex: 1;">
                                <div><strong><?php echo htmlspecialchars($reporter['reporter_name']); ?></strong></div>
                                <div style="color: #6b7280; font-size: 13px;">Type: <span style="font-weight: 500;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $reporter['reporter_type']))); ?></span></div>
                                <?php if ($reporter['reporter_id']): ?>
                                    <div style="color: #6b7280; font-size: 13px;">ID: <span style="font-weight: 500;"><?php echo htmlspecialchars($reporter['reporter_id']); ?></span></div>
                                <?php endif; ?>
                                <?php if ($reporter['reporter_course']): ?>
                                    <div style="color: #6b7280; font-size: 13px;">Course: <span style="font-weight: 500;"><?php echo htmlspecialchars($reporter['reporter_course']); ?></span></div>
                                <?php endif; ?>
                                <?php if ($reporter['mobile_number']): ?>
                                    <div style="color: #6b7280; font-size: 13px;">📱 <?php echo htmlspecialchars($reporter['mobile_number']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <button type="button" class="btn btn-danger" style="font-size: 11px; padding: 2px 6px;" onclick="removeReporter(<?php echo $reporter['id']; ?>)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #6b7280; font-size: 13px;">No reporters on file.</p>
                <?php endif; ?>
            </div>

            <!-- Right Column: Involved Parties -->
            <div class="case-section">
                <h3>Involved Parties 
                    <button type="button" class="btn btn-success" style="float: right; font-size: 12px; padding: 4px 8px;" onclick="openModal('edit_reported')">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </h3>
                <?php
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM case_reported_persons WHERE case_id = ?");
                        $stmt->execute([$caseId]);
                        $reportedPersons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $reportedPersons = [];
                    }
                ?>
                <?php if (!empty($reportedPersons)): ?>
                    <?php foreach ($reportedPersons as $person): ?>
                        <div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="flex: 1;">
                                <div><strong><?php echo htmlspecialchars($person['person_name']); ?></strong></div>
                                <div style="color: #6b7280; font-size: 13px;">Type: <span style="font-weight: 500;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $person['person_type']))); ?></span></div>
                                <?php if ($person['person_id']): ?>
                                    <div style="color: #6b7280; font-size: 13px;">ID: <span style="font-weight: 500;"><?php echo htmlspecialchars($person['person_id']); ?></span></div>
                                <?php endif; ?>
                                <?php if ($person['person_course']): ?>
                                    <div style="color: #6b7280; font-size: 13px;">Course: <span style="font-weight: 500;"><?php echo htmlspecialchars($person['person_course']); ?></span></div>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <button type="button" class="btn btn-danger" style="font-size: 11px; padding: 2px 6px;" onclick="removeReportedPerson(<?php echo $person['id']; ?>)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #6b7280; font-size: 13px;">No involved parties on file.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Case Description -->
        <div class="case-section">
            <h3>Case Description <button type="button" class="btn btn-secondary" style="float: right; font-size: 12px; padding: 4px 8px;" onclick="openModal('edit_description')"><i class="fas fa-edit"></i> Edit</button></h3>
            <div class="case-description">
                <?php echo nl2br(htmlspecialchars($case['incident_description'] ?? 'No description provided.')); ?>
            </div>
        </div>

        <!-- Session History -->
        <div class="case-section">
            <h3>Session History <button type="button" class="btn btn-secondary" style="float: right; font-size: 12px; padding: 4px 8px;" onclick="openModal('edit_sessions')"><i class="fas fa-edit"></i> Edit</button></h3>
            <?php
                $stmt = $pdo->prepare("SELECT * FROM guidance_sessions WHERE case_id = ? ORDER BY session_date DESC, session_time DESC");
                $stmt->execute([$caseId]);
                $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (!empty($sessions)): ?>
                <?php foreach ($sessions as $session): ?>
                    <?php
                        $sessionDateTime = $session['session_date'];
                        if (!empty($session['session_time'])) {
                            $sessionDateTime .= ' ' . $session['session_time'];
                        }
                        $formattedDate = date('M d, Y g:i A', strtotime($sessionDateTime));
                        $sessionNotesRaw = $session['notes'] ?? 'No notes recorded.';
                        $modeLabel = '';
                        $venueLabel = '';
                        $notesContent = $sessionNotesRaw;
                        if (preg_match('/Mode:\s*([^\.]+)\./i', $sessionNotesRaw, $modeMatch)) {
                            $modeLabel = trim($modeMatch[1]);
                            $notesContent = preg_replace('/Mode:\s*[^\.]+\./i', '', $notesContent);
                        }
                        if (preg_match('/(?:Venue|Link):\s*(.+?)(\.|$)/i', $sessionNotesRaw, $venueMatch)) {
                            $venueLabel = trim($venueMatch[1]);
                            $notesContent = preg_replace('/(?:Venue|Link):\s*.+?(\.|$)/i', '', $notesContent);
                        }
                        $notesContent = trim($notesContent);
                        if ($notesContent === '') {
                            $notesContent = 'No additional notes recorded.';
                        }
                    ?>
                    <div class="session-item">
                        <div class="session-header">
                            <div>
                                <strong><?php echo htmlspecialchars($formattedDate); ?></strong>
                                <div style="color: #6b7280; font-size: 13px;">Category: <span style="font-weight: 500;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $session['session_category']))); ?></span></div>
                            </div>
                            <span class="status-badge <?php echo $session['attendance_status'] === 'attended' ? 'badge-success' : ($session['attendance_status'] === 'non_appearance' ? 'badge-danger' : 'badge-warning'); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $session['attendance_status']))); ?>
                            </span>
                        </div>
                        <div class="session-content">
                            <?php if ($modeLabel || $venueLabel): ?>
                                <div class="session-mode">
                                    <?php if ($modeLabel): ?>
                                        <div>Mode: <?php echo htmlspecialchars($modeLabel); ?></div>
                                    <?php endif; ?>
                                    <?php if ($venueLabel): ?>
                                        <div><?php echo htmlspecialchars($sessionNotesRaw && stripos($sessionNotesRaw, 'Link:') !== false ? 'Link' : 'Venue'); ?>: <?php echo htmlspecialchars($venueLabel); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="session-notes">
                                <?php echo nl2br(htmlspecialchars($notesContent)); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #6b7280; font-size: 13px; padding: 12px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; margin: 0;">No sessions logged yet.</p>
            <?php endif; ?>
        </div>

        <!-- Supporting Documents -->
        <div class="case-section">
            <h3>📎 Supporting Documents <button type="button" class="btn btn-secondary" style="float: right; font-size: 12px; padding: 4px 8px;" onclick="openModal('edit_documents')"><i class="fas fa-edit"></i> Edit</button></h3>
            <?php
                $attachments = [];
                try {
                    $stmt = $pdo->prepare("SELECT * FROM guidance_case_attachments WHERE case_id = ? ORDER BY uploaded_at DESC");
                    if ($stmt && $stmt->execute([$caseId])) {
                        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                } catch (Exception $e) {
                    error_log('Error fetching attachments: ' . $e->getMessage());
                    $attachments = [];
                }
            ?>
            <?php if (!empty($attachments)): ?>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($attachments as $attachment): ?>
                        <div style="display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; background: #f9fafb;">
                            <div style="flex: 1;">
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($attachment['filename'] ?? ''); ?></div>
                                <div style="color: #6b7280; font-size: 12px;">Uploaded: <?php echo date('M d, Y', strtotime($attachment['uploaded_at'] ?? 'now')); ?></div>
                            </div>
                            <a href="<?php echo htmlspecialchars($attachment['filepath'] ?? ''); ?>" target="_blank" class="btn btn-primary" style="font-size: 12px; padding: 6px 12px;">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #6b7280; font-size: 13px; padding: 12px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; margin: 0;">No supporting documents uploaded.</p>
            <?php endif; ?>
        </div>

        <!-- Internal Notes -->
        <div class="case-section">
            <h3>Internal Notes (Counselor Only) <button type="button" class="btn btn-secondary" style="float: right; font-size: 12px; padding: 4px 8px;" onclick="openModal('edit_notes')"><i class="fas fa-edit"></i> Edit</button></h3>
            <div style="padding: 12px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 6px; color: #92400e; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($case['internal_notes'] ?? 'No internal notes recorded.')); ?>
            </div>
        </div>

        <div class="actions">
            <a href="case_management.php" class="btn btn-secondary">Back to Cases</a>
        </div>
    </div>

    <!-- Log Session Modal -->
    <div class="modal" id="session">
        <div class="modal-content">
            <div class="modal-header">Log Counseling Session</div>
            <form method="post">
                <input type="hidden" name="action" value="log_session">
                <div class="form-group">
                    <label>Attendance Status:</label>
                    <select name="attendance_status" required>
                        <option value="pending">Pending</option>
                        <option value="attended">Attended</option>
                        <option value="non_appearance">Non-Appearance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Session Mode:</label>
                    <select name="session_mode" required>
                        <option value="Face-to-Face">Face-to-Face</option>
                        <option value="Online">Online</option>
                        <option value="Phone">Phone</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Venue / Link:</label>
                    <input type="text" name="location_link" placeholder="Enter venue or meeting link" />
                </div>
                <div class="form-group">
                    <label>Observation Notes:</label>
                    <textarea name="observation_notes" placeholder="Behavioral observations, progress, concerns..." required></textarea>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="button btn-primary">Log Session</button>
                    <button type="button" class="button btn-secondary" onclick="closeModal('session')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resolution Modal -->
    <div class="modal" id="resolution">
        <div class="modal-content">
            <div class="modal-header">Update Case Resolution</div>
            <form method="post">
                <input type="hidden" name="action" value="update_resolution">
                <div class="form-group">
                    <label>External Resolution (Public):</label>
                    <select name="external_resolution" required>
                        <option value="none">None</option>
                        <option value="counseling_completed_follow_up">Counseling Completed - Follow-up Recommended</option>
                        <option value="advised_behavioral_improvement">Advised Behavioral Improvement</option>
                        <option value="under_monitoring">Under Monitoring</option>
                        <option value="case_resolved">Case Resolved</option>
                        <option value="scheduled_for_further_sessions">Scheduled for Further Sessions</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Internal Resolution (Confidential):</label>
                    <select name="internal_resolution" required>
                        <option value="none">None</option>
                        <option value="with_reformation">With Reformation</option>
                        <option value="without_reformation">Without Reformation</option>
                        <option value="with_warning">With Warning</option>
                        <option value="under_monitoring">Under Monitoring</option>
                        <option value="escalated_case">Escalated Case</option>
                        <option value="ongoing_case">Ongoing Case</option>
                        <option value="closed_case">Closed Case</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Internal Notes (Counselor Eyes Only):</label>
                    <textarea name="internal_notes" placeholder="Private assessment, clinical observations..." required><?php echo htmlspecialchars($case['internal_notes'] ?? ''); ?></textarea>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="button btn-primary">Update Resolution</button>
                    <button type="button" class="button btn-secondary" onclick="closeModal('resolution')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Reporter Details Modal -->
    <div class="modal" id="edit_reporter">
        <div class="modal-content">
            <div class="modal-header">Add Reporter Details</div>
            <form method="post" onsubmit="prepareEditReportersSubmit(event)">
                <input type="hidden" name="action" value="update_reporters">
                <input type="hidden" name="selected_reporters" id="edit_selected_reporters_input" value="">
                
                <div class="form-group">
                    <label>Reporter Type:</label>
                    <select name="reporter_type" id="edit_reporter_type" required onchange="handleEditReporterTypeChange()">
                        <option value="">-- Select reporter type --</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="parent_guardian">Parent/Guardian</option>
                        <option value="course">Course</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Reporter Search Section -->
                <div id="edit_reporter_search_section" style="display: none;">
                    <div class="form-group">
                        <label>Find Reporter by Name or ID</label>
                        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                            <input type="text" id="edit_reporter_search" placeholder="Search by name or ID..." style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                            <button type="button" class="button btn-secondary" style="padding: 8px 12px;" onclick="searchEditReporters()">Search</button>
                        </div>
                        <div id="edit_reporter_search_results" style="display: none; border: 1px solid #e5e7eb; border-radius: 4px; max-height: 200px; overflow-y: auto; background: #f9fafb;">
                        </div>
                        <small style="color: #6b7280; display: block; margin-top: 4px;">Search by name or ID for students and teachers.</small>
                    </div>
                </div>

                <!-- Manual Reporter Form -->
                <div id="edit_manual_reporter_form" style="display: none; margin-top: 16px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 4px; background: #f9fafb;">
                    <h5 style="margin-bottom: 12px; color: #374151;">Add reporter manually</h5>
                    <div class="form-group" id="edit_reporter_name_div" style="display: none;">
                        <label for="edit_manual_reporter_name">Full Name:</label>
                        <input type="text" id="edit_manual_reporter_name" placeholder="Enter full name">
                    </div>
                    <div class="form-group" id="edit_reporter_id_div" style="display: none;">
                        <label for="edit_manual_reporter_id">ID:</label>
                        <input type="text" id="edit_manual_reporter_id" placeholder="ID">
                    </div>
                    <div class="form-group" id="edit_reporter_course_div" style="display: none;">
                        <label for="edit_manual_reporter_course_select">Course</label>
                        <select id="edit_manual_reporter_course_select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                            <option value="">Select course</option>
                            <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                            <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                            <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                            <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                            <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                            <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                            <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                        </select>
                    </div>
                    <div class="form-group" id="edit_reporter_ladderize_course_div" style="display: none;">
                        <label for="edit_manual_reporter_ladderize_course_select">Ladderize Course</label>
                        <select id="edit_manual_reporter_ladderize_course_select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                            <option value="">Select ladderize course</option>
                            <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                            <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                            <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                            <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                        </select>
                    </div>
                    <div class="form-group" id="edit_reporter_course_year_div" style="display: none;">
                        <label for="edit_manual_reporter_year">Year Level</label>
                        <div style="display: flex; gap: 8px;">
                            <select id="edit_manual_reporter_year" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                <option value="">Select year</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="edit_reporter_mobile_div" style="display: none;">
                        <label for="edit_manual_reporter_mobile">Mobile Number:</label>
                        <input type="tel" id="edit_manual_reporter_mobile" placeholder="+63 912 345 6789">
                    </div>
                    <div class="form-group" id="edit_reporter_custom_type_div" style="display: none;">
                        <label for="edit_custom_reporter_type">Please specify reporter type:</label>
                        <input type="text" name="custom_reporter_type" id="edit_custom_reporter_type" placeholder="Specify the reporter type...">
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                        <button type="button" class="button btn-primary" onclick="addEditManualReporter()">Add Reporter</button>
                        <button type="button" class="button btn-secondary" onclick="clearEditManualReporterForm()">Clear</button>
                    </div>
                </div>

                <!-- Selected Reporters List -->
                <div id="edit_selected_reporters" style="display: none; margin-top: 16px;">
                    <h5 style="margin-bottom: 8px; color: #374151;">Selected Reporters:</h5>
                    <div id="edit_reporters_list" style="border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; background: #f9fafb;">
                        <!-- Reporters will be added here -->
                    </div>
                </div>

                <div style="display: flex; gap: 8px; margin-top: 20px;">
                    <button type="submit" class="button btn-primary">Add Reporters</button>
                    <button type="button" class="button btn-secondary" onclick="closeModal('edit_reporter')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Reported Persons Modal -->
    <div class="modal" id="edit_reported">
        <div class="modal-content">
            <div class="modal-header">Add Involved Parties</div>
            <form method="post" onsubmit="prepareEditReportedPersonsSubmit(event)">
                <input type="hidden" name="action" value="update_reported_persons">
                <input type="hidden" name="selected_reported_persons" id="edit_selected_reported_persons_input" value="">
                
                <div class="form-group">
                    <label>Person Type:</label>
                    <select name="person_type" id="edit_person_type" required onchange="handleEditPersonTypeChange()">
                        <option value="">-- Select type --</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="course">Course</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Custom Reported Person Type Field -->
                <div class="form-group" id="edit_custom_reported_div" style="display: none;">
                    <label for="edit_custom_reported_type">Please specify type:</label>
                    <input type="text" name="custom_reported_type" id="edit_custom_reported_type" placeholder="Specify the type...">
                </div>

                <!-- Reported Person Search Section -->
                <div id="edit_reported_search_section" style="display: none;">
                    <div class="form-group">
                        <label>Find Reported Person by Name or ID</label>
                        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                            <input type="text" id="edit_reported_search" placeholder="Search by name or ID..." style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                            <button type="button" class="button btn-secondary" style="padding: 8px 12px;" onclick="searchEditReportedPersons()">Search</button>
                        </div>
                        <div id="edit_reported_search_results" style="display: none; border: 1px solid #e5e7eb; border-radius: 4px; max-height: 200px; overflow-y: auto; background: #f9fafb;">
                        </div>
                        <small style="color: #6b7280; display: block; margin-top: 4px;">Search for existing users above, or manually add reported persons below.</small>
                    </div>
                </div>

                <!-- Manual Reported Person Form (Always Visible) -->
                <div id="edit_manual_reported_form" style="display: none; margin-top: 16px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 4px; background: #f9fafb;">
                    <h5 style="margin-bottom: 12px; color: #374151;">Add Reported Person Manually</h5>
                    <div class="form-group" id="edit_person_name_div" style="display: none;">
                        <label for="edit_manual_reported_name">Full Name:</label>
                        <input type="text" id="edit_manual_reported_name" placeholder="Enter full name">
                    </div>
                    <div class="form-group" id="edit_person_id_div" style="display: none;">
                        <label for="edit_manual_reported_id">ID:</label>
                        <input type="text" id="edit_manual_reported_id" placeholder="ID">
                    </div>
                    <div class="form-group" id="edit_person_course_div" style="display: none;">
                        <label for="edit_manual_reported_course_select">Course</label>
                        <select id="edit_manual_reported_course_select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                            <option value="">Select course</option>
                            <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                            <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                            <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                            <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                            <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                            <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                            <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                        </select>
                    </div>
                    <div class="form-group" id="edit_person_ladderize_course_div" style="display: none;">
                        <label for="edit_manual_reported_ladderize_course_select">Ladderize Course</label>
                        <select id="edit_manual_reported_ladderize_course_select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                            <option value="">Select ladderize course</option>
                            <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                            <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                            <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                            <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                        </select>
                    </div>
                    <div class="form-group" id="edit_person_course_year_div" style="display: none;">
                        <label for="edit_manual_reported_year">Year Level</label>
                        <div style="display: flex; gap: 8px;">
                            <select id="edit_manual_reported_year" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                <option value="">Select year</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="edit_person_mobile_div" style="display: none;">
                        <label for="edit_manual_reported_mobile">Mobile Number:</label>
                        <input type="tel" id="edit_manual_reported_mobile" placeholder="+63 912 345 6789">
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                        <button type="button" class="button btn-primary" onclick="addEditManualReportedPerson()">Add Person</button>
                        <button type="button" class="button btn-secondary" onclick="clearEditManualReportedForm()">Clear</button>
                    </div>
                </div>

                <!-- Selected Reported Persons List -->
                <div id="edit_selected_reported" style="display: none; margin-top: 12px;">
                    <h5 style="margin-bottom: 8px; color: #374151;">Selected Reported Persons:</h5>
                    <div id="edit_reported_list" style="border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; background: #f9fafb;">
                        <!-- Reported persons will be added here -->
                    </div>
                </div>

                <div style="display: flex; gap: 8px; margin-top: 20px;">
                    <button type="submit" class="button btn-primary">Add Involved Parties</button>
                    <button type="button" class="button btn-secondary" onclick="closeModal('edit_reported')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Single Reporter Modal -->
    <div class="modal" id="edit_single_reporter">
        <div class="modal-content">
            <div class="modal-header">Edit Reporter</div>
            <form method="post">
                <input type="hidden" name="action" value="update_single_reporter">
                <input type="hidden" name="reporter_id" id="edit_single_reporter_id">
                
                <div class="form-group">
                    <label>Reporter Type:</label>
                    <select name="reporter_type" id="edit_single_reporter_type" required>
                        <option value="">-- Select reporter type --</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="staff">Staff</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Full Name:</label>
                    <input type="text" name="reporter_name" id="edit_single_reporter_name" required>
                </div>
                
                <div class="form-group">
                    <label>ID (Optional):</label>
                    <input type="text" name="reporter_id_field" id="edit_single_reporter_id_field">
                </div>
                
                <!-- Course/Program Selection for Reporter -->
                <div id="edit_single_reporter_course_section">
                    <div class="form-group">
                        <label>Course/Program:</label>
                        <select name="reporter_course_main" id="edit_single_reporter_course_main" required onchange="handleEditSingleReporterCourseChange()">
                            <option value="">Select course</option>
                            <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                            <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                            <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                            <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                            <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                            <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                            <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                        </select>
                    </div>
                    
                    <!-- Student-specific fields -->
                    <div id="edit_single_reporter_student_fields" style="display: none;">
                        <div class="form-group">
                            <label>Ladderize Course:</label>
                            <select name="reporter_ladderize_course" id="edit_single_reporter_ladderize_course">
                                <option value="">Select ladderize course</option>
                                <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year Level:</label>
                            <select name="reporter_year_level" id="edit_single_reporter_year_level">
                                <option value="">Select year</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Hidden field to store combined course info -->
                    <input type="hidden" name="reporter_course" id="edit_single_reporter_course">
                </div>
                
                <div class="form-group">
                    <label>Mobile Number (Optional):</label>
                    <input type="text" name="mobile_number" id="edit_single_reporter_mobile">
                </div>
                
                <div style="display: flex; gap: 8px; margin-top: 20px;">
                    <button type="submit" class="button btn-primary">Update Reporter</button>
                    <button type="button" class="button btn-secondary" onclick="closeModal('edit_single_reporter')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Single Reported Person Modal -->
    <div class="modal" id="edit_single_reported_person">
        <div class="modal-content">
            <div class="modal-header">Edit Involved Party</div>
            <form method="post">
                <input type="hidden" name="action" value="update_single_reported_person">
                <input type="hidden" name="person_id" id="edit_single_person_id">
                
                <div class="form-group">
                    <label>Person Type:</label>
                    <select name="person_type" id="edit_single_person_type" required>
                        <option value="">-- Select type --</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="course">Course</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Full Name:</label>
                    <input type="text" name="person_name" id="edit_single_person_name" required>
                </div>
                
                <div class="form-group">
                    <label>ID (Optional):</label>
                    <input type="text" name="person_id_field" id="edit_single_person_id_field">
                </div>
                
                <!-- Course/Program Selection for Involved Party -->
                <div id="edit_single_person_course_section">
                    <div class="form-group">
                        <label>Course/Program:</label>
                        <select name="person_course_main" id="edit_single_person_course_main" required onchange="handleEditSinglePersonCourseChange()">
                            <option value="">Select course</option>
                            <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                            <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                            <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                            <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                            <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                            <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                            <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                        </select>
                    </div>
                    
                    <!-- Student-specific fields -->
                    <div id="edit_single_person_student_fields" style="display: none;">
                        <div class="form-group">
                            <label>Ladderize Course:</label>
                            <select name="person_ladderize_course" id="edit_single_person_ladderize_course">
                                <option value="">Select ladderize course</option>
                                <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year Level:</label>
                            <select name="person_year_level" id="edit_single_person_year_level">
                                <option value="">Select year</option>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Hidden field to store combined course info -->
                    <input type="hidden" name="person_course" id="edit_single_person_course">
                </div>
                
                <div style="display: flex; gap: 8px; margin-top: 20px;">
                    <button type="submit" class="button btn-primary">Update Involved Party</button>
                    <button type="button" class="button btn-secondary" onclick="closeModal('edit_single_reported_person')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Case Description Modal -->
    <div class="modal" id="edit_description">
        <div class="modal-content">
            <div class="modal-header">Edit Case Description</div>
            <form method="post">
                <input type="hidden" name="action" value="update_description">
                <div class="form-group">
                    <label>Case Description:</label>
                    <textarea name="incident_description" rows="6" required><?php echo htmlspecialchars($case['incident_description'] ?? ''); ?></textarea>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="button btn-primary">Update Description</button>
                    <button type="button" class="button btn-secondary" onclick="closeModal('edit_description')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Sessions Modal -->
    <div class="modal" id="edit_sessions">
        <div class="modal-content">
            <div class="modal-header">Edit Pending Sessions</div>
            <?php
                $stmt = $pdo->prepare("SELECT * FROM guidance_sessions WHERE case_id = ? AND attendance_status = 'pending' ORDER BY session_date DESC");
                $stmt->execute([$caseId]);
                $pendingSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (!empty($pendingSessions)): ?>
                <div style="max-height: 400px; overflow-y: auto; margin-bottom: 16px;">
                <?php foreach ($pendingSessions as $session): ?>
                    <?php
                        // Parse session details from notes
                        $mode = 'Face-to-Face';
                        $location = '';
                        if (preg_match('/Mode:\s*([^.]+)/i', $session['notes'], $m)) $mode = trim($m[1]);
                        if (preg_match('/(Venue|Link):\s*([^.\n]+)/i', $session['notes'], $m)) $location = trim($m[2]);
                        
                        // Parse date and time
                        $date = '';
                        $time = '';
                        if (!empty($session['session_time'])) {
                            $date = $session['session_date'];
                            $time = substr($session['session_time'], 0, 5);
                        } else {
                            $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $session['session_date']);
                            if (!$dateObj) {
                                try {
                                    $dateObj = new DateTime($session['session_date']);
                                } catch (Exception $e) {
                                    $dateObj = null;
                                }
                            }
                            $date = $dateObj ? $dateObj->format('Y-m-d') : '';
                            $time = $dateObj ? $dateObj->format('H:i') : '';
                        }
                    ?>
                    <form method="POST" style="margin-bottom: 16px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; background: #f9fafb;">
                        <input type="hidden" name="action" value="edit_session">
                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div class="form-group">
                                <label for="session_date_<?= $session['id'] ?>">Date</label>
                                <input type="date" name="session_date" id="session_date_<?= $session['id'] ?>" value="<?= htmlspecialchars($date) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="session_time_<?= $session['id'] ?>">Time</label>
                                <input type="time" name="session_time" id="session_time_<?= $session['id'] ?>" value="<?= htmlspecialchars($time) ?>" required min="08:00" max="17:00">
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label for="session_mode_<?= $session['id'] ?>">Session Mode</label>
                            <select name="session_mode" id="session_mode_<?= $session['id'] ?>" required onchange="updateLocationLabel('<?= $session['id'] ?>')">
                                <option value="Face-to-Face" <?= $mode === 'Face-to-Face' ? 'selected' : '' ?>>Face-to-Face</option>
                                <option value="Online" <?= $mode === 'Online' ? 'selected' : '' ?>>Online</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label for="location_<?= $session['id'] ?>" id="location_label_<?= $session['id'] ?>"><?= ($mode === 'Online' ? 'Meeting Link/Platform:' : 'Venue/Location:') ?></label>
                            <input type="text" name="location_link" id="location_<?= $session['id'] ?>" value="<?= htmlspecialchars($location) ?>" placeholder="<?= ($mode === 'Online' ? 'e.g., Zoom Link or Platform' : 'e.g., Guidance Office Room 101') ?>">
                        </div>
                        
                        <button type="submit" class="button btn-primary" style="width: 100%; padding: 8px;">Save Changes</button>
                    </form>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 16px;">No pending sessions to edit. Use the "Log Session" button to add new sessions.</p>
            <?php endif; ?>
            
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="button btn-secondary" onclick="closeModal('edit_sessions')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Documents Modal -->
    <div class="modal" id="edit_documents">
        <div class="modal-content">
            <div class="modal-header">Edit Supporting Documents</div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_document">
                <div class="form-group">
                    <label>Upload New Document:</label>
                    <input type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.png,.txt" required>
                    <small style="color: #6b7280;">Max 5MB. Accepted: PDF, Word, images, text files</small>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="button btn-primary">Upload Document</button>
                    <button type="button" class="button btn-secondary" onclick="closeModal('edit_documents')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Reporters Modal (for managing existing reporters) -->
    <div class="modal" id="manage_reporters">
        <div class="modal-content">
            <div class="modal-header">Manage Reporters</div>
            <div style="margin-bottom: 16px; color: #6b7280; font-size: 14px;">
                Remove existing reporters. Use the "Add" button to add new reporters. Edit reporters directly from the main case details.
            </div>
            <div id="manage_reporters_list">
                <?php
                $stmt = $pdo->prepare("SELECT * FROM case_reporters WHERE case_id = ? ORDER BY id");
                $stmt->execute([$caseId]);
                $existingReporters = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($existingReporters)):
                    foreach ($existingReporters as $reporter):
                ?>
                <div style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 12px; background: #f9fafb;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <strong><?php echo htmlspecialchars($reporter['reporter_name']); ?></strong>
                        <button type="button" class="btn btn-danger" style="font-size: 11px; padding: 2px 6px;" onclick="removeReporter(<?php echo $reporter['id']; ?>)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                    <div style="color: #6b7280; font-size: 13px;">
                        Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $reporter['reporter_type']))); ?><br>
                        <?php if ($reporter['reporter_id']): ?>ID: <?php echo htmlspecialchars($reporter['reporter_id']); ?><br><?php endif; ?>
                        <?php if ($reporter['reporter_course']): ?>Course: <?php echo htmlspecialchars($reporter['reporter_course']); ?><br><?php endif; ?>
                        <?php if ($reporter['mobile_number']): ?>📱 <?php echo htmlspecialchars($reporter['mobile_number']); ?><?php endif; ?>
                    </div>
                </div>
                <?php
                    endforeach;
                else:
                ?>
                <p style="color: #6b7280; text-align: center; padding: 24px;">No reporters found.</p>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="button btn-secondary" onclick="closeModal('manage_reporters')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Reported Persons Modal (for managing existing involved parties) -->
    <div class="modal" id="manage_reported">
        <div class="modal-content">
            <div class="modal-header">Manage Involved Parties</div>
            <div style="margin-bottom: 16px; color: #6b7280; font-size: 14px;">
                Remove existing involved parties. Use the "Add" button to add new involved parties. Edit involved parties directly from the main case details.
            </div>
            <div id="manage_reported_list">
                <?php
                $stmt = $pdo->prepare("SELECT * FROM case_reported_persons WHERE case_id = ? ORDER BY id");
                $stmt->execute([$caseId]);
                $existingPersons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($existingPersons)):
                    foreach ($existingPersons as $person):
                ?>
                <div style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 12px; background: #f9fafb;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <strong><?php echo htmlspecialchars($person['person_name']); ?></strong>
                        <button type="button" class="btn btn-danger" style="font-size: 11px; padding: 2px 6px;" onclick="removeReportedPerson(<?php echo $person['id']; ?>)">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                    <div style="color: #6b7280; font-size: 13px;">
                        Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $person['person_type']))); ?><br>
                        <?php if ($person['person_id']): ?>ID: <?php echo htmlspecialchars($person['person_id']); ?><br><?php endif; ?>
                        <?php if ($person['person_course']): ?>Course: <?php echo htmlspecialchars($person['person_course']); ?><?php endif; ?>
                    </div>
                </div>
                <?php
                    endforeach;
                else:
                ?>
                <p style="color: #6b7280; text-align: center; padding: 24px;">No involved parties found.</p>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="button btn-secondary" onclick="closeModal('manage_reported')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Participants Modal -->
    <div class="modal" id="edit_participants">
        <div class="modal-content">
            <div class="modal-header">Edit Case Participants</div>
            <form method="post">
                <input type="hidden" name="action" value="update_participants">
                <div class="form-group">
                    <label for="participants">Participants <span style="color: #dc2626;">*</span></label>
                    <select name="participants" id="participants" required>
                        <option value="STUDENT ONLY" <?php echo ($case['participants'] ?? 'STUDENT ONLY') === 'STUDENT ONLY' ? 'selected' : ''; ?>>STUDENT ONLY</option>
                        <option value="TEACHER ONLY" <?php echo ($case['participants'] ?? 'STUDENT ONLY') === 'TEACHER ONLY' ? 'selected' : ''; ?>>TEACHER ONLY</option>
                        <option value="TEACHER AND STUDENT" <?php echo ($case['participants'] ?? 'STUDENT ONLY') === 'TEACHER AND STUDENT' ? 'selected' : ''; ?>>TEACHER AND STUDENT</option>
                        <option value="Student and Parent/Guardian" <?php echo ($case['participants'] ?? 'STUDENT ONLY') === 'Student and Parent/Guardian' ? 'selected' : ''; ?>>Student and Parent/Guardian</option>
                    </select>
                    <small style="color: #6b7280;">Select who should participate in counseling sessions for this case.</small>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="button btn-primary">Update Participants</button>
                    <button type="button" class="button btn-secondary" onclick="closeModal('edit_participants')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const caseId = <?php echo $caseId; ?>;
        
        function openModal(modalId) {
            // Initialize arrays when opening edit modals
            if (modalId === 'edit_reporter') {
                editSelectedReporters = [];
                updateEditReportersDisplay();
                document.getElementById('edit_reporter_type').value = '';
                handleEditReporterTypeChange();
            } else if (modalId === 'edit_reported') {
                editSelectedReportedPersons = [];
                updateEditReportedPersonsDisplay();
                document.getElementById('edit_person_type').value = '';
                handleEditPersonTypeChange();
            }
            
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function updateLocationLabel(sessionId) {
            const modeSelect = document.getElementById('session_mode_' + sessionId);
            const locationLabel = document.getElementById('location_label_' + sessionId);
            const locationInput = document.getElementById('location_' + sessionId);
            const mode = modeSelect.value;

            if (mode === 'Online') {
                locationLabel.textContent = 'Meeting Link/Platform:';
                locationInput.placeholder = 'e.g., Zoom Link or Platform';
            } else {
                locationLabel.textContent = 'Venue/Location:';
                locationInput.placeholder = 'e.g., Guidance Office Room 101';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Reporter and Reported Person arrays for edit modals
        let editSelectedReporters = [];
        let editSelectedReportedPersons = [];

        function prepareEditReportersSubmit(event) {
            event.preventDefault();
            const input = document.getElementById('edit_selected_reporters_input');
            input.value = JSON.stringify(editSelectedReporters);
            event.target.submit();
        }

        function prepareEditReportedPersonsSubmit(event) {
            event.preventDefault();
            const input = document.getElementById('edit_selected_reported_persons_input');
            input.value = JSON.stringify(editSelectedReportedPersons);
            event.target.submit();
        }

        function handleEditReporterTypeChange() {
            const type = document.getElementById('edit_reporter_type').value;
            const searchSection = document.getElementById('edit_reporter_search_section');
            const manualForm = document.getElementById('edit_manual_reporter_form');
            const nameDiv = document.getElementById('edit_reporter_name_div');
            const idDiv = document.getElementById('edit_reporter_id_div');
            const courseDiv = document.getElementById('edit_reporter_course_div');
            const ladderizeCourseDiv = document.getElementById('edit_reporter_ladderize_course_div');
            const courseYearDiv = document.getElementById('edit_reporter_course_year_div');
            const mobileDiv = document.getElementById('edit_reporter_mobile_div');
            const customTypeDiv = document.getElementById('edit_reporter_custom_type_div');

            // Reset all
            searchSection.style.display = 'none';
            manualForm.style.display = 'none';
            nameDiv.style.display = 'none';
            idDiv.style.display = 'none';
            courseDiv.style.display = 'none';
            ladderizeCourseDiv.style.display = 'none';
            courseYearDiv.style.display = 'none';
            mobileDiv.style.display = 'none';
            customTypeDiv.style.display = 'none';

            if (type === 'student') {
                searchSection.style.display = 'block';
                manualForm.style.display = 'block';
                nameDiv.style.display = 'block';
                idDiv.style.display = 'block';
                courseDiv.style.display = 'block';
                ladderizeCourseDiv.style.display = 'block';
                courseYearDiv.style.display = 'block';
                document.getElementById('edit_reporter_id_div').querySelector('label').textContent = 'School ID:';
                document.getElementById('edit_manual_reporter_id').placeholder = 'Student ID';
            } else if (type === 'teacher') {
                searchSection.style.display = 'block';
                manualForm.style.display = 'block';
                nameDiv.style.display = 'block';
                idDiv.style.display = 'block';
                courseDiv.style.display = 'block';
                document.getElementById('edit_reporter_course_div').querySelector('label').textContent = 'Course / Department';
                document.getElementById('edit_reporter_id_div').querySelector('label').textContent = 'Teacher ID (Optional):';
                document.getElementById('edit_manual_reporter_id').placeholder = 'Teacher ID (optional)';
            } else if (type === 'parent_guardian') {
                manualForm.style.display = 'block';
                nameDiv.style.display = 'block';
                mobileDiv.style.display = 'block';
            } else if (type === 'course') {
                manualForm.style.display = 'block';
                courseDiv.style.display = 'block';
                courseYearDiv.style.display = 'block';
                document.getElementById('edit_reporter_course_div').querySelector('label').textContent = 'Course / Subject';
            } else if (type === 'other') {
                manualForm.style.display = 'block';
                nameDiv.style.display = 'block';
                customTypeDiv.style.display = 'block';
            }
        }

        function handleEditPersonTypeChange() {
            const reportedType = document.getElementById('edit_person_type').value;
            const customReportedDiv = document.getElementById('edit_custom_reported_div');
            const reportedSearchSection = document.getElementById('edit_reported_search_section');
            const manualReportedForm = document.getElementById('edit_manual_reported_form');
            const nameDiv = document.getElementById('edit_person_name_div');
            const idDiv = document.getElementById('edit_person_id_div');
            const courseDiv = document.getElementById('edit_person_course_div');
            const courseYearDiv = document.getElementById('edit_person_course_year_div');
            const mobileDiv = document.getElementById('edit_person_mobile_div');

            // Reset all displays
            customReportedDiv.style.display = 'none';
            reportedSearchSection.style.display = 'none';
            if (manualReportedForm) manualReportedForm.style.display = 'none';
            if (nameDiv) nameDiv.style.display = 'none';
            if (idDiv) idDiv.style.display = 'none';
            if (courseDiv) courseDiv.style.display = 'none';
            if (courseYearDiv) courseYearDiv.style.display = 'none';
            if (mobileDiv) mobileDiv.style.display = 'none';

            if (reportedType) {
                if (reportedType !== 'course') {
                    reportedSearchSection.style.display = 'block';
                }
                if (manualReportedForm) manualReportedForm.style.display = 'block';
                
                if (reportedType === 'student') {
                    if (nameDiv) nameDiv.style.display = 'block';
                    if (idDiv) idDiv.style.display = 'block';
                    if (courseDiv) courseDiv.style.display = 'block';
                    if (courseYearDiv) courseYearDiv.style.display = 'block';
                    document.getElementById('edit_person_ladderize_course_div').style.display = 'block';
                    document.getElementById('edit_person_course_div').querySelector('label').textContent = 'Course';
                    // Set ID label for student
                    const idLabel = document.querySelector('#edit_person_id_div label');
                    const idInput = document.getElementById('edit_manual_reported_id');
                    if (idLabel) idLabel.textContent = 'School ID:';
                    if (idInput) idInput.placeholder = 'Student ID';
                } else if (reportedType === 'teacher') {
                    if (nameDiv) nameDiv.style.display = 'block';
                    if (idDiv) idDiv.style.display = 'block';
                    if (courseDiv) courseDiv.style.display = 'block';
                    document.getElementById('edit_person_ladderize_course_div').style.display = 'none';
                    if (courseYearDiv) courseYearDiv.style.display = 'none';
                    document.getElementById('edit_person_course_div').querySelector('label').textContent = 'Course / Department';
                    // Set ID label for teacher
                    const idLabel = document.querySelector('#edit_person_id_div label');
                    const idInput = document.getElementById('edit_manual_reported_id');
                    if (idLabel) idLabel.textContent = 'Teacher ID (Optional):';
                    if (idInput) idInput.placeholder = 'Teacher ID (optional)';
                } else if (reportedType === 'course') {
                    if (manualReportedForm) manualReportedForm.style.display = 'block';
                    if (courseDiv) courseDiv.style.display = 'block';
                    if (courseYearDiv) courseYearDiv.style.display = 'block';
                    document.getElementById('edit_person_ladderize_course_div').style.display = 'none';
                    document.getElementById('edit_person_course_div').querySelector('label').textContent = 'Course / Subject';
                } else if (reportedType === 'other') {
                    if (nameDiv) nameDiv.style.display = 'block';
                    if (mobileDiv) mobileDiv.style.display = 'block';
                    customReportedDiv.style.display = 'block';
                }
            }

            const courseSelect = document.getElementById('edit_manual_reported_course_select');
            const ladderizeSelect = document.getElementById('edit_manual_reported_ladderize_course_select');
            if (courseSelect) {
                courseSelect.value = '';
                courseSelect.disabled = false;
            }
            if (ladderizeSelect) {
                ladderizeSelect.value = '';
                ladderizeSelect.disabled = false;
            }
            populateEditManualReportedYearOptions('');
        }

        function addEditManualReporter() {
            const reporterType = document.getElementById('edit_reporter_type').value;
            const name = document.getElementById('edit_manual_reporter_name').value.trim();
            const id = document.getElementById('edit_manual_reporter_id').value.trim();
            const courseSelect = document.getElementById('edit_manual_reporter_course_select');
            const ladderizeCourseSelect = document.getElementById('edit_manual_reporter_ladderize_course_select');
            const yearSelect = document.getElementById('edit_manual_reporter_year');
            const mobile = document.getElementById('edit_manual_reporter_mobile').value.trim();
            const customType = document.getElementById('edit_custom_reporter_type').value.trim();
            const courseValue = courseSelect ? courseSelect.value.trim() : '';
            const ladderizeValue = ladderizeCourseSelect ? ladderizeCourseSelect.value.trim() : '';
            const selectedCourse = courseValue || ladderizeValue;

            if (!reporterType) {
                alert('Please select a reporter type first.');
                return;
            }

            let reporter = {
                type: reporterType,
                name: '',
                id: '',
                course: '',
                mobile: ''
            };

            if (reporterType === 'student') {
                const selectedYear = yearSelect ? yearSelect.value : '';
                if (!selectedCourse || !selectedYear) {
                    alert('Please select a course and year.');
                    return;
                }
                if (!name) {
                    alert('Please enter a full name.');
                    return;
                }
                if (!id) {
                    alert('Please enter School ID.');
                    return;
                }
                reporter.name = name;
                reporter.id = id;
                reporter.course = selectedCourse + ' / ' + selectedYear;
            } else if (reporterType === 'course') {
                const selectedYear = yearSelect ? yearSelect.value : '';
                if (!courseValue || !selectedYear) {
                    alert('Please select a course and year.');
                    return;
                }
                reporter.name = courseValue + ' - ' + selectedYear;
                reporter.course = courseValue + ' / ' + selectedYear;
            } else if (reporterType === 'parent_guardian' || reporterType === 'other') {
                if (!name) {
                    alert('Please enter a full name.');
                    return;
                }
                if (!mobile) {
                    alert('Please enter a mobile number.');
                    return;
                }
                reporter.name = name;
                reporter.mobile = mobile;
                if (reporterType === 'other') {
                    reporter.course = customType || '';
                }
            } else {
                if (!name) {
                    alert('Please enter a full name.');
                    return;
                }
                if (!selectedCourse) {
                    alert('Please select a course.');
                    return;
                }
                reporter.name = name;
                reporter.id = id;
                reporter.course = selectedCourse;
            }

            editSelectedReporters.push(reporter);
            updateEditReportersDisplay();
            const selectedDiv = document.getElementById('edit_selected_reporters');
            if (selectedDiv && editSelectedReporters.length > 0) {
                selectedDiv.style.display = 'block';
            }
            clearEditManualReporterForm();
        }

        function updateEditReportersDisplay() {
            const listDiv = document.getElementById('edit_reporters_list');
            const selectedDiv = document.getElementById('edit_selected_reporters');
            
            if (editSelectedReporters.length === 0) {
                selectedDiv.style.display = 'none';
                return;
            }
            
            selectedDiv.style.display = 'block';
            let html = '';
            editSelectedReporters.forEach((reporter, index) => {
                html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 1px solid #e5e7eb;">';
                html += '<div>';
                html += '<strong>' + reporter.name + '</strong>';
                if (reporter.id) html += ' (ID: ' + reporter.id + ')';
                if (reporter.course) html += ' - ' + reporter.course;
                if (reporter.mobile) html += ' 📱 ' + reporter.mobile;
                html += ' <small style="color: #6b7280;">[' + reporter.type + ']</small>';
                html += '</div>';
                html += '<button type="button" class="button btn-danger" style="padding: 2px 6px; font-size: 12px;" onclick="removeEditReporter(' + index + ')">Remove</button>';
                html += '</div>';
            });
            listDiv.innerHTML = html;
        }

        function removeEditReporter(index) {
            editSelectedReporters.splice(index, 1);
            updateEditReportersDisplay();
        }

        function clearEditManualReporterForm() {
            document.getElementById('edit_manual_reporter_name').value = '';
            document.getElementById('edit_manual_reporter_id').value = '';
            document.getElementById('edit_manual_reporter_mobile').value = '';
            document.getElementById('edit_manual_reporter_course_select').value = '';
            document.getElementById('edit_manual_reporter_ladderize_course_select').value = '';
            document.getElementById('edit_manual_reporter_year').value = '';
            document.getElementById('edit_custom_reporter_type').value = '';
            
            const courseSelect = document.getElementById('edit_manual_reporter_course_select');
            const ladderSelect = document.getElementById('edit_manual_reporter_ladderize_course_select');
            if (courseSelect) courseSelect.disabled = false;
            if (ladderSelect) ladderSelect.disabled = false;
            
            populateEditManualReporterYearOptions('');
        }

        function populateEditManualReporterYearOptions(courseValue) {
            const yearSelect = document.getElementById('edit_manual_reporter_year');
            if (!yearSelect) return;
            const twoYearPrograms = ['Associate in Computer Technology', 'Associate in Business Knowledge', 'Associate in Hospitality Management', 'Associate in Tourism Management'];
            const years = twoYearPrograms.includes(courseValue) ? ['1st Year', '2nd Year'] : ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Graduate'];
            const currentValue = yearSelect.value;
            yearSelect.innerHTML = '<option value="">Select year</option>' + years.map(year => `\n                <option value="${year}"${currentValue === String(year) ? ' selected' : ''}>${year}</option>`).join('');
        }

        function addEditManualReportedPerson() {
            const reportedType = document.getElementById('edit_person_type').value;
            const name = document.getElementById('edit_manual_reported_name').value.trim();
            const id = document.getElementById('edit_manual_reported_id').value.trim();
            const courseSelect = document.getElementById('edit_manual_reported_course_select');
            const ladderizeCourseSelect = document.getElementById('edit_manual_reported_ladderize_course_select');
            const yearSelect = document.getElementById('edit_manual_reported_year');
            const mobile = document.getElementById('edit_manual_reported_mobile').value.trim();
            const customType = document.getElementById('edit_custom_reported_type').value.trim();
            const courseValue = courseSelect ? courseSelect.value.trim() : '';
            const ladderizeValue = ladderizeCourseSelect ? ladderizeCourseSelect.value.trim() : '';
            const selectedCourse = courseValue || ladderizeValue;

            if (!reportedType) {
                alert('Please select a reported person type first.');
                return;
            }

            let person = {
                type: reportedType,
                name: '',
                id: '',
                course: ''
            };

            if (reportedType === 'student') {
                const selectedYear = yearSelect ? yearSelect.value : '';
                if (!selectedCourse || !selectedYear) {
                    alert('Please select a course and year.');
                    return;
                }
                if (!name) {
                    alert('Please enter a full name.');
                    return;
                }
                if (!id) {
                    alert('Please enter School ID.');
                    return;
                }
                person.name = name;
                person.id = id;
                person.course = selectedCourse + ' / ' + selectedYear;
            } else if (reportedType === 'course') {
                const selectedYear = yearSelect ? yearSelect.value : '';
                if (!courseValue || !selectedYear) {
                    alert('Please select a course and year.');
                    return;
                }
                person.name = courseValue + ' - ' + selectedYear;
                person.course = courseValue + ' / ' + selectedYear;
            } else if (reportedType === 'other') {
                if (!name) {
                    alert('Please enter a full name.');
                    return;
                }
                if (!mobile) {
                    alert('Please enter a mobile number.');
                    return;
                }
                person.name = name;
                person.course = customType || '';
            } else {
                if (!name) {
                    alert('Please enter a full name.');
                    return;
                }
                if (!selectedCourse) {
                    alert('Please select a course.');
                    return;
                }
                person.name = name;
                person.id = id;
                person.course = selectedCourse;
            }

            editSelectedReportedPersons.push(person);
            updateEditReportedPersonsDisplay();
            clearEditManualReportedForm();
        }

        function updateEditReportedPersonsDisplay() {
            const listDiv = document.getElementById('edit_reported_list');
            const selectedDiv = document.getElementById('edit_selected_reported');
            
            if (editSelectedReportedPersons.length === 0) {
                selectedDiv.style.display = 'none';
                return;
            }
            
            selectedDiv.style.display = 'block';
            let html = '';
            editSelectedReportedPersons.forEach((person, index) => {
                html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 1px solid #e5e7eb;">';
                html += '<div>';
                html += '<strong>' + person.name + '</strong>';
                if (person.id) html += ' (ID: ' + person.id + ')';
                if (person.course) html += ' - ' + person.course;
                html += ' <small style="color: #6b7280;">[' + person.type + ']</small>';
                html += '</div>';
                html += '<button type="button" class="button btn-danger" style="padding: 2px 6px; font-size: 12px;" onclick="removeEditReportedPerson(' + index + ')">Remove</button>';
                html += '</div>';
            });
            listDiv.innerHTML = html;
        }

        function removeEditReportedPerson(index) {
            editSelectedReportedPersons.splice(index, 1);
            updateEditReportedPersonsDisplay();
        }

        function clearEditManualReportedForm() {
            document.getElementById('edit_manual_reported_name').value = '';
            document.getElementById('edit_manual_reported_id').value = '';
            document.getElementById('edit_manual_reported_mobile').value = '';
            document.getElementById('edit_manual_reported_course_select').value = '';
            document.getElementById('edit_manual_reported_ladderize_course_select').value = '';
            document.getElementById('edit_manual_reported_year').value = '';
            
            const courseSelect = document.getElementById('edit_manual_reported_course_select');
            const ladderSelect = document.getElementById('edit_manual_reported_ladderize_course_select');
            if (courseSelect) courseSelect.disabled = false;
            if (ladderSelect) ladderSelect.disabled = false;
            
            populateEditManualReportedYearOptions('');
        }

        function populateEditManualReportedYearOptions(courseValue) {
            const yearSelect = document.getElementById('edit_manual_reported_year');
            if (!yearSelect) return;
            const twoYearPrograms = ['Associate in Computer Technology', 'Associate in Business Knowledge', 'Associate in Hospitality Management', 'Associate in Tourism Management'];
            const years = twoYearPrograms.includes(courseValue) ? ['1st Year', '2nd Year'] : ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Graduate'];
            const currentValue = yearSelect.value;
            yearSelect.innerHTML = '<option value="">Select year</option>' + years.map(year => `\n                <option value="${year}"${currentValue === String(year) ? ' selected' : ''}>${year}</option>`).join('');
        }

        function searchEditReporters() {
            const searchTerm = document.getElementById('edit_reporter_search').value.trim();
            if (!searchTerm || searchTerm.length < 2) {
                alert('Please enter at least 2 characters to search');
                return;
            }

            const resultsDiv = document.getElementById('edit_reporter_search_results');
            resultsDiv.innerHTML = '<div style="padding: 8px; color: #6b7280; text-align: center;">Searching...</div>';
            resultsDiv.style.display = 'block';
            
            fetch('./search_students.php?q=' + encodeURIComponent(searchTerm))
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.results.length > 0) {
                            let html = '<div style="padding: 8px; border-radius: 4px;">';
                            data.results.forEach(person => {
                                const roleDisplay = person.role === 'student' ? '👤 Student' : '👨‍🏫 Teacher';
                                const idValue = person.role === 'student' ? person.student_id : person.employee_id;
                                const courseValue = person.course_year;
                                
                                html += '<div style="padding: 8px; border-bottom: 1px solid #d1d5db; cursor: pointer;" onmouseover="this.style.background=\'#e5e7eb\'" onmouseout="this.style.background=\'\'" onclick="selectEditReporter(\'' + person.id + '\', \'' + person.full_name.replace(/'/g, "\\\\'") + '\', \'' + idValue.replace(/'/g, "\\\\'") + '\', \'' + (courseValue ? courseValue.replace(/'/g, "\\\\'") : '') + '\', \'' + person.role + '\')">';
                                html += '<strong>' + person.full_name + '</strong> <span style="color: #6b7280; font-size: 12px;">' + roleDisplay + '</span><br>';
                                html += '<small style="color: #6b7280;">ID: ' + idValue + '</small>';
                                if (courseValue) {
                                    html += '<br><small style="color: #6b7280;">Course: ' + courseValue + '</small>';
                                }
                                html += '</div>';
                            });
                            html += '</div>';
                            resultsDiv.innerHTML = html;
                        } else {
                            resultsDiv.innerHTML = '<div style="padding: 8px; color: #6b7280; text-align: center;">No results found</div>';
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        resultsDiv.innerHTML = '<div style="padding: 8px; color: #dc2626; text-align: center;">Error parsing results</div>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    resultsDiv.innerHTML = '<div style="padding: 8px; color: #dc2626; text-align: center;">Error searching</div>';
                });
        }

        function selectEditReporter(id, name, idValue, courseValue, role) {
            const reporterType = document.getElementById('edit_reporter_type').value;
            
            editSelectedReporters.push({
                type: reporterType,
                name: name,
                id: idValue,
                course: courseValue
            });
            
            updateEditReportersDisplay();
            document.getElementById('edit_reporter_search').value = '';
            document.getElementById('edit_reporter_search_results').style.display = 'none';
        }

        function searchEditReportedPersons() {
            const searchTerm = document.getElementById('edit_reported_search').value.trim();
            if (!searchTerm || searchTerm.length < 2) {
                alert('Please enter at least 2 characters to search');
                return;
            }

            const resultsDiv = document.getElementById('edit_reported_search_results');
            resultsDiv.innerHTML = '<div style="padding: 8px; color: #6b7280; text-align: center;">Searching...</div>';
            resultsDiv.style.display = 'block';
            
            fetch('./search_students.php?q=' + encodeURIComponent(searchTerm))
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.results.length > 0) {
                            let html = '<div style="padding: 8px; border-radius: 4px;">';
                            data.results.forEach(person => {
                                const roleDisplay = person.role === 'student' ? '👤 Student' : '👨‍🏫 Teacher';
                                const idValue = person.role === 'student' ? person.student_id : person.employee_id;
                                const courseValue = person.course_year;
                                
                                html += '<div style="padding: 8px; border-bottom: 1px solid #d1d5db; cursor: pointer;" onmouseover="this.style.background=\'#e5e7eb\'" onmouseout="this.style.background=\'\'" onclick="selectEditReportedPerson(\'' + person.id + '\', \'' + person.full_name.replace(/'/g, "\\\\'") + '\', \'' + idValue.replace(/'/g, "\\\\'") + '\', \'' + (courseValue ? courseValue.replace(/'/g, "\\\\'") : '') + '\', \'' + person.role + '\')">';
                                html += '<strong>' + person.full_name + '</strong> <span style="color: #6b7280; font-size: 12px;">' + roleDisplay + '</span><br>';
                                html += '<small style="color: #6b7280;">ID: ' + idValue + '</small>';
                                if (courseValue) {
                                    html += '<br><small style="color: #6b7280;">Course: ' + courseValue + '</small>';
                                }
                                html += '</div>';
                            });
                            html += '</div>';
                            resultsDiv.innerHTML = html;
                        } else {
                            resultsDiv.innerHTML = '<div style="padding: 8px; color: #6b7280; text-align: center;">No results found</div>';
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        resultsDiv.innerHTML = '<div style="padding: 8px; color: #dc2626; text-align: center;">Error parsing results</div>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    resultsDiv.innerHTML = '<div style="padding: 8px; color: #dc2626; text-align: center;">Error searching</div>';
                });
        }

        function selectEditReportedPerson(id, name, idValue, courseValue, role) {
            const reportedType = document.getElementById('edit_person_type').value;
            
            editSelectedReportedPersons.push({
                type: reportedType,
                name: name,
                id: idValue,
                course: courseValue
            });
            
            updateEditReportedPersonsDisplay();
            document.getElementById('edit_reported_search').value = '';
            document.getElementById('edit_reported_search_results').style.display = 'none';
        }

        function removeReporter(reporterId) {
            if (confirm('Are you sure you want to remove this reporter?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'remove_reporter';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'reporter_id';
                idInput.value = reporterId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function normalizeYearValue(value) {
            const trimmed = value.trim();
            const numericMatch = trimmed.match(/^([1-4])$/);
            if (numericMatch) {
                const n = parseInt(numericMatch[1], 10);
                return n === 1 ? '1st Year' : n === 2 ? '2nd Year' : n === 3 ? '3rd Year' : '4th Year';
            }
            return trimmed;
        }

        function isLadderizeCourse(courseString) {
            const normalized = courseString.trim();
            const ladderOptions = [
                'Associate in Computer Technology',
                'Associate in Business Knowledge',
                'Associate in Hospitality Management',
                'Associate in Tourism Management'
            ];
            return ladderOptions.includes(normalized);
        }

        function parseStudentCourseString(courseString) {
            const result = { mainCourse: '', ladderize: '', yearLevel: '' };
            const trimmed = courseString.trim();
            if (!trimmed) {
                return result;
            }

            if (trimmed.includes(' - ') && trimmed.includes('(')) {
                const parts = trimmed.split(' - ');
                result.mainCourse = parts[0].trim();
                const remaining = parts[1].split(' (');
                result.ladderize = remaining[0].trim();
                result.yearLevel = remaining[1] ? remaining[1].replace(')', '').trim() : '';
            } else if (trimmed.includes(' / ')) {
                const [left, right] = trimmed.split(' / ').map(s => s.trim());
                if (isLadderizeCourse(left)) {
                    result.ladderize = left;
                } else {
                    result.mainCourse = left;
                }
                result.yearLevel = normalizeYearValue(right || '');
            } else {
                const yearMatch = trimmed.match(/(1st Year|2nd Year|3rd Year|4th Year|Graduate|[1-4])$/i);
                if (yearMatch) {
                    result.yearLevel = normalizeYearValue(yearMatch[1]);
                    let coursePart = trimmed.slice(0, trimmed.length - yearMatch[0].length).trim();
                    coursePart = coursePart.replace(/[\/-]$/, '').trim();
                    if (isLadderizeCourse(coursePart)) {
                        result.ladderize = coursePart;
                    } else {
                        result.mainCourse = coursePart;
                    }
                } else {
                    if (isLadderizeCourse(trimmed)) {
                        result.ladderize = trimmed;
                    } else {
                        result.mainCourse = trimmed;
                    }
                }
            }

            if (!result.ladderize && isLadderizeCourse(result.mainCourse)) {
                result.ladderize = result.mainCourse;
                result.mainCourse = '';
            }
            return result;
        }

        // Populate course fields for edit single reporter
        function populateEditSingleReporterCourse(courseString) {
            const type = document.getElementById('edit_single_reporter_type').value;
            
            if (type === 'student') {
                const parsed = parseStudentCourseString(courseString);
                document.getElementById('edit_single_reporter_course_main').value = parsed.mainCourse;
                document.getElementById('edit_single_reporter_ladderize_course').value = parsed.ladderize;
                document.getElementById('edit_single_reporter_year_level').value = parsed.yearLevel;
                
                // Apply course/ladderize selection logic
                handleEditSingleReporterCourseSelection();
            } else {
                document.getElementById('edit_single_reporter_course_main').value = courseString;
                document.getElementById('edit_single_reporter_ladderize_course').value = '';
                document.getElementById('edit_single_reporter_year_level').value = '';
            }
            
            updateEditSingleReporterCourseValue();
            handleEditSingleReporterCourseChange();
        }

        // Populate course fields for edit single person
        function populateEditSinglePersonCourse(courseString) {
            const type = document.getElementById('edit_single_person_type').value;
            
            if (type === 'student') {
                const parsed = parseStudentCourseString(courseString);
                document.getElementById('edit_single_person_course_main').value = parsed.mainCourse;
                document.getElementById('edit_single_person_ladderize_course').value = parsed.ladderize;
                document.getElementById('edit_single_person_year_level').value = parsed.yearLevel;
                
                // Apply course/ladderize selection logic
                handleEditSinglePersonCourseSelection();
            } else {
                document.getElementById('edit_single_person_course_main').value = courseString;
                document.getElementById('edit_single_person_ladderize_course').value = '';
                document.getElementById('edit_single_person_year_level').value = '';
            }
            
            updateEditSinglePersonCourseValue();
            handleEditSinglePersonCourseChange();
        }

        function editReporterModal(reporterId) {
            // Fetch reporter data via AJAX
            fetch('case_details.php?id=' + caseId + '&ajax=get_reporter&reporter_id=' + reporterId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the form
                        document.getElementById('edit_single_reporter_id').value = data.reporter.id;
                        document.getElementById('edit_single_reporter_type').value = (data.reporter.reporter_type || '').toString().toLowerCase();
                        document.getElementById('edit_single_reporter_name').value = data.reporter.reporter_name || '';
                        document.getElementById('edit_single_reporter_id_field').value = data.reporter.reporter_id || '';
                        
                        // Parse and populate course data
                        populateEditSingleReporterCourse(data.reporter.reporter_course || '');
                        document.getElementById('edit_single_reporter_course').value = data.reporter.reporter_course || '';
                        
                        document.getElementById('edit_single_reporter_mobile').value = data.reporter.mobile_number || '';
                        
                        // Show the modal
                        openModal('edit_single_reporter');
                    } else {
                        alert('Error loading reporter data: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error loading reporter data');
                    console.error('Error:', error);
                });
        }

        function editReportedPersonModal(personId) {
            // Fetch person data via AJAX
            fetch('case_details.php?id=' + caseId + '&ajax=get_reported_person&person_id=' + personId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the form
                        document.getElementById('edit_single_person_id').value = data.person.id;
                        document.getElementById('edit_single_person_type').value = (data.person.person_type || '').toString().toLowerCase();
                        document.getElementById('edit_single_person_name').value = data.person.person_name || '';
                        document.getElementById('edit_single_person_id_field').value = data.person.person_id || '';
                        
                        // Parse and populate course data
                        populateEditSinglePersonCourse(data.person.person_course || '');
                        document.getElementById('edit_single_person_course').value = data.person.person_course || '';
                        
                        // Show the modal
                        openModal('edit_single_reported_person');
                    } else {
                        alert('Error loading person data: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error loading person data');
                    console.error('Error:', error);
                });
        }

        // Handle course selection changes for edit single reporter
        function handleEditSingleReporterCourseChange() {
            const type = document.getElementById('edit_single_reporter_type').value;
            const studentFields = document.getElementById('edit_single_reporter_student_fields');

            studentFields.style.display = type === 'student' ? 'block' : 'none';
            updateEditSingleReporterCourseValue();
        }

        // Handle course selection changes for edit single reporter (course/ladderize logic)
        function handleEditSingleReporterCourseSelection() {
            const courseSelect = document.getElementById('edit_single_reporter_course_main');
            const ladderizeSelect = document.getElementById('edit_single_reporter_ladderize_course');
            const courseValue = courseSelect.value;
            const ladderizeValue = ladderizeSelect.value;

            if (courseValue) {
                ladderizeSelect.value = '';
                ladderizeSelect.disabled = true;
            } else {
                ladderizeSelect.disabled = false;
            }
            if (ladderizeValue) {
                courseSelect.value = '';
                courseSelect.disabled = true;
            } else {
                courseSelect.disabled = false;
            }

            updateEditSingleReporterCourseValue();
        }

        // Handle course selection changes for edit single person
        function handleEditSinglePersonCourseChange() {
            const type = document.getElementById('edit_single_person_type').value;
            const studentFields = document.getElementById('edit_single_person_student_fields');

            studentFields.style.display = type === 'student' ? 'block' : 'none';
            updateEditSinglePersonCourseValue();
        }

        // Handle course selection changes for edit single person (course/ladderize logic)
        function handleEditSinglePersonCourseSelection() {
            const courseSelect = document.getElementById('edit_single_person_course_main');
            const ladderizeSelect = document.getElementById('edit_single_person_ladderize_course');
            const courseValue = courseSelect.value;
            const ladderizeValue = ladderizeSelect.value;

            if (courseValue) {
                ladderizeSelect.value = '';
                ladderizeSelect.disabled = true;
            } else {
                ladderizeSelect.disabled = false;
            }
            if (ladderizeValue) {
                courseSelect.value = '';
                courseSelect.disabled = true;
            } else {
                courseSelect.disabled = false;
            }

            updateEditSinglePersonCourseValue();
        }

        // Update combined course value for reporter
        function updateEditSingleReporterCourseValue() {
            const type = document.getElementById('edit_single_reporter_type').value;
            let courseValue = '';
            
            if (type === 'student') {
                const mainCourse = document.getElementById('edit_single_reporter_course_main').value;
                const ladderize = document.getElementById('edit_single_reporter_ladderize_course').value;
                const yearLevel = document.getElementById('edit_single_reporter_year_level').value;
                
                courseValue = mainCourse;
                if (ladderize) courseValue += ' - ' + ladderize;
                if (yearLevel) courseValue += ' (' + yearLevel + ')';
            } else {
                courseValue = document.getElementById('edit_single_reporter_course_main').value;
            }
            
            document.getElementById('edit_single_reporter_course').value = courseValue;
        }

        // Update combined course value for person
        function updateEditSinglePersonCourseValue() {
            const type = document.getElementById('edit_single_person_type').value;
            let courseValue = '';
            
            if (type === 'student') {
                const mainCourse = document.getElementById('edit_single_person_course_main').value;
                const ladderize = document.getElementById('edit_single_person_ladderize_course').value;
                const yearLevel = document.getElementById('edit_single_person_year_level').value;
                
                courseValue = mainCourse;
                if (ladderize) courseValue += ' - ' + ladderize;
                if (yearLevel) courseValue += ' (' + yearLevel + ')';
            } else {
                courseValue = document.getElementById('edit_single_person_course_main').value;
            }
            
            document.getElementById('edit_single_person_course').value = courseValue;
        }

        // Initialize event listeners for course selection
        document.getElementById('edit_manual_reporter_course_select').addEventListener('change', function () {
            const ladderSelect = document.getElementById('edit_manual_reporter_ladderize_course_select');
            if (this.value) {
                ladderSelect.value = '';
                ladderSelect.disabled = true;
                populateEditManualReporterYearOptions(this.value);
            } else {
                ladderSelect.disabled = false;
                populateEditManualReporterYearOptions('');
            }
        });

        document.getElementById('edit_manual_reporter_ladderize_course_select').addEventListener('change', function () {
            const courseSelect = document.getElementById('edit_manual_reporter_course_select');
            if (this.value) {
                courseSelect.value = '';
                courseSelect.disabled = true;
                populateEditManualReporterYearOptions(this.value);
            } else {
                courseSelect.disabled = false;
                populateEditManualReporterYearOptions('');
            }
        });

        document.getElementById('edit_manual_reported_course_select').addEventListener('change', function () {
            const ladderSelect = document.getElementById('edit_manual_reported_ladderize_course_select');
            if (this.value) {
                ladderSelect.value = '';
                ladderSelect.disabled = true;
                populateEditManualReportedYearOptions(this.value);
            } else {
                ladderSelect.disabled = false;
                populateEditManualReportedYearOptions('');
            }
        });

        document.getElementById('edit_manual_reported_ladderize_course_select').addEventListener('change', function () {
            const courseSelect = document.getElementById('edit_manual_reported_course_select');
            if (this.value) {
                courseSelect.value = '';
                courseSelect.disabled = true;
                populateEditManualReportedYearOptions(this.value);
            } else {
                courseSelect.disabled = false;
                populateEditManualReportedYearOptions('');
            }
        });

        // Event listeners for edit single reporter
        document.getElementById('edit_single_reporter_type').addEventListener('change', handleEditSingleReporterCourseChange);
        document.getElementById('edit_single_reporter_course_main').addEventListener('change', updateEditSingleReporterCourseValue);
        document.getElementById('edit_single_reporter_ladderize_course').addEventListener('change', updateEditSingleReporterCourseValue);
        document.getElementById('edit_single_reporter_year_level').addEventListener('change', updateEditSingleReporterCourseValue);
        // Event listeners for edit single reporter
        document.getElementById('edit_single_reporter_type').addEventListener('change', handleEditSingleReporterCourseChange);
        document.getElementById('edit_single_reporter_course_main').addEventListener('change', handleEditSingleReporterCourseSelection);
        document.getElementById('edit_single_reporter_ladderize_course').addEventListener('change', handleEditSingleReporterCourseSelection);
        document.getElementById('edit_single_reporter_year_level').addEventListener('change', updateEditSingleReporterCourseValue);

        // Event listeners for edit single person
        document.getElementById('edit_single_person_type').addEventListener('change', handleEditSinglePersonCourseChange);
        document.getElementById('edit_single_person_course_main').addEventListener('change', handleEditSinglePersonCourseSelection);
        document.getElementById('edit_single_person_ladderize_course').addEventListener('change', handleEditSinglePersonCourseSelection);
        document.getElementById('edit_single_person_year_level').addEventListener('change', updateEditSinglePersonCourseValue);
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
                </div>
            </div>
        </main>
    </div>
</body>
</html>
