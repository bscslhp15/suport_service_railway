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

require_once __DIR__ . '/../AI CHAT BOT/chat_widget.php';

$pdo = get_db();
$message = '';
$messageType = 'success';

// ========== FORM SUBMISSIONS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Accept new case with session scheduling
    if ($action === 'accept_case') {
        $caseId = (int)($_POST['case_id'] ?? 0);
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
        } elseif ($sessionTime < '08:00' || $sessionTime > '17:00') {
            $message = 'Session time must be between 08:00 and 17:00.';
            $messageType = 'error';
        } elseif ($caseId > 0) {
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
                $notes .= ' Mode: ' . $sessionMode . '. ' . ($sessionMode === 'Face-to-Face' ? 'Venue/Location' : 'Meeting Link/Platform') . ': ' . $locationLink . '. Participants: ' . $participationScopeStr;
                
                if (add_guidance_session([
                    'case_id' => $caseId,
                    'counselor_id' => $user['id'],
                    'session_date' => $sessionDate,
                    'session_time' => $sessionTime . ':00',
                    'session_category' => 'under_guidance_counseling', // default
                    'participation_scope' => $participationScopeStr,
                    'attendance_status' => 'pending',
                    'notes' => $notes,
                ])) {
                    if (update_guidance_case($caseId, ['status' => 'in_progress', 'counselor_id' => $user['id'], 'participants' => $participantsFieldValue])) {
                        $message = 'Case accepted. Session scheduled for ' . date('M d, Y H:i', strtotime($sessionDateTime)) . '.';
                        $messageType = 'success';
                    } else {
                        $message = 'Session created but unable to update case status.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Unable to schedule session.';
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'Invalid case.';
            $messageType = 'error';
        }
    }

    // Reject new case
    elseif ($action === 'reject_case') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $rejectCategory = trim($_POST['reject_category'] ?? '');
        $customReasonText = trim($_POST['custom_reason_text'] ?? '');
        $rejectNote = trim($_POST['reject_note'] ?? '');
        
        $rejectReason = ($rejectCategory === 'custom') ? $customReasonText : $rejectCategory;
        $fullReason = $rejectReason . ($rejectNote ? ' | Additional note: ' . $rejectNote : '');
        
        if ($caseId > 0 && update_guidance_case($caseId, [
            'status' => 'escalated',
            'internal_notes' => $fullReason
        ])) {
            $message = 'Case rejected successfully.';
            $messageType = 'success';
        } else {
            $message = 'Unable to reject case.';
            $messageType = 'error';
        }
    }

    // Return case for clarification
    elseif ($action === 'return_case') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $clarificationNotes = trim($_POST['clarification_notes'] ?? '');
        if ($caseId > 0 && update_guidance_case($caseId, [
            'status' => 'escalated',
            'internal_notes' => $clarificationNotes ?: 'Case returned for clarification.'
        ])) {
            $message = 'Case returned for clarification.';
            $messageType = 'success';
        } else {
            $message = 'Unable to return case.';
            $messageType = 'error';
        }
    }
    
    // Log session
    elseif ($action === 'log_session') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $attendanceStatus = $_POST['attendance_status'] ?? 'pending';
        $sessionMode = trim($_POST['session_mode'] ?? '');
        $locationLink = trim($_POST['location_link'] ?? '');
        $observationNotes = trim($_POST['observation_notes'] ?? '');
        
        $validation = validate_observation_text($observationNotes);
        if (!$validation['valid']) {
            $message = 'Observation validation failed: ' . $validation['message'];
            $messageType = 'error';
        } elseif ($caseId > 0) {
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
            } else {
                $message = 'Unable to log session.';
                $messageType = 'error';
            }
        }
    }
    
    // Update resolution
    elseif ($action === 'update_resolution') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        $internalResolution = $_POST['internal_resolution'] ?? 'none';
        $caseRemarks = trim($_POST['case_remarks'] ?? '');
        $internalNotes = trim($_POST['internal_notes'] ?? '');
        $currentUser = current_user();
        
        if ($internalResolution === 'closed_case') {
            $newStatus = 'closed';
        } else {
            $newStatus = 'in_progress';
        }
        
        $notesValue = null;
        if ($caseRemarks !== '' || $internalNotes !== '') {
            $notesValue = '';
            if ($caseRemarks !== '') {
                $notesValue .= 'Remarks: ' . $caseRemarks;
            }
            if ($internalNotes !== '') {
                if ($notesValue !== '') {
                    $notesValue .= "\n\n";
                }
                $notesValue .= 'Notes: ' . $internalNotes;
            }
        }
        
        if ($caseId > 0 && update_guidance_case($caseId, [
            'external_resolution' => 'none',
            'internal_resolution' => $internalResolution,
            'case_remarks' => $caseRemarks !== '' ? $caseRemarks : null,
            'internal_notes' => $notesValue,
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ])) {
            $message = 'Resolution updated successfully.';
            $messageType = 'success';
            
            // Handle file uploads for attachments
            if ($caseId > 0 && !empty($_FILES['attachments']['name'][0])) {
                $uploadsDir = __DIR__ . '/../uploads/case_attachments/';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $maxFileSize = 10 * 1024 * 1024; // 10MB
                
                foreach ($_FILES['attachments']['error'] as $key => $error) {
                    if ($error === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    
                    if ($error !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    
                    $fileName = $_FILES['attachments']['name'][$key];
                    $fileTmp = $_FILES['attachments']['tmp_name'][$key];
                    $fileSize = $_FILES['attachments']['size'][$key];
                    $fileMime = mime_content_type($fileTmp);
                    
                    if ($fileSize > $maxFileSize || !in_array($fileMime, $allowedMimes)) {
                        continue;
                    }
                    
                    $newFileName = uniqid('case_' . $caseId . '_') . '_' . basename($fileName);
                    $newFilePath = $uploadsDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmp, $newFilePath)) {
                        add_guidance_case_attachment($caseId, $currentUser['id'], 'uploads/case_attachments/' . $newFileName, $fileName, $fileMime);
                    }
                }
            }
            
            // Redirect to refresh the page and show updated cases
            header('Location: case_management.php?tab=closed-cases&msg=updated');
            exit;
        } else {
            $message = 'Unable to update resolution.';
            $messageType = 'error';
        }
    }

    // Remove (archive) a closed case
    elseif ($action === 'remove_case') {
        $caseId = (int)($_POST['case_id'] ?? 0);
        if ($caseId > 0 && update_guidance_case($caseId, ['status' => 'removed', 'updated_at' => date('Y-m-d H:i:s')])) {
            header('Location: case_management.php?tab=closed-cases&msg=removed');
            exit;
        } else {
            $message = 'Unable to remove case.';
            $messageType = 'error';
        }
    }
    // Bulk remove multiple cases
    elseif ($action === 'remove_multiple_cases') {
        $caseIds = $_POST['case_ids'] ?? [];
        if (!is_array($caseIds)) $caseIds = [];
        $removed = 0;
        foreach ($caseIds as $cid) {
            $id = (int)$cid;
            if ($id > 0 && update_guidance_case($id, ['status' => 'removed', 'updated_at' => date('Y-m-d H:i:s')])) {
                $removed++;
            }
        }
        header('Location: case_management.php?tab=closed-cases&msg=removed&count=' . $removed);
        exit;
    }

    // Create manual case
    elseif ($action === 'create_manual_case') {
        $reporterType = $_POST['reporter_type'] ?? '';
        $customReporterType = trim($_POST['custom_reporter_type'] ?? '');
        $reporterMobile = trim($_POST['reporter_mobile'] ?? '');
        $reportedPersonType = $_POST['reported_person_type'] ?? '';
        $customReportedType = trim($_POST['custom_reported_type'] ?? '');
        $concernType = $_POST['manual_concern'] ?? '';
        $description = trim($_POST['manual_description'] ?? '');
        $priorityLevel = $_POST['priority_level'] ?? 'Medium';
        $participants = $_POST['participants'] ?? 'STUDENT ONLY';
        $internalNotes = trim($_POST['internal_notes'] ?? '');
        $scheduleSession = isset($_POST['schedule_session']) ? true : false;
        $sessionDateManual = $_POST['session_date_manual'] ?? '';
        $sessionTimeManual = $_POST['session_time_manual'] ?? '';
        $sessionModeManual = $_POST['session_mode_manual'] ?? '';
        $locationLinkManual = trim($_POST['location_link_manual'] ?? '');

        // Validate required fields
        if (!$reporterType || !$reportedPersonType || !$concernType || !$description) {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        } elseif (empty($_POST['selected_reporters']) && $reporterType !== 'proactive') {
            $message = 'Please add at least one reporter.';
            $messageType = 'error';
        } elseif (empty($_POST['selected_reported_persons'])) {
            $message = 'Please add at least one reported person.';
            $messageType = 'error';
        } elseif ($scheduleSession && (!$sessionDateManual || !$sessionTimeManual || !$sessionModeManual)) {
            $message = 'Please fill in session date, time, and mode to schedule the initial session.';
            $messageType = 'error';
        } elseif ($scheduleSession && ($sessionTimeManual < '08:00' || $sessionTimeManual > '17:00')) {
            $message = 'Session time must be between 08:00 and 17:00.';
            $messageType = 'error';
        } else {
            // Get the first reported person for the main case
            $reportedPersons = json_decode($_POST['selected_reported_persons'], true);
            $firstReported = $reportedPersons[0] ?? null;
            
            if (!$firstReported) {
                $message = 'Invalid reported person data.';
                $messageType = 'error';
            } else {
                // Create the case with the first reported person (can be any type)
                $reportedStudentId = null;
                $reportedType = $firstReported['type'] ?? null;
                
                // Try to get a valid user_id from various fields
                if ($firstReported && isset($firstReported['user_id']) && $firstReported['user_id']) {
                    $reportedStudentId = $firstReported['user_id'];
                } elseif ($firstReported && isset($firstReported['id']) && $firstReported['id'] && is_numeric($firstReported['id'])) {
                    $reportedStudentId = (int)$firstReported['id'];
                } elseif ($firstReported && isset($firstReported['external_id']) && $firstReported['external_id'] && $reportedType !== 'course') {
                    // Try to look up the user by external_id (student_id or employee_id)
                    $pdo = get_db();
                    $lookupStmt = $pdo->prepare('SELECT id FROM users WHERE student_id = ? OR employee_id = ? LIMIT 1');
                    $lookupStmt->execute([$firstReported['external_id'], $firstReported['external_id']]);
                    $foundUser = $lookupStmt->fetchColumn();
                    if ($foundUser) {
                        $reportedStudentId = (int)$foundUser;
                    }
                }
                
                // Validate based on reported type
                // For course and other types, we allow NULL reported_student_id
                // For student/teacher types, we need to find or use provided ID
                $isValidType = in_array($reportedType, ['course', 'other']);
                
                if (!$reportedStudentId && !$isValidType) {
                    $message = 'Invalid reported person data. Could not find user.';
                    $messageType = 'error';
                } else {
                    $caseData = [
                        'case_number' => generate_guidance_case_number(),
                        'reported_student_id' => $reportedStudentId,
                        'reporter_user_id' => $user['id'],
                        'reporter_role' => 'teacher',
                        'report_type' => 'other',
                        'type_of_concern' => $concernType,
                        'course_year' => $firstReported['course'] ?? null,
                        'incident_description' => $description,
                        'parent_guardian_notified' => 0,
                        'teacher_awareness_required' => 0,
                        'priority_level' => $priorityLevel,
                        'participants' => $participants,
                    ];

                    $caseId = create_guidance_case($caseData);
                    if ($caseId) {
                        $pdo = get_db();
                        
                        // Store reporters
                        $reporters = json_decode($_POST['selected_reporters'], true);
                        foreach ($reporters as $reporter) {
                            $stmt = $pdo->prepare(
                                'INSERT INTO case_reporters (case_id, reporter_type, reporter_name, reporter_id, reporter_course, mobile_number, custom_reporter_type) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)'
                            );
                            $stmt->execute([
                                $caseId,
                                $reporter['type'],
                                $reporter['name'],
                                $reporter['id'],
                                $reporter['course'],
                                $reporter['mobile'],
                                $customReporterType ?: null
                            ]);
                        }
                        
                        // Store reported persons
                        foreach ($reportedPersons as $person) {
                            $personUserId = $person['user_id'] ?? $person['id'] ?? null;
                            $personExternalId = $person['external_id'] ?? $person['id'] ?? null;
                            
                            // If we don't have user_id but have external_id, look it up from the database
                            if (!$personUserId && $personExternalId && $person['type'] !== 'course') {
                                $lookupStmt = $pdo->prepare(
                                    'SELECT id FROM users WHERE (student_id = ? OR employee_id = ?) LIMIT 1'
                                );
                                $lookupStmt->execute([$personExternalId, $personExternalId]);
                                $foundUser = $lookupStmt->fetchColumn();
                                if ($foundUser) {
                                    $personUserId = (int)$foundUser;
                                }
                            }
                            
                            $stmt = $pdo->prepare(
                                'INSERT INTO case_reported_persons (case_id, person_type, person_name, person_id, person_user_id, person_course, custom_person_type) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?)'
                            );
                            $stmt->execute([
                                $caseId,
                                $person['type'],
                                $person['name'],
                                $personExternalId,
                                $personUserId,
                                $person['course'],
                                $customReportedType ?: null
                            ]);
                        }

                        // Store additional metadata only when counselor notes are provided
                        if (!empty($internalNotes)) {
                            update_guidance_case($caseId, ['internal_notes' => $internalNotes]);
                        }

                        // Handle file upload if provided
                        if (isset($_FILES['manual_document']) && $_FILES['manual_document']['error'] === UPLOAD_ERR_OK) {
                            $uploadDir = __DIR__ . '/../uploads/guidance';
                            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                            $fileName = uniqid('manual_') . '_' . basename($_FILES['manual_document']['name']);
                            $filePath = $uploadDir . '/' . $fileName;
                            if (move_uploaded_file($_FILES['manual_document']['tmp_name'], $filePath)) {
                                add_guidance_case_attachment($caseId, $user['id'], 'uploads/guidance/' . $fileName, $_FILES['manual_document']['name'], $_FILES['manual_document']['type']);
                            }
                        }

                        // Schedule initial session if requested
                        if ($scheduleSession) {
                            $sessionDate = $_POST['session_date_manual'] ?? '';
                            $sessionTime = $_POST['session_time_manual'] ?? '';
                            $sessionMode = $_POST['session_mode_manual'] ?? '';
                            $locationLink = trim($_POST['location_link_manual'] ?? '');

                            if ($sessionDate && $sessionTime && $sessionMode) {
                                $sessionDateTime = $sessionDate . ' ' . $sessionTime . ':00';
                                
                                $notes = 'Mode: ' . $sessionMode;
                                if ($locationLink) {
                                    $notes .= '. ' . ($sessionMode === 'Face-to-Face' ? 'Venue/Location' : 'Meeting Link/Platform') . ': ' . $locationLink;
                                }

                                add_guidance_session([
                                    'case_id' => $caseId,
                                    'counselor_id' => $user['id'],
                                    'session_date' => $sessionDate,
                                    'session_time' => $sessionTime . ':00',
                                    'session_category' => 'under_guidance_counseling',
                                    'participation_scope' => 'student_only',
                                    'attendance_status' => 'pending',
                                    'notes' => $notes,
                                ]);
                            }
                        }

                        // Set case status to in_progress so it appears in active cases
                        update_guidance_case($caseId, ['status' => 'in_progress', 'counselor_id' => $user['id']]);
                        
                        $message = 'Manual case created successfully for ' . htmlspecialchars($firstReported['name']) . '.';
                        $messageType = 'success';
                    } else {
                        $message = 'Unable to create manual case. Please try again.';
                        $messageType = 'error';
                    }
                }
            }
        }
    }
}

// Handle GET messages from redirects
if (isset($_GET['msg'])) {
    $msgType = $_GET['msg'];
    $count = (int)($_GET['count'] ?? 0);
    
    switch ($msgType) {
        case 'updated':
            $message = 'Case resolution updated successfully.';
            $messageType = 'success';
            break;
        case 'removed':
            if ($count > 0) {
                $message = 'Successfully removed ' . $count . ' case' . ($count === 1 ? '' : 's') . '.';
            } else {
                $message = 'Case removed successfully.';
            }
            $messageType = 'success';
            break;
    }
}

// Get all cases
$allCases = get_guidance_cases_for_counselor($user['id']);
$newCases = array_filter($allCases, fn($c) => $c['status'] === 'open');
$activeCases = array_filter($allCases, fn($c) => $c['status'] === 'in_progress');
$closedCases = array_filter($allCases, fn($c) => in_array($c['status'], ['resolved', 'closed']));
$recentActivity = array_filter($allCases, fn($c) => $c['status'] === 'escalated');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Management | Guidance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
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

        .case-page-header {
            position: relative;
            min-height: 245px;
            display: flex;
            align-items: center;
            overflow: hidden;
            margin: 0 0 24px;
            padding: 42px 48px 30px;
            background: #f8f1e7;
            border: 0;
            border-radius: 0 0 24px 24px;
        }

        .case-page-header::before {
            content: '';
            position: absolute;
            z-index: 0;
            inset: 0 auto 0 0;
            width: 3px;
            background: #861b17;
        }

        .case-page-header > div:first-child {
            position: relative;
            z-index: 2;
            width: 65%;
        }

        .case-page-header .eyebrow {
            margin: 0;
            color: #861b17 !important;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 3px;
            line-height: 1;
            text-transform: uppercase;
        }

        .case-page-header h1 {
            margin: 14px 0 12px;
            color: #111 !important;
            font-family: Georgia, serif;
            font-size: clamp(36px, 4vw, 62px);
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.05;
        }

        .case-page-header .dashboard-subtitle {
            max-width: 760px;
            margin: 0;
            color: #34302d !important;
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
        }

        .case-header__art {
            position: absolute;
            z-index: 1;
            top: 0;
            right: 0;
            width: 43%;
            height: 100%;
            color: #d7a58d;
            opacity: .78;
        }

        .case-header__art::before {
            content: '';
            position: absolute;
            right: 13%;
            bottom: 2%;
            width: 82%;
            height: 30%;
            background: radial-gradient(ellipse at center, rgba(235, 205, 167, .48) 0 42%, transparent 43%);
            border-radius: 50%;
        }

        .case-header__art::after {
            content: '';
            position: absolute;
            top: 23%;
            left: 4%;
            width: 76%;
            height: 42%;
            border: 1px solid rgba(215, 165, 141, .45);
            border-left-color: transparent;
            border-radius: 50%;
            transform: rotate(-13deg);
        }

        .case-header__art i {
            position: absolute;
            z-index: 2;
            font-family: 'Font Awesome 6 Free';
            font-size: 28px;
            font-style: normal;
            font-weight: 900;
        }

        .case-header__art .art-chat {
            top: 57%;
            left: 2%;
            padding: 12px 14px;
            border: 2px solid rgba(215, 165, 141, .7);
            border-radius: 9px;
            font-size: 15px;
        }

        .case-header__art .art-chat::after {
            content: '';
            position: absolute;
            right: 12px;
            bottom: -9px;
            width: 14px;
            height: 14px;
            border-right: 2px solid rgba(215, 165, 141, .7);
            border-bottom: 2px solid rgba(215, 165, 141, .7);
            background: #f8f1e7;
            transform: rotate(45deg);
        }

        .case-header__art .art-file {
            top: 17%;
            left: 39%;
            padding: 16px 19px;
            border: 2px solid rgba(215, 165, 141, .58);
            border-radius: 9px;
            box-shadow: 20px 10px 0 -1px #f8f1e7, 20px 10px 0 1px rgba(215, 165, 141, .48), 36px 20px 0 -1px #f8f1e7, 36px 20px 0 1px rgba(215, 165, 141, .42);
            font-size: 35px;
        }

        .case-header__art .art-card {
            top: 35%;
            left: 47%;
            padding: 13px 16px;
            border: 2px solid rgba(215, 165, 141, .65);
            border-radius: 8px;
            background: rgba(248, 241, 231, .7);
            font-size: 28px;
        }

        .case-header__art .art-check {
            top: 22%;
            right: 7%;
            padding: 12px;
            border: 2px solid #e3b968;
            border-radius: 50%;
            color: #e3b968;
            font-size: 21px;
        }

        .case-header__art .art-pin {
            bottom: 10%;
            left: 42%;
            width: 31px;
            height: 31px;
            color: transparent;
            background: #cf9589;
            border-radius: 50% 50% 50% 0;
            font-size: 0;
            transform: rotate(-45deg);
        }

        .case-header__art .art-pin::after {
            content: '';
            position: absolute;
            top: 9px;
            left: 9px;
            width: 13px;
            height: 13px;
            background: #f8f1e7;
            border-radius: 50%;
        }

        .case-header__art .art-dots {
            right: 25%;
            bottom: 12%;
            color: #d7a58d;
            font-size: 28px;
        }

        @media (max-width: 768px) {
            .case-page-header {
                min-height: 245px;
                padding: 32px 24px 110px;
            }

            .case-page-header::before {
                width: 3px;
            }

            .case-page-header > div:first-child {
                width: 100%;
            }

            .case-page-header h1 {
                font-size: 36px;
            }

            .case-header__art {
                width: 55%;
                opacity: .35;
            }
        }

        .card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; background: white; margin-bottom: 16px; }
        .card h3 { margin-top: 0; color: #1f2937; }
        .cases-table { width: 100%; border-collapse: collapse; }
        .cases-table th, .cases-table td { padding: 12px 10px; border: 1px solid #e2e8f0; text-align: left; }
        .cases-table th { background: #f8fafc; font-weight: 600; }
        .cases-table tr:hover { background: #f9fafb; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
        .alert-error { background: #fee2e2; border-left: 4px solid #ef4444; color: #7f1d1d; }
        .button { padding: 10px 16px; border: none; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.open { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 24px; border-radius: 8px; max-width: 500px; width: 90%; }
        .modal-header { font-size: 18px; font-weight: 600; margin-bottom: 16px; }
        .metric-card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; background: white; }
        .metric-card h3 { margin: 0 0 8px 0; color: #6b7280; font-size: 14px; font-weight: 500; text-transform: uppercase; }
        .metric-card .value { font-size: 28px; font-weight: 700; color: #1f2937; }
        .tabs { display: flex; gap: 10px; background: white; border-radius: 10px; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); flex-wrap: wrap; }
        .tab-button { flex: 1; padding: 1rem; text-align: center; background: #f8f9fa; border: none; cursor: pointer; transition: all 0.3s ease; font-weight: 500; color: black; border-radius: 8px; min-width: 150px; }
        .tab-button i { color: #800000; margin-right: 0.5rem; }
        .tab-button:hover:not(.active) { background: #800000; color: white; }
        .tab-button:hover:not(.active) i { color: #D4AF37; }
        .tab-button.active { background: #800000; color: white; }
        .tab-button.active i { color: #D4AF37; }
        .tab-content { display: none; min-height: 0; }
        .tab-content.active { display: block !important; animation: fadeIn 0.2s ease-in; }
        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .cases-table { width: 100%; border-collapse: collapse; table-layout: auto; }
        .cases-table th, .cases-table td { white-space: normal; word-break: break-word; box-sizing: border-box; }
        .case-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: stretch; }
        .case-actions .button { min-width: 100px; }
        .case-actions .button, .case-actions a.button { text-align: center; }
        .case-actions .button i {
            margin-right: 6px;
        }
        .case-actions .btn-secondary {
            background: #fbf4e4;
            color: #5c1f23;
            border: 1px solid #d8b8a8;
        }
        .case-actions .btn-secondary:hover {
            background: #f4e2d9;
            color: #8f0011;
        }
        .case-actions .btn-primary {
            background: linear-gradient(135deg, #68151c, #9e3030);
            color: #ffffff;
            border: 1px solid #68151c;
        }
        .case-actions .btn-primary:hover {
            background: linear-gradient(135deg, #531017, #8f0011);
        }
        .case-actions .reject-button {
            background: #f9e5df;
            color: #8f0011;
            border: 1px solid #d8a99b;
        }
        .case-actions .reject-button:hover {
            background: #f1d1c8;
            color: #68151c;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .tabs::-webkit-scrollbar { display: none; }
        @media (max-width: 768px) {
            .tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; touch-action: pan-x; padding: 0.5rem; }
            .tab-button { flex: 0 0 auto; min-width: 140px; white-space: nowrap; }
            .tab-content { padding: 0 0.5rem; }
            .tab-content p { font-size: 14px; }
            .cases-table th, .cases-table td { padding: 10px 8px; font-size: 12px; }
            .case-actions { display: flex; flex-wrap: wrap; gap: 6px; }
            .case-actions .button { min-width: 100px; }
        }
        @media (max-width: 720px) {
            .table-scroll { overflow-x: hidden; }
            .cases-table, .cases-table thead, .cases-table tbody, .cases-table tr, .cases-table th, .cases-table td { display: block; width: 100%; }
            .cases-table thead { display: none; }
            .cases-table tr { margin-bottom: 16px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; padding: 12px; }
            .cases-table td { padding: 10px 0; border: none; border-bottom: 1px solid #e2e8f0; position: relative; text-align: left; }
            .cases-table td:last-child { border-bottom: none; }
            .cases-table td::before { content: attr(data-label); font-weight: 700; display: block; margin-bottom: 6px; color: #334155; }
            .case-actions { display: flex; flex-direction: column; gap: 8px; }
            .case-actions .button { width: 100%; min-width: auto; }
            .case-actions .button, .case-actions a.button { text-align: center; justify-content: center; }
            .cases-table td .button { width: 100%; box-sizing: border-box; }
        }
        @media (max-width: 480px) {
            .tab-button { min-width: 120px; padding: 0.75rem 0.65rem; font-size: 13px; }
            .cases-table th, .cases-table td { font-size: 11px; padding: 8px 6px; }
            .case-actions .button { min-width: 95px; font-size: 11px; padding: 6px 8px; }
        }
        .subtab-content { width: 100%; }
        .feedback-subtabs { display: flex; gap: 8px; background: #f8fafc; border-radius: 10px; padding: 8px; flex-wrap: wrap; margin-bottom: 18px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .feedback-subtabs .tab-button { flex: 1 1 200px; min-width: 160px; border-radius: 10px; background: #ffffff; border: 1px solid #e5e7eb; padding: 0.95rem 1rem; color: #1f2937; font-weight: 600; white-space: normal; }
        .feedback-subtabs .tab-button.active { background: #800000; color: #fff; border-color: #800000; }
        .feedback-subtabs .tab-button i { margin-right: 0.5rem; }
        .feedback-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; box-shadow: 0 8px 20px rgba(15,23,42,0.05); }
        .feedback-card h2, .feedback-card h3 { margin-top: 0; }
        .feedback-card p { margin-bottom: 0.75rem; line-height: 1.55; color: #475569; }
        .borrow-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .borrow-table th, .borrow-table td { padding: 12px; border: 1px solid #e2e8f0; text-align: left; font-size: 13px; word-break: break-word; }
        .borrow-table th { background: #f8fafc; font-weight: 600; color: #0f172a; }
        .borrow-table tr:hover { background: #f9fafb; }
        .feedback-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
        .feedback-list li { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; }
        .feedback-list li strong { display: block; margin-bottom: 6px; color: #0f172a; }
        .feedback-empty { color: #64748b; text-align: center; padding: 18px 12px; background: #f8fafc; border-radius: 14px; }
        @media (max-width: 720px) {
            .feedback-subtabs { gap: 6px; padding: 6px; }
            .feedback-subtabs .tab-button { min-width: calc(50% - 8px); padding: 0.9rem 0.75rem; font-size: 13px; }
            .feedback-card { padding: 16px; }
            .borrow-table, .borrow-table thead, .borrow-table tbody, .borrow-table tr, .borrow-table th, .borrow-table td { display: block; width: 100%; }
            .borrow-table thead { display: none; }
            .borrow-table tr { margin-bottom: 16px; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; padding: 12px; }
            .borrow-table td { padding: 10px 0; border: none; border-bottom: 1px solid #e2e8f0; position: relative; text-align: left; }
            .borrow-table td:last-child { border-bottom: none; }
            .borrow-table td::before { content: attr(data-label); font-weight: 700; display: block; margin-bottom: 6px; color: #334155; }
        }
        @media (max-width: 480px) {
            .feedback-subtabs { gap: 4px; }
            .feedback-subtabs .tab-button { min-width: 100px; padding: 0.8rem 0.7rem; font-size: 12px; }
            .feedback-card { padding: 14px; }
            .borrow-table td { padding: 8px 0; }
        }
        .inventory-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 28px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); padding: 28px; width: 100%; box-sizing: border-box; display: block; }
        .inventory-card h2 { font-size: 20px; margin-bottom: 18px; color: #111827; }
        .borrow-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .borrow-table th, .borrow-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 13px; }
        .borrow-table th { background: #f8fafc; font-weight: 600; color: #0f172a; }
        .borrow-table tr:hover { background: #f9fafb; }
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <script>
        // Swipe-to-refresh for mobile
        (function() {
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
        })();
        
        // Define all functions at the very beginning
        window.switchTab = function(event, tabName) {
            event.preventDefault();
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => button.classList.remove('active'));
            const targetTab = document.getElementById(tabName);
            if (targetTab) targetTab.classList.add('active');
            if (event && event.target) event.target.classList.add('active');
        };
        
        window.openModal = function(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('open');
        };
        
        window.closeModal = function(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('open');
        };
        
        window.toggleCustomReason = function(caseId) {
            const dropdown = document.getElementById('reject_category_' + caseId);
            const customDiv = document.getElementById('custom_reason_' + caseId);
            if (dropdown && customDiv) {
                customDiv.style.display = dropdown.value === 'custom' ? 'block' : 'none';
            }
        };
        
        window.toggleLocationLabel = function(caseId) {
            const mode = document.getElementById('session_mode_' + caseId).value;
            const label = document.getElementById('location_label_' + caseId);
            const textarea = document.getElementById('location_link_' + caseId);
            if (label && textarea) {
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
        };
        
        window.toggleParticipationOther = function(caseId) {
            const scope = document.getElementById('participation_scope_' + caseId).value;
            const otherDiv = document.getElementById('other_participation_' + caseId);
            if (otherDiv) {
                otherDiv.style.display = scope === 'Other' ? 'block' : 'none';
            }
        };

        window.toggleScheduleSection = function() {
            const checkbox = document.getElementById('schedule_session').checked;
            const section = document.getElementById('schedule_section');
            if (section) {
                section.style.display = checkbox ? 'block' : 'none';
            }
        };

        window.toggleLocationLabelManual = function() {
            const mode = document.getElementById('session_mode_manual') ? document.getElementById('session_mode_manual').value : '';
            const label = document.getElementById('location_label_manual');
            const input = document.getElementById('location_link_manual');
            if (label && input) {
                if (mode === 'Face-to-Face') {
                    label.textContent = 'Venue/Location:';
                    input.placeholder = 'e.g., Guidance Office Room 101';
                } else if (mode === 'Online') {
                    label.textContent = 'Meeting Link/Platform:';
                    input.placeholder = 'e.g., Google Meet link or Zoom room';
                } else {
                    label.textContent = 'Venue/Location:';
                    input.placeholder = '';
                }
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab-button').forEach(function(button) {
                button.removeAttribute('onclick');
                button.addEventListener('click', function(event) {
                    window.switchTab(event, button.dataset.tab);
                });
            });
            
            // Add event listeners for sub-tab buttons
            document.querySelectorAll('[data-subtab]').forEach(function(button) {
                button.removeAttribute('onclick');
                button.addEventListener('click', function(event) {
                    window.switchSubTab(event, button.dataset.subtab);
                });
            });
            
            // Load feedback when tab is clicked
            const feedbackButton = document.querySelector('[data-tab="feedback-cases"]');
            if (feedbackButton) {
                feedbackButton.addEventListener('click', loadCaseManagementFeedback);
            }
        });

        // Load feedback for case management
        function loadCaseManagementFeedback() {
            fetch('/THESIS/SUPPORTSERVICESYSTEM/includes/api_guidance_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_all',
                    case_id: 0
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('feedback-table-body');
                if (!data.success || !data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #6b7280;">No feedback submitted yet.</td></tr>';
                    return;
                }
                
                let rows = '';
                data.data.forEach(fb => {
                    const stars = createRatingStars(fb.service_rating);
                    rows += `
                        <tr>
                            <td data-label="Student Name">${htmlEscapeText(fb.full_name || 'Unknown')}</td>
                            <td data-label="Case ID"><strong>${fb.case_id}</strong></td>
                            <td data-label="Concern Type">${htmlEscapeText(fb.type_of_concern?.substring(0, 30) || 'N/A')}</td>
                            <td data-label="Rating">${stars}</td>
                            <td data-label="Feedback">${htmlEscapeText(fb.feedback_text?.substring(0, 40) || '-')}</td>
                            <td data-label="Date Submitted">${new Date(fb.created_at).toLocaleDateString()}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = rows;
            })
            .catch(e => {
                console.error('Error loading feedback:', e);
                document.getElementById('feedback-table-body').innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Error loading feedback: ' + e.message + '</td></tr>';
            });
        }

        function createRatingStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<span style="color: ${i <= rating ? '#fbbf24' : '#d1d5db'}; font-size: 16px;">★</span>`;
            }
            return stars;
        }

        function htmlEscapeText(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // Switch between feedback subtabs
        function switchSubTab(event, subtabName) {
            event.preventDefault();
            
            // Ensure parent tab-content is visible
            const parentTab = document.getElementById('feedback-cases');
            if (parentTab) {
                parentTab.classList.add('active');
                parentTab.style.display = 'block';
            }
            
            // Hide all subtabs
            document.querySelectorAll('.subtab-content').forEach(el => {
                el.style.display = 'none';
            });
            
            // Remove active class from all sub-tab buttons
            document.querySelectorAll('[data-subtab]').forEach(btn => {
                btn.classList.remove('active');
                btn.style.borderBottom = '3px solid transparent';
            });
            
            // Show selected subtab
            const selectedContent = document.getElementById(subtabName);
            if (selectedContent) {
                selectedContent.style.display = 'block';
            }
            
            // Mark the clicked button as active
            const button = event.target.closest('.tab-button');
            if (button) {
                button.classList.add('active');
                button.style.borderBottom = '3px solid #3b82f6';
            }
            
            // Load data for the selected subtab
            if (subtabName === 'goodmoral-feedback') {
                loadGoodMoralFeedback();
            } else if (subtabName === 'guidance-feedback') {
                loadCaseManagementFeedback();
            }
        }

        // Load Good Moral feedback from API
        function loadGoodMoralFeedback() {
            fetch('/THESIS/SUPPORTSERVICESYSTEM/includes/api_good_moral_feedback.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_all'
                }),
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                const tbody = document.getElementById('goodmoral-feedback-table-body');
                if (!data.success || !data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #6b7280;">No feedback submitted yet.</td></tr>';
                    return;
                }
                
                let rows = '';
                data.data.forEach(fb => {
                    const stars = createRatingStars(fb.service_rating);
                    rows += `
                        <tr>
                            <td data-label="Student Name">${htmlEscapeText(fb.full_name || 'Unknown')}</td>
                            <td data-label="Email">${htmlEscapeText(fb.email || '-')}</td>
                            <td data-label="Course">${htmlEscapeText(fb.course_year?.substring(0, 30) || 'N/A')}</td>
                            <td data-label="Rating">${stars}</td>
                            <td data-label="Feedback">${htmlEscapeText(fb.feedback_text?.substring(0, 40) || '-')}</td>
                            <td data-label="Date Submitted">${new Date(fb.created_at).toLocaleDateString()}</td>
                        </tr>
                    `;
                });
                tbody.innerHTML = rows;
            })
            .catch(e => {
                console.error('Error loading good moral feedback:', e);
                document.getElementById('goodmoral-feedback-table-body').innerHTML = '<tr><td colspan="6" style="text-align: center; color: red;">Error loading feedback: ' + e.message + '</td></tr>';
            });
        }
    </script>
    <script>
        function filterActiveCases() {
            const sel = document.getElementById('activeStatusFilter');
            if (!sel) return;
            const val = sel.value;
            const rows = document.querySelectorAll('#active-cases table.cases-table tbody tr');
            let shown = 0;
            rows.forEach(r => {
                const res = r.getAttribute('data-resolution') || 'none';
                if (val === 'all' || res === val) {
                    r.style.display = '';
                    shown++;
                } else {
                    r.style.display = 'none';
                }
            });
            const countEl = document.getElementById('activeCount');
            if (countEl) countEl.textContent = shown;
        }

        // Initialize filter to show all on page load
        document.addEventListener('DOMContentLoaded', function () {
            filterActiveCases();
        });
    </script>
    <script src="../assets/js/app.js" defer></script>

    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Guidance Management</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
               
            </div>
            <div class="nav-section">
                <a href="guidance_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="school_announcements.php?service=guidance" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
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
                <a href="profile.php?service=guidance" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="about.php?service=guidance" data-tooltip="About">
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
        <div id="pageOverlay" class="page-overlay"></div>

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

                 
                </div>
            </header>

            <div class="main-scroll">
                    <section class="dashboard-intro case-page-header">
                        <div>
                            <span class="eyebrow">CASE MANAGEMENT</span>
                            <h1>Manage Guidance Cases</h1>
                            <p class="dashboard-subtitle">Track case progress, update resolutions, and review notes for student counseling and follow-up.</p>
                        </div>
                        <div class="case-header__art" aria-hidden="true">
                            <i class="fa-solid fa-message art-chat"></i>
                            <i class="fa-solid fa-folder-open art-file"></i>
                            <i class="fa-solid fa-address-card art-card"></i>
                            <i class="fa-solid fa-circle-check art-check"></i>
                            <i class="fa-solid fa-location-pin art-pin"></i>
                            <i class="fa-solid fa-leaf art-dots"></i>
                        </div>
                    </section>

                    <?php if ($message !== ''): ?>
                        <div class="alert <?= $messageType === 'error' ? 'alert-error' : 'alert-success' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        <div class="metric-card">
                            <h3>Pending Review</h3>
                            <div class="value"><?= count($newCases) ?></div>
                        </div>
                        <div class="metric-card">
                            <h3>Active Cases</h3>
                            <div class="value"><?= count($activeCases) ?></div>
                        </div>
                        <div class="metric-card">
                            <h3>Closed Cases</h3>
                            <div class="value"><?= count($closedCases) ?></div>
                        </div>
                    </div>

                    <!-- TABBED CASE MANAGEMENT -->
                    <div class="card">
                        <div class="tabs">
                            <button class="tab-button active" data-tab="new-cases" onclick="switchTab(event, 'new-cases')"><i class="fas fa-file-alt"></i> New Cases</button>
                            <button class="tab-button" data-tab="active-cases" onclick="switchTab(event, 'active-cases')"><i class="fas fa-hourglass-half"></i> Active Cases</button>
                            <button class="tab-button" data-tab="closed-cases" onclick="switchTab(event, 'closed-cases')"><i class="fas fa-check-circle"></i> Closed Cases</button>
                            <button class="tab-button" data-tab="manual-case" onclick="switchTab(event, 'manual-case')"><i class="fas fa-plus-circle"></i> Manual Case</button>
                            <button class="tab-button" data-tab="feedback-cases" onclick="switchTab(event, 'feedback-cases')"><i class="fas fa-star"></i> Feedback</button>
                        </div>

                        <!-- TAB 1: NEW CASES QUEUE -->
                        <div id="new-cases" class="tab-content active">
                            <?php if (empty($newCases)): ?>
                                <p style="color: #6b7280; text-align: center; padding: 12px 0; margin: 0;">No new cases awaiting review.</p>
                            <?php else: ?>
                                <div class="table-scroll">
                                    <table class="cases-table">
                                        <thead>
                                            <tr>
                                                <th>Case ID</th>
                                                <th>Student</th>
                                                <th>Concern</th>
                                                <th>Submitted</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($newCases as $case): ?>
                                                <tr>
                                                    <td data-label="Case ID"><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                    <td data-label="Student"><?= htmlspecialchars($case['reported_student_name'] ?? 'Unknown') ?></td>
                                                    <td data-label="Concern"><?= htmlspecialchars(substr($case['type_of_concern'], 0, 25)) ?></td>
                                                    <td data-label="Submitted"><?= date('M d, Y', strtotime($case['created_at'])) ?></td>
                                                    <td data-label="Actions" class="case-actions">
                                                        <a href="case_review.php?case_id=<?= (int)$case['id'] ?>" class="button btn-secondary"><i class="fa-solid fa-eye" aria-hidden="true"></i>View</a>
                                                        <button type="button" class="button btn-primary" onclick="openModal('accept_<?= $case['id'] ?>')"><i class="fa-solid fa-check" aria-hidden="true"></i>Accept</button>
                                                        <button type="button" class="button btn-secondary reject-button" onclick="openModal('reject_<?= $case['id'] ?>')"><i class="fa-solid fa-xmark" aria-hidden="true"></i>Reject</button>
                                                        
                                                        <!-- Accept and Schedule Session Modal -->
                                                        <div class="modal" id="accept_<?= $case['id'] ?>">
                                                            <div class="modal-content">
                                                                <div class="modal-header">Accept Case & Schedule First Session</div>
                                                                <form method="post">
                                                                    <input type="hidden" name="action" value="accept_case">
                                                                    <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                                    <div class="form-group">
                                                                        <label for="session_date_<?= $case['id'] ?>">Date and Time:</label>
                                                                        <div style="display: flex; gap: 8px;">
                                                                            <input type="date" name="session_date" id="session_date_<?= $case['id'] ?>" required style="flex: 1;">
                                                                            <input type="time" name="session_time" id="session_time_<?= $case['id'] ?>" required min="08:00" max="17:00" style="flex: 1;">
                                                                        </div>
                                                                        <small style="color: #6b7280; display: block; margin-top: 6px;">Available time: 08:00 to 17:00 only.</small>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="session_mode_<?= $case['id'] ?>">Session Mode:</label>
                                                                        <select name="session_mode" id="session_mode_<?= $case['id'] ?>" required onchange="toggleLocationLabel('<?= $case['id'] ?>')">
                                                                            <option value="">-- Select mode --</option>
                                                                            <option value="Face-to-Face">Face-to-Face</option>
                                                                            <option value="Online">Online</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="location_link_<?= $case['id'] ?>" id="location_label_<?= $case['id'] ?>">Venue/Location:</label>
                                                                        <textarea name="location_link" id="location_link_<?= $case['id'] ?>" placeholder="e.g., Guidance Office Room 101" required></textarea>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="participation_scope_<?= $case['id'] ?>">Participation Scope:</label>
                                                                        <select name="participation_scope" id="participation_scope_<?= $case['id'] ?>" required onchange="toggleParticipationOther('<?= $case['id'] ?>')">
                                                                            <option value="">-- Select participation --</option>
                                                                            <option value="Student only">Student only</option>
                                                                            <option value="Student and Parent/Guardian">Student and Parent/Guardian</option>
                                                                            <option value="Student and Teacher">Student and Teacher</option>
                                                                            <option value="Other">Other</option>
                                                                        </select>
                                                                        <div id="other_participation_<?= $case['id'] ?>" style="display: none; margin-top: 8px;">
                                                                            <textarea name="participation_other" id="participation_other_<?= $case['id'] ?>" placeholder="Specify custom participation scope..." style="margin-bottom: 8px;"></textarea>
                                                                            <textarea name="additional_attendees" id="additional_attendees_<?= $case['id'] ?>" placeholder="Specify additional attendees if needed..."></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="session_notes_<?= $case['id'] ?>">Notes / Description:</label>
                                                                        <textarea name="session_notes" id="session_notes_<?= $case['id'] ?>" placeholder="Add any notes or comments about the case..."></textarea>
                                                                    </div>
                                                                    <div style="display: flex; gap: 8px;">
                                                                        <button type="submit" class="button btn-primary">Accept & Schedule</button>
                                                                        <button type="button" class="button btn-secondary" onclick="closeModal('accept_<?= $case['id'] ?>')">Cancel</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Reject Case Modal -->
                                                        <div class="modal" id="reject_<?= $case['id'] ?>">
                                                            <div class="modal-content">
                                                                <div class="modal-header">Reject Case</div>
                                                                <form method="post">
                                                                    <input type="hidden" name="action" value="reject_case">
                                                                    <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                                    <div class="form-group">
                                                                        <label for="reject_category_<?= $case['id'] ?>">Rejection Reason:</label>
                                                                        <select name="reject_category" id="reject_category_<?= $case['id'] ?>" required onchange="toggleCustomReason('<?= $case['id'] ?>')">
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
                                                                    <div class="form-group" id="custom_reason_<?= $case['id'] ?>" style="display: none;">
                                                                        <label for="custom_reason_text_<?= $case['id'] ?>">Please specify your reason:</label>
                                                                        <textarea name="custom_reason_text" id="custom_reason_text_<?= $case['id'] ?>" placeholder="Enter your custom rejection reason..."></textarea>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label for="reject_note_<?= $case['id'] ?>">Additional Notes (Optional):</label>
                                                                        <textarea name="reject_note" id="reject_note_<?= $case['id'] ?>" placeholder="Add any additional comments..."></textarea>
                                                                    </div>
                                                                    <div style="display: flex; gap: 8px;">
                                                                        <button type="submit" class="button btn-secondary" style="background: #f8d7da; color: #842029; border: 1px solid #f5c2c7;">Reject Case</button>
                                                                        <button type="button" class="button btn-secondary" onclick="closeModal('reject_<?= $case['id'] ?>')">Cancel</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 2: ACTIVE CASES -->
                        <div id="active-cases" class="tab-content">
                            <?php if (empty($activeCases)): ?>
                                <p style="color: #6b7280; text-align: center; padding: 12px 0; margin: 0;">No active cases currently.</p>
                            <?php else: ?>
                                <div style="overflow-x: auto;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; gap:8px;">
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <label style="color:#374151; font-weight:600;">Filter:</label>
                                            <select id="activeStatusFilter" onchange="filterActiveCases()" style="padding:6px 8px; font-size:13px;">
                                                <option value="all">All</option>
                                                <option value="without_reformation">Without Reformation</option>
                                                <option value="with_warning">With Warning</option>
                                                <option value="under_monitoring">Under Monitoring</option>
                                                <option value="escalated_case">Escalated Case</option>
                                                <option value="pending_case">Pending Case</option>
                                                <option value="closed_case">Closed Case</option>
                                            </select>
                                        </div>
                                        <div style="color:#6b7280; font-size:13px;">Showing <span id="activeCount"><?= count($activeCases) ?></span> cases</div>
                                    </div>
                                    <table class="cases-table">
                                        <thead>
                                            <tr>
                                                <th>Case ID</th>
                                                <th>Involved Parties</th>
                                                <th>Priority</th>
                                                <th>Resolution</th>
                                                <th>Last Update</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activeCases as $case): ?>
                                                <?php $rowResolution = $case['internal_resolution'] ?? 'none'; ?>
                                                <?php
                                                    $reportedParties = get_case_reported_persons((int)$case['id']);
                                                    $involvedNames = [];
                                                    foreach ($reportedParties as $person) {
                                                        if (!empty($person['person_name'])) {
                                                            $involvedNames[] = $person['person_name'];
                                                        } elseif (!empty($person['person_id'])) {
                                                            $involvedNames[] = $person['person_id'];
                                                        }
                                                    }
                                                    if (empty($involvedNames) && !empty($case['reported_student_name'])) {
                                                        $involvedNames[] = $case['reported_student_name'];
                                                    }
                                                    if (empty($involvedNames)) {
                                                        $involvedNames[] = 'Unknown';
                                                    }
                                                ?>
                                                <tr data-resolution="<?= htmlspecialchars($rowResolution) ?>">
                                                    <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                    <td><?= htmlspecialchars(implode(', ', array_unique($involvedNames))) ?></td>
                                                    <td><span style="font-weight: 600; font-size: 12px; padding: 2px 8px; border-radius: 3px; background: <?= ($case['priority_level'] ?? 'Medium') === 'Emergency' ? '#fca5a5' : (($case['priority_level'] ?? 'Medium') === 'High' ? '#fbbf24' : '#bfdbfe') ?>;"><?= htmlspecialchars($case['priority_level'] ?? 'Medium') ?></span></td>
                                                    <td><em><?php
                                                            $resolution = $case['internal_resolution'] ?? 'none';
                                                            if ($resolution === 'closed_case') {
                                                                echo 'Case Resolved';
                                                            } elseif ($resolution === 'none') {
                                                                echo 'None';
                                                            } else {
                                                                echo htmlspecialchars(str_replace('_', ' ', ucfirst($resolution)));
                                                            }
                                                        ?></em></td>
                                                    <td><?= date('M d, Y', strtotime($case['updated_at'] ?? $case['created_at'])) ?></td>
                                                    <td class="case-actions">
                                                        <a href="case_details.php?id=<?= $case['id'] ?>" class="button btn-secondary" style="padding: 6px 10px; font-size: 12px; text-decoration: none; display: inline-block;"><i class="fa-solid fa-eye" aria-hidden="true"></i>View Details</a>
                                                        <button type="button" class="button btn-secondary" style="padding: 6px 10px; font-size: 12px;" onclick="openModal('session_<?= $case['id'] ?>')"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>Log Session</button>
                                                        <button type="button" class="button btn-secondary" style="padding: 6px 10px; font-size: 12px;" onclick="openModal('resolution_<?= $case['id'] ?>')"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Update Resolution</button>
                                                        <button type="button" class="button btn-secondary" style="padding: 6px 10px; font-size: 12px;" onclick="openModal('note_<?= $case['id'] ?>')"><i class="fa-solid fa-note-sticky" aria-hidden="true"></i>Note</button>
                                                    </td>
                                                </tr>

                                                <!-- Log Session Modal -->
                                                <div class="modal" id="session_<?= $case['id'] ?>">
                                                    <div class="modal-content">
                                                        <div class="modal-header">Log Counseling Session</div>
                                                        <form method="post">
                                                            <input type="hidden" name="action" value="log_session">
                                                            <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
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
                                                                <button type="button" class="button btn-secondary" onclick="closeModal('session_<?= $case['id'] ?>')">Cancel</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>

                                                <!-- Resolution Modal -->
                                                <div class="modal" id="resolution_<?= $case['id'] ?>">
                                                    <div class="modal-content">
                                                        <div class="modal-header">Update Case Resolution</div>
                                                        <form method="post" enctype="multipart/form-data">
                                                            <input type="hidden" name="action" value="update_resolution">
                                                            <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                                <div class="form-group">
                                                                <label>Internal Resolution (Confidential):</label>
                                                                <select name="internal_resolution" required>
                                                                    <option value="none">None</option>
                                                                    <option value="without_reformation">Without Reformation</option>
                                                                    <option value="with_warning">With Warning</option>
                                                                    <option value="under_monitoring">Under Monitoring</option>
                                                                    <option value="escalated_case">Escalated Case</option>
                                                                    <option value="pending_case">Pending Case</option>
                                                                    <option value="other">Other</option>
                                                                    <option value="closed_case">Closed Case</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Remarks:</label>
                                                                <select name="case_remarks" required>
                                                                    <option value="">Select remark</option>
                                                                    <option value="Admission of Fault">Admission of Fault</option>
                                                                    <option value="Academic Stress">Academic Stress</option>
                                                                    <option value="Family Problems">Family Problems</option>
                                                                    <option value="Mental Health Support">Mental Health Support</option>
                                                                    <option value="Conflict Resolution">Conflict Resolution</option>
                                                                    <option value="Follow-up Scheduled">Follow-up Scheduled</option>
                                                                    <option value="Improvement Plan">Improvement Plan</option>
                                                                    <option value="Policy Violation">Policy Violation</option>
                                                                    <option value="Interpersonal Conflict">Interpersonal Conflict</option>
                                                                    <option value="Academic Concern">Academic Concern</option>
                                                                    <option value="Other">Other</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Notes:</label>
                                                                <textarea name="internal_notes" placeholder="Detailed case notes for counselor review."><?= htmlspecialchars($case['internal_notes'] ?? '') ?></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Attach Letter/Image (Multiple allowed):</label>
                                                                <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif" />
                                                                <small style="color: #6b7280; display: block; margin-top: 4px;">Allowed: PDF, DOC, DOCX, JPG, PNG, GIF (Max 10MB per file)</small>
                                                            </div>
                                                            <div style="display: flex; gap: 8px;">
                                                                <button type="submit" class="button btn-primary">Update Resolution</button>
                                                                <button type="button" class="button btn-secondary" onclick="closeModal('resolution_<?= $case['id'] ?>')">Cancel</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                                <!-- Note Modal (Active Cases) - layout similar to Log Counseling Session -->
                                                <div class="modal" id="note_<?= $case['id'] ?>">
                                                    <div class="modal-content">
                                                        <div class="modal-header">Case Note</div>
                                                        <div style="padding: 12px; max-height: 600px; overflow-y: auto;">
                                                            <div class="form-group">
                                                                <label>Case ID:</label>
                                                                <input type="text" value="<?= htmlspecialchars($case['case_number']) ?>" disabled style="width:100%; padding:8px;" />
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Resolution:</label>
                                                                <input type="text" value="<?php $res = $case['internal_resolution'] ?? 'none'; if ($res === 'closed_case') { echo 'Case Resolved'; } else { echo htmlspecialchars(str_replace('_', ' ', ucfirst($res))); } ?>" disabled style="width:100%; padding:8px;" />
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Remarks:</label>
                                                                <textarea rows="3" disabled style="width:100%; padding:8px;"><?= htmlspecialchars($case['case_remarks'] ?? 'No remarks recorded.') ?></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Notes:</label>
<?php
                                                                $notes = $case['internal_notes'] ?? '';
                                                                $notes = str_replace('Remarks: Mental Health Support', '', $notes);
                                                                $notes = trim($notes);
                                                                if ($notes === '') {
                                                                    $notes = 'No notes recorded.';
                                                                }
?>
                                                                <textarea rows="4" disabled style="width:100%; padding:8px;"><?= htmlspecialchars($notes) ?></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Attachments:</label>
<?php
                                                                $attachments = get_guidance_case_attachments($case['id']);
                                                                if (!empty($attachments)): ?>
                                                                <div style="border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; background: #f9fafb;">
<?php foreach ($attachments as $att): ?>
                                                                    <div style="display: flex; align-items: center; padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                                                        <i class="fa-solid fa-file" style="color: #6b7280; margin-right: 8px;"></i>
                                                                        <a href="<?= htmlspecialchars($att['filepath']) ?>" target="_blank" style="color: #3b82f6; text-decoration: none; flex: 1;">
                                                                            <?= htmlspecialchars($att['filename']) ?>
                                                                        </a>
                                                                        <small style="color: #9ca3af;"><?= date('M d, Y', strtotime($att['uploaded_at'])) ?></small>
                                                                    </div>
<?php endforeach; ?>
                                                                </div>
<?php else: ?>
                                                                    <p style="color: #6b7280; margin: 0;">No attachments</p>
<?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div style="display: flex; justify-content: flex-end; gap: 8px; padding: 12px;">
                                                            <button type="button" class="button btn-secondary" onclick="closeModal('note_<?= $case['id'] ?>')">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 3: CLOSED CASES -->
                        <div id="closed-cases" class="tab-content">
                            <?php if (empty($closedCases)): ?>
                                <p style="color: #6b7280; text-align: center; padding: 12px 0; margin: 0;">No closed cases yet.</p>
                            <?php else: ?>
                                <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
                                    <button type="button" class="button btn-secondary" id="toggleIndividualRemoveBtn" onclick="toggleIndividualRemove()">Remove Individual</button>
                                    <button type="button" class="button btn-secondary" id="toggleBulkRemoveBtn" onclick="toggleBulkRemove()">Remove Multiple</button>
                                    <form id="bulkRemoveForm" method="post" style="margin:0;">
                                        <input type="hidden" name="action" value="remove_multiple_cases">
                                        <button type="button" id="bulkRemoveSubmit" class="button btn-danger" style="display:none;">Delete Checked Cases</button>
                                    </form>
                                </div>
                                <div style="overflow-x: auto;">
                                    <form id="closedCasesTableForm" method="post">
                                    <input type="hidden" name="action" value="remove_multiple_cases">
                                    <table class="cases-table">
                                        <thead>
                                            <tr>
                                                <th>Case ID</th>
                                                <th>Student</th>
                                                <th>Concern</th>
                                                <th>Closed Date</th>
                                                <th>Internal Resolution</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($closedCases as $case): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($case['case_number']) ?></strong></td>
                                                    <td><?= htmlspecialchars($case['reported_student_name'] ?? 'Unknown') ?></td>
                                                    <td><?= htmlspecialchars(substr($case['type_of_concern'], 0, 20)) ?></td>
                                                    <td><?= date('M d, Y', strtotime($case['updated_at'] ?? $case['created_at'])) ?></td>
                                                    <td><em><?php
                                                            $resolution = $case['internal_resolution'] ?? 'none';
                                                            if ($resolution === 'closed_case') {
                                                                echo 'Case Resolved';
                                                            } else {
                                                                echo htmlspecialchars(str_replace('_', ' ', ucfirst($resolution)));
                                                            }
                                                        ?></em></td>
                                                    <td class="case-actions">
                                                        <button type="button" class="button btn-secondary view-btn" style="padding: 6px 10px; font-size: 12px;" onclick="openModal('closed_case_<?= $case['id'] ?>')"><i class="fa-solid fa-eye" aria-hidden="true"></i>View</button>
                                                        <button type="button" class="button btn-danger remove-icon" title="Remove" style="padding: 6px 8px; font-size: 12px; display:none;" onclick="openModal('remove_confirm_<?= $case['id'] ?>')"><i class="fa fa-trash" aria-hidden="true"></i></button>
                                                        <input type="checkbox" name="case_ids[]" value="<?= (int)$case['id'] ?>" class="bulk-checkbox" style="display:none; width:18px; height:18px; cursor:pointer;" />
                                                    </td>
                                                </tr>
                                                <div class="modal" id="closed_case_<?= $case['id'] ?>">
                                                    <div class="modal-content">
                                                        <div class="modal-header">Closed Case Details</div>
                                                        <div style="padding: 12px; max-height: 600px; overflow-y: auto;">
                                                            <div class="form-group">
                                                                <label>Case ID:</label>
                                                                <input type="text" value="<?= htmlspecialchars($case['case_number']) ?>" disabled style="width:100%; padding:8px;" />
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Student:</label>
                                                                <input type="text" value="<?= htmlspecialchars($case['reported_student_name'] ?? 'Unknown') ?>" disabled style="width:100%; padding:8px;" />
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Concern:</label>
                                                                <textarea rows="3" disabled style="width:100%; padding:8px;"><?= htmlspecialchars($case['type_of_concern']) ?></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Internal Resolution:</label>
                                                                <input type="text" value="<?php if (($case['internal_resolution'] ?? '') === 'closed_case') { echo 'Case Resolved'; } else { echo htmlspecialchars(str_replace('_', ' ', ucfirst($case['internal_resolution'] ?? 'none'))); } ?>" disabled style="width:100%; padding:8px;" />
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Closed Date:</label>
                                                                <input type="text" value="<?= date('M d, Y', strtotime($case['updated_at'] ?? $case['created_at'])) ?>" disabled style="width:100%; padding:8px;" />
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Remarks:</label>
                                                                <textarea rows="3" disabled style="width:100%; padding:8px;"><?= htmlspecialchars($case['case_remarks'] ?? 'No remarks recorded.') ?></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Notes:</label>
<?php
                                                                $notes = $case['internal_notes'] ?? '';
                                                                // Remove lines/entries that start with "Remarks:" 
                                                                $notes = preg_replace('/^Remarks:\s*[^\|]*/i', '', $notes);
                                                                // Remove lines/entries that start with "Notes:"
                                                                $notes = preg_replace('/^Notes:\s*[^\|]*/i', '', $notes);
                                                                // Remove entries separated by pipe with "Remarks:"
                                                                $notes = preg_replace('/\|\s*Remarks:\s*[^\|]*/i', '', $notes);
                                                                // Remove entries separated by pipe with "Notes:"
                                                                $notes = preg_replace('/\|\s*Notes:\s*[^\|]*/i', '', $notes);
                                                                // Clean up any extra pipes or whitespace
                                                                $notes = preg_replace('/^\|\s*/', '', $notes);
                                                                $notes = preg_replace('/\s*\|\s*$/', '', $notes);
                                                                $notes = trim($notes);
                                                                if ($notes === '' || $notes === '|') {
                                                                    $notes = 'No notes recorded.';
                                                                }
?>
                                                                <textarea rows="4" disabled style="width:100%; padding:8px;"><?= htmlspecialchars($notes) ?></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Attachments:</label>
<?php
                                                                $attachments = get_guidance_case_attachments($case['id']);
                                                                if (!empty($attachments)): ?>
                                                                <div style="border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; background: #f9fafb;">
<?php foreach ($attachments as $att): ?>
                                                                    <div style="display: flex; align-items: center; padding: 6px 0; border-bottom: 1px solid #e5e7eb;">
                                                                        <i class="fa-solid fa-file" style="color: #6b7280; margin-right: 8px;"></i>
                                                                        <a href="<?= htmlspecialchars($att['filepath']) ?>" target="_blank" style="color: #3b82f6; text-decoration: none; flex: 1;">
                                                                            <?= htmlspecialchars($att['filename']) ?>
                                                                        </a>
                                                                        <small style="color: #9ca3af;"><?= date('M d, Y', strtotime($att['uploaded_at'])) ?></small>
                                                                    </div>
<?php endforeach; ?>
                                                                </div>
<?php else: ?>
                                                                    <p style="color: #6b7280; margin: 0;">No attachments</p>
<?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div style="display: flex; justify-content: flex-end; gap: 8px; padding: 12px;">
                                                            <button type="button" class="button btn-secondary" onclick="closeModal('closed_case_<?= $case['id'] ?>')">Close</button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Remove Confirmation Modal -->
                                                <div class="modal" id="remove_confirm_<?= $case['id'] ?>">
                                                    <div class="modal-content">
                                                        <div class="modal-header">Confirm Remove</div>
                                                        <div style="padding: 12px;">
                                                            <p>Are you sure you want to remove this case from Closed Cases? This will archive the case and hide it from the list.</p>
                                                            <form method="post" style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
                                                                <input type="hidden" name="action" value="remove_case">
                                                                <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                                <button type="submit" class="button btn-danger">Remove</button>
                                                                <button type="button" class="button btn-secondary" onclick="closeModal('remove_confirm_<?= $case['id'] ?>')">Cancel</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </form>
                                </div>

                                <!-- Bulk Remove Confirmation Modal -->
                                <div class="modal" id="bulk_remove_confirm_modal">
                                    <div class="modal-content">
                                        <div class="modal-header">Confirm Bulk Remove</div>
                                        <div style="padding: 12px;">
                                            <p id="bulkRemoveMessage">Are you sure you want to remove the selected cases? This will archive them and hide them from the list.</p>
                                            <form id="bulkRemoveConfirmForm" method="post" style="display:flex; gap:8px; justify-content:flex-end; margin-top:12px;">
                                                <input type="hidden" name="action" value="remove_multiple_cases">
                                                <div id="bulkRemoveCheckboxesContainer" style="display:none;"></div>
                                                <button type="submit" class="button btn-danger">Remove All Selected</button>
                                                <button type="button" class="button btn-secondary" onclick="closeModal('bulk_remove_confirm_modal')">Cancel</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 4: MANUAL CASE CREATION -->
                        <div id="manual-case" class="tab-content">
                            <div style="width: 100%; padding: 0 10px;">
                                <h4 style="margin-bottom: 16px; color: #374151;">Create New Case Manually</h4>
                                <p style="color: #6b7280; margin-bottom: 24px; font-size: 14px;">Use this form to manually create a counseling case for a student who may need guidance support.</p>

                                <form method="post" enctype="multipart/form-data" id="manualCaseForm">
                                    <input type="hidden" name="action" value="create_manual_case">
                                    <input type="hidden" name="selected_reporters" id="selected_reporters_input" value="">
                                    <input type="hidden" name="selected_reported_persons" id="selected_reported_persons_input" value="">

                                    <!-- Reporter Information Section - FIRST -->
                                    <fieldset style="border: 1px solid #e5e7eb; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
                                        <legend style="padding: 0 8px; color: #1f2937; font-weight: 600;">Reporter Information</legend>
                                        
                                        <div class="form-group">
                                            <label for="reporter_type">Reporter Type <span style="color: #dc2626;">*</span></label>
                                            <select name="reporter_type" id="reporter_type" required onchange="handleReporterTypeChange()">
                                                <option value="">-- Select reporter type --</option>
                                                <option value="student">Student</option>
                                                <option value="teacher">Teacher</option>
                                                <option value="parent_guardian">Parent/Guardian</option>
                                                <option value="course">Course (Whole Class)</option>
                                                <option value="proactive">Proactive (Counselor-Initiated)</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>

                                        <!-- Reporter Search Section -->
                                        <div id="reporter_search_section" style="display: none;">
                                            <div class="form-group">
                                                <label>Find Reporter by Name or ID</label>
                                                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                                                    <input type="text" id="reporter_search" placeholder="Search by name or ID..." style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                                    <button type="button" class="button btn-secondary" style="padding: 8px 12px;" onclick="searchReporters()">Search</button>
                                                </div>
                                                <div id="reporter_search_results" style="display: none; border: 1px solid #e5e7eb; border-radius: 4px; max-height: 200px; overflow-y: auto; background: #f9fafb;">
                                                </div>
                                                <small style="color: #6b7280; display: block; margin-top: 4px;">Search by name or ID for students and teachers.</small>
                                            </div>
                                        </div>

                                        <!-- Manual Reporter Form -->
                                        <div id="manual_reporter_form" style="display: none; margin-top: 16px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 4px; background: #f9fafb;">
                                            <h5 style="margin-bottom: 12px; color: #374151;">Add reporter manually</h5>
                                            <div class="form-group" id="manual_reporter_name_div" style="display: none;">
                                                <label for="manual_reporter_name">Full Name:</label>
                                                <input type="text" id="manual_reporter_name" placeholder="Enter full name">
                                            </div>
                                            <div class="form-group" id="manual_reporter_id_div" style="display: none;">
                                                <label for="manual_reporter_id">ID (Optional):</label>
                                                <input type="text" id="manual_reporter_id" placeholder="Teacher ID (optional)">
                                            </div>
                                            <div class="form-group" id="manual_reporter_course_div" style="display: none;">
                                                <label for="manual_reporter_course_select">Course</label>
                                                <select id="manual_reporter_course_select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
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
                                            <div class="form-group" id="manual_reporter_ladderize_course_div" style="display: none;">
                                                <label for="manual_reporter_ladderize_course_select">Ladderize Course</label>
                                                <select id="manual_reporter_ladderize_course_select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                                    <option value="">Select ladderize course</option>
                                                    <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                                    <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                                    <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                                    <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                                                </select>
                                            </div>
                                            <div class="form-group" id="manual_reporter_course_year_div" style="display: none;">
                                                <label for="manual_reporter_year">Year Level</label>
                                                <div style="display: flex; gap: 8px;">
                                                    <select id="manual_reporter_year" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                                        <option value="">Select year</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-group" id="manual_reporter_mobile_div" style="display: none;">
                                                <label for="manual_reporter_mobile">Mobile Number:</label>
                                                <input type="tel" id="manual_reporter_mobile" placeholder="+63 912 345 6789">
                                            </div>
                                            <div class="form-group" id="manual_reporter_custom_type_div" style="display: none;">
                                                <label for="custom_reporter_type">Please specify reporter type:</label>
                                                <input type="text" name="custom_reporter_type" id="custom_reporter_type" placeholder="Specify the reporter type...">
                                            </div>
                                            <div style="display: flex; gap: 8px; margin-top: 12px;">
                                                <button type="button" class="button btn-primary" onclick="addManualReporter()">Add Reporter</button>
                                                <button type="button" class="button btn-secondary" onclick="clearManualReporterForm()">Clear</button>
                                            </div>
                                        </div>
                                    </fieldset>

                                    <fieldset style="border: 1px solid #e5e7eb; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
                                        <legend style="padding: 0 8px; color: #1f2937; font-weight: 600;">Selected Reporters</legend>
                                        <div id="selected_reporters" style="display: none; margin-top: 0;">
                                            <div id="reporters_list" style="border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; background: #f9fafb;">
                                                <!-- Reporters will be added here -->
                                            </div>
                                        </div>
                                    </fieldset>

                                    <!-- Reported Person Section -->
                                    <fieldset style="border: 1px solid #e5e7eb; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
                                        <legend style="padding: 0 8px; color: #1f2937; font-weight: 600;">Reported Person Information</legend>
                                        
                                        <div class="form-group">
                                            <label for="reported_person_type">Reported Person Type <span style="color: #dc2626;">*</span></label>
                                            <select name="reported_person_type" id="reported_person_type" required onchange="handleReportedPersonTypeChange()">
                                                <option value="">-- Select type --</option>
                                                <option value="student">Student</option>
                                                <option value="teacher">Teacher</option>
                                                <option value="course">Course (Whole Class)</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>

                                        <!-- Custom Reported Person Type Field -->
                                        <div class="form-group" id="custom_reported_div" style="display: none;">
                                            <label for="custom_reported_type">Please specify type:</label>
                                            <input type="text" name="custom_reported_type" id="custom_reported_type" placeholder="Specify the type...">
                                        </div>

                                        <!-- Reported Person Search Section -->
                                        <div id="reported_search_section" style="display: none;">
                                            <div class="form-group">
                                                <label>Find Reported Person by Name or ID</label>
                                                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                                                    <input type="text" id="reported_search" placeholder="Search by name or ID..." style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                                    <button type="button" class="button btn-secondary" style="padding: 8px 12px;" onclick="searchReportedPersons()">Search</button>
                                                </div>
                                                <div id="reported_search_results" style="display: none; border: 1px solid #e5e7eb; border-radius: 4px; max-height: 200px; overflow-y: auto; background: #f9fafb;">
                                                </div>
                                                <small style="color: #6b7280; display: block; margin-top: 4px;">Search for existing users above, or manually add reported persons below.</small>
                                            </div>
                                        </div>

                                        <!-- Manual Reported Person Form -->
                                        <div id="manual_reported_form" style="display: none; margin-top: 16px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 4px; background: #f9fafb;">
                                            <h5 style="margin-bottom: 12px; color: #374151;">Add reporter manually</h5>
                                                <div class="form-group" id="manual_reported_name_div" style="display: none;">
                                                    <label for="manual_reported_name">Full Name:</label>
                                                    <input type="text" id="manual_reported_name" placeholder="Enter full name">
                                                </div>
                                                <div class="form-group" id="manual_reported_id_div" style="display: none;">
                                                    <label for="manual_reported_id">ID (Optional):</label>
                                                    <input type="text" id="manual_reported_id" placeholder="Student ID or Employee ID">
                                                </div>
                                                <div class="form-group" id="manual_reported_course_div" style="display: none;">
                                                    <label for="manual_reported_course_select">Course</label>
                                                    <select id="manual_reported_course_select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
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
                                                <div class="form-group" id="manual_reported_ladderize_course_div" style="display: none;">
                                                    <label for="manual_reported_ladderize_course_select">Ladderize Course</label>
                                                    <select id="manual_reported_ladderize_course_select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                                        <option value="">Select ladderize course</option>
                                                        <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                                        <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                                        <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                                        <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                                                    </select>
                                                </div>
                                                <div class="form-group" id="manual_reported_course_year_div" style="display: none;">
                                                    <label for="manual_reported_year">Year Level</label>
                                                    <div style="display: flex; gap: 8px;">
                                                        <select id="manual_reported_year" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                                            <option value="">Select year</option>
                                                            <option value="1">1st Year</option>
                                                            <option value="2">2nd Year</option>
                                                            <option value="3">3rd Year</option>
                                                            <option value="4">4th Year</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="form-group" id="manual_reported_mobile_div" style="display: none;">
                                                    <label for="manual_reported_mobile">Mobile Number:</label>
                                                    <input type="tel" id="manual_reported_mobile" placeholder="+63 912 345 6789">
                                                </div>
                                                <div style="display: flex; gap: 8px; margin-top: 12px;">
                                                    <button type="button" class="button btn-primary" onclick="addManualReportedPerson()">Add Reporter</button>
                                                    <button type="button" class="button btn-secondary" onclick="clearManualReportedForm()">Clear</button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Selected Reported Persons List -->
                                        <div id="selected_reported" style="display: none; margin-top: 12px;">
                                            <h5 style="margin-bottom: 8px; color: #374151;">Selected Reported Persons:</h5>
                                            <div id="reported_list" style="border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; background: #f9fafb;">
                                                <!-- Reported persons will be added here -->
                                            </div>
                                        </div>
                                    </fieldset>

                                    <!-- Case Details Section -->
                                    <fieldset style="border: 1px solid #e5e7eb; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
                                        <legend style="padding: 0 8px; color: #1f2937; font-weight: 600;">Case Details</legend>
                                        
                                        <div class="form-group">
                                            <label for="manual_concern">Type of Concern <span style="color: #dc2626;">*</span></label>
                                            <select name="manual_concern" id="manual_concern" required>
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
                                            <label for="manual_description">Case Description <span style="color: #dc2626;">*</span></label>
                                            <textarea name="manual_description" id="manual_description" rows="4" placeholder="Provide detailed information about the student's situation and why they need counseling support..." required></textarea>
                                        </div>

                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                            <div class="form-group">
                                                <label for="priority_level">Priority Level <span style="color: #dc2626;">*</span></label>
                                                <select name="priority_level" id="priority_level" required>
                                                    <option value="Low">Low</option>
                                                    <option value="Medium" selected>Medium</option>
                                                    <option value="High">High</option>
                                                    <option value="Emergency">Emergency</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="participants">Participants <span style="color: #dc2626;">*</span></label>
                                                <select name="participants" id="participants" required>
                                                    <option value="">-- Select participants --</option>
                                                    <option value="STUDENT ONLY" <?= ($_POST['participants'] ?? '') === 'STUDENT ONLY' ? 'selected' : '' ?>>STUDENT ONLY</option>
                                                    <option value="TEACHER ONLY" <?= ($_POST['participants'] ?? '') === 'TEACHER ONLY' ? 'selected' : '' ?>>TEACHER ONLY</option>
                                                    <option value="TEACHER AND STUDENT" <?= ($_POST['participants'] ?? '') === 'TEACHER AND STUDENT' ? 'selected' : '' ?>>TEACHER AND STUDENT</option>
                                                    <option value="Student and Parent/Guardian" <?= ($_POST['participants'] ?? '') === 'Student and Parent/Guardian' ? 'selected' : '' ?>>Student and Parent/Guardian</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="initial_status">Initial Status</label>
                                            <input type="text" id="initial_status" value="Under Counseling" disabled style="background: #f3f4f6; cursor: not-allowed;">
                                            <small style="color: #6b7280;">Default status for counselor-initiated cases</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="manual_document">📎 Supporting Document (Optional)</label>
                                            <input type="file" name="manual_document" id="manual_document" accept=".pdf,.doc,.docx,.jpg,.png,.txt">
                                            <small style="color: #6b7280;">Max 5MB. Accepted: PDF, Word, images, text files</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="internal_notes">Internal Notes (For Counselor Reference)</label>
                                            <textarea name="internal_notes" id="internal_notes" rows="3" placeholder="Add any internal notes about this case that will be visible only to counselors..."></textarea>
                                        </div>
                                    </fieldset>

                                    <!-- Immediate Scheduling Section (Optional) -->
                                    <fieldset style="border: 1px solid #e5e7eb; padding: 16px; border-radius: 6px; margin-bottom: 20px;">
                                        <legend style="padding: 0 8px; color: #1f2937; font-weight: 600;">
                                            <input type="checkbox" id="schedule_session" name="schedule_session" onchange="toggleScheduleSection()" style="margin-right: 8px;">
                                            <label for="schedule_session" style="display: inline; cursor: pointer;">Schedule Initial Session (Optional)</label>
                                        </legend>

                                        <div id="schedule_section" style="display: none;">
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                                <div class="form-group">
                                                    <label for="session_date_manual">Date and Time</label>
                                                    <div style="display: flex; gap: 8px;">
                                                        <input type="date" name="session_date_manual" id="session_date_manual" min="<?= date('Y-m-d') ?>" style="flex: 1;">
                                                        <input type="time" name="session_time_manual" id="session_time_manual" min="08:00" max="17:00" value="08:00" style="flex: 1;">
                                                    </div>
                                                    <small style="color: #6b7280; display: block; margin-top: 6px;">Available time: 08:00 to 17:00 only.</small>
                                                </div>
                                                <div class="form-group">
                                                    <label for="session_mode_manual">Session Mode</label>
                                                    <select name="session_mode_manual" id="session_mode_manual" onchange="toggleLocationLabelManual()">
                                                        <option value="">-- Select mode --</option>
                                                        <option value="Face-to-Face">Face-to-Face</option>
                                                        <option value="Online">Online</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label for="location_link_manual" id="location_label_manual">Venue/Location:</label>
                                                <input type="text" name="location_link_manual" id="location_link_manual" placeholder="e.g., Guidance Office Room 101">
                                            </div>
                                        </div>
                                    </fieldset>

                                    <button type="submit" class="button btn-primary" style="width: 100%; padding: 12px;">Create Case</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 5: FEEDBACK -->
                    <div id="feedback-cases" class="tab-content">
                        <!-- Sub-tabs for Feedback Types -->
                        <div class="feedback-subtabs">
                            <button class="tab-button active" data-subtab="guidance-feedback" onclick="switchSubTab(event, 'guidance-feedback')">
                                <i class="fas fa-comment"></i> Guidance Case Feedback
                            </button>
                            <button class="tab-button" data-subtab="goodmoral-feedback" onclick="switchSubTab(event, 'goodmoral-feedback')">
                                <i class="fas fa-certificate"></i> Good Moral Feedback
                            </button>
                        </div>

                        <!-- Guidance Feedback Sub-tab -->
                        <div id="guidance-feedback" class="subtab-content active" style="display: block;">
                            <div class="inventory-card">
                                <h2>Guidance Case Feedback & Reviews</h2>
                                <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">View all feedback submitted by students for their guidance cases.</p>
                                <div style="overflow-x: auto;">
                                    <table class="borrow-table">
                                        <thead>
                                            <tr>
                                                <th>Student Name</th>
                                                <th>Case ID</th>
                                                <th>Concern Type</th>
                                                <th>Rating</th>
                                                <th>Feedback</th>
                                                <th>Date Submitted</th>
                                            </tr>
                                        </thead>
                                        <tbody id="feedback-table-body">
                                            <tr><td colspan="6" style="text-align: center; padding: 20px; color: #6b7280;">Loading feedback...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Good Moral Feedback Sub-tab -->
                        <div id="goodmoral-feedback" class="subtab-content" style="display: none;">
                            <div class="inventory-card">
                                <h2>Good Moral Certificate Feedback & Reviews</h2>
                                <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">View all feedback submitted by students for their Good Moral Certificate requests.</p>
                                <div style="overflow-x: auto;">
                                    <table class="borrow-table">
                                        <thead>
                                            <tr>
                                                <th>Student Name</th>
                                                <th>Email</th>
                                                <th>Course</th>
                                                <th>Rating</th>
                                                <th>Feedback</th>
                                                <th>Date Submitted</th>
                                            </tr>
                                        </thead>
                                        <tbody id="goodmoral-feedback-table-body">
                                            <tr><td colspan="6" style="text-align: center; padding: 20px; color: #6b7280;">Loading feedback...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(event, tabName) {
            event.preventDefault();
            
            // Find the clicked button - handle case where event.target might be an icon
            let button = event.target;
            if (!button.classList.contains('tab-button')) {
                button = button.closest('.tab-button');
            }
            
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => {
                content.classList.remove('active');
                content.style.display = 'none';
            });
            
            // Remove active class from all buttons at this level
            const buttonGroup = button.closest('.tabs');
            if (buttonGroup) {
                const buttons = buttonGroup.querySelectorAll('.tab-button');
                buttons.forEach(btn => btn.classList.remove('active'));
            }
            
            // Show selected tab and mark button as active
            const tabElement = document.getElementById(tabName);
            if (tabElement) {
                tabElement.classList.add('active');
                tabElement.style.display = 'block';
            }
            
            // Mark the clicked button as active
            if (button) {
                button.classList.add('active');
            }
            
            // Load feedback if this is the feedback tab
            if (tabName === 'feedback-cases') {
                loadCaseManagementFeedback();
            }
        }
        
        function attachTabSwipe(tabBar) {
            let startX = 0;
            let currentX = 0;
            let isTouching = false;

            tabBar.addEventListener('touchstart', function(event) {
                if (event.touches.length !== 1) return;
                startX = event.touches[0].clientX;
                currentX = startX;
                isTouching = true;
            }, { passive: true });

            tabBar.addEventListener('touchmove', function(event) {
                if (!isTouching || event.touches.length !== 1) return;
                currentX = event.touches[0].clientX;
            }, { passive: true });

            tabBar.addEventListener('touchend', function() {
                if (!isTouching) return;
                const delta = startX - currentX;
                const threshold = 60;
                if (Math.abs(delta) >= threshold) {
                    const buttons = Array.from(tabBar.querySelectorAll('.tab-button'));
                    const activeIndex = buttons.findIndex(btn => btn.classList.contains('active'));
                    if (activeIndex !== -1) {
                        const nextIndex = delta > 0 ? Math.min(buttons.length - 1, activeIndex + 1) : Math.max(0, activeIndex - 1);
                        if (nextIndex !== activeIndex) {
                            buttons[nextIndex].click();
                            buttons[nextIndex].scrollIntoView({ behavior: 'smooth', inline: 'center' });
                        }
                    }
                }
                isTouching = false;
            }, { passive: true });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tabBar = document.querySelector('.tabs');
            if (tabBar) {
                attachTabSwipe(tabBar);
            }
        });

        function openModal(id) {
            document.getElementById(id).classList.add('open');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }
        function toggleCustomReason(caseId) {
            const dropdown = document.getElementById('reject_category_' + caseId);
            const customDiv = document.getElementById('custom_reason_' + caseId);
            customDiv.style.display = dropdown.value === 'custom' ? 'block' : 'none';
        }
        
        function toggleLocationLabel(caseId) {
            const mode = document.getElementById('session_mode_' + caseId).value;
            const label = document.getElementById('location_label_' + caseId);
            const textarea = document.getElementById('location_link_' + caseId);
            if (mode === 'Face-to-Face') {
                label.textContent = 'Venue/Location:';
                textarea.placeholder = 'e.g., Guidance Office Room 101';
            } else if (mode === 'Online') {
                label.textContent = 'Meeting Link/Platform:';
                textarea.placeholder = 'e.g., Google Meet link or Zoom room';
            } else {
                label.textContent = 'Venue/Location:';
                textarea.placeholder = 'e.g., Guidance Office Room 101';
            }
        }
        
        function toggleParticipationOther(caseId) {
            const scope = document.getElementById('participation_scope_' + caseId).value;
            const otherDiv = document.getElementById('other_participation_' + caseId);
            if (scope === 'Other') {
                otherDiv.style.display = 'block';
            } else {
                otherDiv.style.display = 'none';
            }
        }

        // Manual Case Functions
        function searchPeople() {
            const searchTerm = document.getElementById('student_search').value.trim();
            if (!searchTerm || searchTerm.length < 2) {
                alert('Please enter at least 2 characters to search');
                return;
            }

            const resultsDiv = document.getElementById('search_results');
            resultsDiv.innerHTML = '<div style="padding: 8px; color: #6b7280; text-align: center;">Searching...</div>';
            resultsDiv.style.display = 'block';
            
            // AJAX call to search students and teachers
            fetch('./search_students.php?q=' + encodeURIComponent(searchTerm))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error, status = ' + response.status);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success && data.results.length > 0) {
                            let html = '<div style="padding: 8px; border-radius: 4px;">';
                            data.results.forEach(person => {
                                const roleDisplay = person.role === 'student' ? '👤 Student' : '👨‍🏫 Teacher';
                                const idValue = person.role === 'student' ? person.student_id : person.employee_id;
                                const courseValue = person.course_year;
                                
                                html += '<div style="padding: 8px; border-bottom: 1px solid #d1d5db; cursor: pointer;" onmouseover="this.style.background=\'#e5e7eb\'" onmouseout="this.style.background=\'\'" onclick="selectPerson(\'' + person.id + '\', \'' + person.full_name.replace(/'/g, "\\\\'") + '\', \'' + idValue.replace(/'/g, "\\\\'") + '\', \'' + (courseValue ? courseValue.replace(/'/g, "\\\\'") : '') + '\', \'' + person.role + '\')">';
                                html += '<strong>' + person.full_name + '</strong> <span style="color: #6b7280; font-size: 12px;">' + roleDisplay + '</span><br>';
                                html += '<small style="color: #6b7280;">ID: ' + idValue + '</small>';
                                if (courseValue) {
                                    html += '<br><small style="color: #6b7280;">Course: ' + courseValue + '</small>';
                                }
                                html += '</div>';
                            });
                            html += '</div>';
                            resultsDiv.innerHTML = html;
                            resultsDiv.style.display = 'block';
                        } else {
                            resultsDiv.innerHTML = '<div style="padding: 8px; color: #6b7280; text-align: center;">No results found</div>';
                            resultsDiv.style.display = 'block';
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Response:', text);
                        resultsDiv.innerHTML = '<div style="padding: 8px; color: #dc2626; text-align: center;">Error parsing results: ' + e.message + '</div>';
                        resultsDiv.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    resultsDiv.innerHTML = '<div style="padding: 8px; color: #dc2626; text-align: center;">Error searching: ' + error.message + '</div>';
                    resultsDiv.style.display = 'block';
                });
        }

        function selectPerson(id, name, idValue, courseValue, role) {
            document.getElementById('student_id').value = idValue;
            document.getElementById('student_name').value = name;
            document.getElementById('course_year').value = courseValue;
            document.getElementById('person_role').value = role;
            
            // Update labels based on role
            if (role === 'student') {
                document.getElementById('id_label').textContent = 'Student ID';
                document.getElementById('course_label').textContent = 'Course and Year';
                document.getElementById('course_year').placeholder = 'e.g., BS Information Technology 3rd Year';
            } else {
                document.getElementById('id_label').textContent = 'Employee ID';
                document.getElementById('course_label').textContent = 'Course';
                document.getElementById('course_year').placeholder = 'e.g., Mathematics, Science';
            }
            
            document.getElementById('search_results').style.display = 'none';
            document.getElementById('student_search').value = '';
        }

        function toggleOtherReporterField() {
            const reporterType = document.getElementById('reporter_type').value;
            const otherDiv = document.getElementById('other_reporter_div');
            if (reporterType === 'other') {
                otherDiv.style.display = 'block';
            } else {
                otherDiv.style.display = 'none';
            }
        }

        function toggleScheduleSection() {
            const checkbox = document.getElementById('schedule_session').checked;
            const section = document.getElementById('schedule_section');
            const dateInput = document.getElementById('session_date_manual');
            const timeInput = document.getElementById('session_time_manual');
            const modeInput = document.getElementById('session_mode_manual');
            section.style.display = checkbox ? 'block' : 'none';
            if (checkbox) {
                dateInput.required = true;
                timeInput.required = true;
                modeInput.required = true;
                // Initialize the location label when showing the section
                toggleLocationLabelManual();
            } else {
                dateInput.required = false;
                timeInput.required = false;
                modeInput.required = false;
            }
        }

        function toggleLocationLabelManual() {
            try {
                const modeSelect = document.getElementById('session_mode_manual');
                const label = document.getElementById('location_label_manual');
                const input = document.getElementById('location_link_manual');
                
                if (!modeSelect || !label || !input) {
                    console.error('Elements not found:', { modeSelect, label, input });
                    return;
                }
                
                const mode = modeSelect.value;
                console.log('Mode value:', mode);
                
                if (mode === 'Face-to-Face') {
                    label.textContent = 'Venue/Location:';
                    if (input) input.placeholder = 'e.g., Guidance Office Room 101';
                } else if (mode === 'Online') {
                    label.textContent = 'Meeting Link/Platform:';
                    if (input) input.placeholder = 'e.g., Google Meet link or Zoom room';
                } else {
                    label.textContent = 'Venue/Location:';
                    if (input) input.placeholder = '';
                }
            } catch (error) {
                console.error('Error in toggleLocationLabelManual:', error);
            }
        }

        // Reporter functions
        let selectedReporters = [];
        
        function handleReporterTypeChange() {
            const reporterType = document.getElementById('reporter_type').value;
            const customReporterDiv = document.getElementById('manual_reporter_custom_type_div');
            const mobileDiv = document.getElementById('manual_reporter_mobile_div');
            const nameDiv = document.getElementById('manual_reporter_name_div');
            const idDiv = document.getElementById('manual_reporter_id_div');
            const courseDiv = document.getElementById('manual_reporter_course_div');
            const courseYearDiv = document.getElementById('manual_reporter_course_year_div');
            const reporterSearchSection = document.getElementById('reporter_search_section');
            const manualReporterForm = document.getElementById('manual_reporter_form');
            const customReporterTypeInput = document.getElementById('custom_reporter_type');

            if (customReporterDiv) customReporterDiv.style.display = 'none';
            if (mobileDiv) mobileDiv.style.display = 'none';
            if (nameDiv) nameDiv.style.display = 'none';
            if (idDiv) idDiv.style.display = 'none';
            if (courseDiv) courseDiv.style.display = 'none';
            if (courseYearDiv) courseYearDiv.style.display = 'none';
            if (reporterSearchSection) reporterSearchSection.style.display = 'none';
            if (manualReporterForm) manualReporterForm.style.display = 'none';
            if (customReporterTypeInput) customReporterTypeInput.value = '';

            if (reporterType !== 'proactive') {
                // Keep all reporters, don't filter
                // const initialLength = selectedReporters.length;
                // selectedReporters = selectedReporters.filter(r => r.type !== 'proactive');
                // if (selectedReporters.length !== initialLength) {
                //     updateReportersDisplay();
                // }
            }

            if (reporterType === 'student') {
                if (reporterSearchSection) reporterSearchSection.style.display = 'block';
                if (manualReporterForm) manualReporterForm.style.display = 'block';
                if (nameDiv) nameDiv.style.display = 'block';
                if (idDiv) idDiv.style.display = 'block';
                if (courseDiv) courseDiv.style.display = 'block';
                if (courseYearDiv) courseYearDiv.style.display = 'block';
                document.getElementById('manual_reporter_ladderize_course_div').style.display = 'block';
                document.getElementById('manual_reporter_course_div').querySelector('label').textContent = 'Course';
                // Set ID label for student
                const idLabel = document.querySelector('#manual_reporter_id_div label');
                const idInput = document.getElementById('manual_reporter_id');
                if (idLabel) idLabel.textContent = 'School ID:';
                if (idInput) idInput.placeholder = 'Student ID';
            } else if (reporterType === 'teacher') {
                if (reporterSearchSection) reporterSearchSection.style.display = 'block';
                if (manualReporterForm) manualReporterForm.style.display = 'block';
                if (nameDiv) nameDiv.style.display = 'block';
                if (idDiv) idDiv.style.display = 'block';
                if (courseDiv) courseDiv.style.display = 'block';
                document.getElementById('manual_reporter_ladderize_course_div').style.display = 'none';
                if (courseYearDiv) courseYearDiv.style.display = 'none';
                document.getElementById('manual_reporter_course_div').querySelector('label').textContent = 'Course / Department';
                // Set ID label for teacher
                const idLabel = document.querySelector('#manual_reporter_id_div label');
                const idInput = document.getElementById('manual_reporter_id');
                if (idLabel) idLabel.textContent = 'Teacher ID (Optional):';
                if (idInput) idInput.placeholder = 'Teacher ID (optional)';
            } else if (reporterType === 'parent_guardian') {
                if (manualReporterForm) manualReporterForm.style.display = 'block';
                if (nameDiv) nameDiv.style.display = 'block';
                if (mobileDiv) mobileDiv.style.display = 'block';
            } else if (reporterType === 'course') {
                if (manualReporterForm) manualReporterForm.style.display = 'block';
                if (courseDiv) courseDiv.style.display = 'block';
                if (courseYearDiv) courseYearDiv.style.display = 'block';
                document.getElementById('manual_reporter_ladderize_course_div').style.display = 'none';
                document.getElementById('manual_reporter_course_div').querySelector('label').textContent = 'Course / Subject';
            } else if (reporterType === 'proactive') {
                if (manualReporterForm) manualReporterForm.style.display = 'none';
                // Add proactive if not already present
                const hasProactive = selectedReporters.some(r => r.type === 'proactive');
                if (!hasProactive) {
                    selectedReporters.push({
                        type: 'proactive',
                        name: 'Guidance Counselor',
                        id: '',
                        course: '',
                        mobile: ''
                    });
                }
                updateReportersDisplay();
            } else if (reporterType === 'other') {
                if (manualReporterForm) manualReporterForm.style.display = 'block';
                if (nameDiv) nameDiv.style.display = 'block';
                if (mobileDiv) mobileDiv.style.display = 'block';
                if (customReporterDiv) customReporterDiv.style.display = 'block';
            }

            const courseSelect = document.getElementById('manual_reporter_course_select');
            const ladderizeSelect = document.getElementById('manual_reporter_ladderize_course_select');
            if (courseSelect) courseSelect.value = '';
            if (ladderizeSelect) ladderizeSelect.value = '';
            populateManualReporterYearOptions('');
            updateReportersDisplay();
            const selectedDiv = document.getElementById('selected_reporters');
            if (selectedDiv && selectedReporters.length > 0) {
                selectedDiv.style.display = 'block';
            }
        }

        function searchReporters() {
            const searchTerm = document.getElementById('reporter_search').value.trim();
            if (!searchTerm || searchTerm.length < 2) {
                alert('Please enter at least 2 characters to search');
                return;
            }

            const resultsDiv = document.getElementById('reporter_search_results');
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
                                
                                html += '<div style="padding: 8px; border-bottom: 1px solid #d1d5db; cursor: pointer;" onmouseover="this.style.background=\'#e5e7eb\'" onmouseout="this.style.background=\'\'" onclick="selectReporter(\'' + person.id + '\', \'' + person.full_name.replace(/'/g, "\\\\'") + '\', \'' + idValue.replace(/'/g, "\\\\'") + '\', \'' + (courseValue ? courseValue.replace(/'/g, "\\\\'") : '') + '\', \'' + person.role + '\')">';
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

        function selectReporter(id, name, idValue, courseValue, role) {
            const reporterType = document.getElementById('reporter_type').value;
            
            selectedReporters.push({
                type: reporterType,
                name: name,
                id: idValue,
                course: courseValue,
                mobile: ''
            });
            
            updateReportersDisplay();
            const selectedDiv = document.getElementById('selected_reporters');
            if (selectedDiv && selectedReporters.length > 0) {
                selectedDiv.style.display = 'block';
            }
            document.getElementById('reporter_search').value = '';
            document.getElementById('reporter_search_results').style.display = 'none';
        }

        function addManualReporter() {
            const reporterType = document.getElementById('reporter_type').value;
            const name = document.getElementById('manual_reporter_name').value.trim();
            const id = document.getElementById('manual_reporter_id').value.trim();
            const courseSelect = document.getElementById('manual_reporter_course_select');
            const ladderizeCourseSelect = document.getElementById('manual_reporter_ladderize_course_select');
            const yearSelect = document.getElementById('manual_reporter_year');
            const mobile = document.getElementById('manual_reporter_mobile').value.trim();
            const customType = document.getElementById('custom_reporter_type').value.trim();
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

            selectedReporters.push(reporter);
            updateReportersDisplay();
            const selectedDiv = document.getElementById('selected_reporters');
            if (selectedDiv && selectedReporters.length > 0) {
                selectedDiv.style.display = 'block';
            }
            clearManualReporterForm();
        }

        function updateReportersDisplay() {
            const listDiv = document.getElementById('reporters_list');
            const selectedDiv = document.getElementById('selected_reporters');
            
            if (selectedReporters.length === 0) {
                selectedDiv.style.display = 'none';
                return;
            }
            
            selectedDiv.style.display = 'block';
            let html = '';
            selectedReporters.forEach((reporter, index) => {
                html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 1px solid #e5e7eb;">';
                html += '<div>';
                html += '<strong>' + reporter.name + '</strong>';
                if (reporter.id) html += ' (ID: ' + reporter.id + ')';
                if (reporter.course) html += ' - ' + reporter.course;
                if (reporter.mobile) html += ' 📱 ' + reporter.mobile;
                html += ' <small style="color: #6b7280;">[' + reporter.type + ']</small>';
                html += '</div>';
                html += '<button type="button" class="button btn-danger" style="padding: 2px 6px; font-size: 12px;" onclick="removeReporter(' + index + ')">Remove</button>';
                html += '</div>';
            });
            listDiv.innerHTML = html;
        }

        function removeReporter(index) {
            selectedReporters.splice(index, 1);
            updateReportersDisplay();
        }

        function clearManualReporterForm() {
            document.getElementById('manual_reporter_name').value = '';
            document.getElementById('manual_reporter_id').value = '';
            document.getElementById('manual_reporter_mobile').value = '';
            document.getElementById('manual_reporter_course_select').value = '';
            document.getElementById('manual_reporter_ladderize_course_select').value = '';
            document.getElementById('manual_reporter_year').value = '';
            document.getElementById('custom_reporter_type').value = '';
            populateManualReporterYearOptions('');
        }

        function populateManualReporterYearOptions(courseValue) {
            const yearSelect = document.getElementById('manual_reporter_year');
            if (!yearSelect) return;
            const twoYearPrograms = ['Associate in Computer Technology', 'Associate in Business Knowledge', 'Associate in Hospitality Management', 'Associate in Tourism Management'];
            const years = twoYearPrograms.includes(courseValue) ? ['1st Year', '2nd Year'] : ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Graduate'];
            const currentValue = yearSelect.value;
            yearSelect.innerHTML = '<option value="">Select year</option>' + years.map(year => `\n                <option value="${year}"${currentValue === String(year) ? ' selected' : ''}>${year}</option>`).join('');
        }

        document.getElementById('manual_reporter_course_select').addEventListener('change', function () {
            const ladderSelect = document.getElementById('manual_reporter_ladderize_course_select');
            if (this.value) {
                ladderSelect.value = '';
                ladderSelect.disabled = true;
                populateManualReporterYearOptions(this.value);
            } else {
                ladderSelect.disabled = false;
                populateManualReporterYearOptions('');
            }
        });

        document.getElementById('manual_reporter_ladderize_course_select').addEventListener('change', function () {
            const courseSelect = document.getElementById('manual_reporter_course_select');
            if (this.value) {
                courseSelect.value = '';
                courseSelect.disabled = true;
                populateManualReporterYearOptions(this.value);
            } else {
                courseSelect.disabled = false;
                populateManualReporterYearOptions('');
            }
        });

        // Reported Person functions
        let selectedReportedPersons = [];
        
        function handleReportedPersonTypeChange() {
            const reportedType = document.getElementById('reported_person_type').value;
            const customReportedDiv = document.getElementById('custom_reported_div');
            const reportedSearchSection = document.getElementById('reported_search_section');
            const nameDiv = document.getElementById('manual_reported_name_div');
            const idDiv = document.getElementById('manual_reported_id_div');
            const courseDiv = document.getElementById('manual_reported_course_div');
            const courseYearDiv = document.getElementById('manual_reported_course_year_div');
            const mobileDiv = document.getElementById('manual_reported_mobile_div');
            const manualReportedForm = document.getElementById('manual_reported_form');

            // Reset all displays
            customReportedDiv.style.display = 'none';
            reportedSearchSection.style.display = 'none';
            if (nameDiv) nameDiv.style.display = 'none';
            if (idDiv) idDiv.style.display = 'none';
            if (courseDiv) courseDiv.style.display = 'none';
            if (courseYearDiv) courseYearDiv.style.display = 'none';
            if (mobileDiv) mobileDiv.style.display = 'none';
            if (manualReportedForm) manualReportedForm.style.display = 'none';

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
                    document.getElementById('manual_reported_ladderize_course_div').style.display = 'block';
                    document.getElementById('manual_reported_course_div').querySelector('label').textContent = 'Course';
                    // Set ID label for student
                    const idLabel = document.querySelector('#manual_reported_id_div label');
                    const idInput = document.getElementById('manual_reported_id');
                    if (idLabel) idLabel.textContent = 'School ID:';
                    if (idInput) idInput.placeholder = 'Student ID';
                } else if (reportedType === 'teacher') {
                    if (nameDiv) nameDiv.style.display = 'block';
                    if (idDiv) idDiv.style.display = 'block';
                    if (courseDiv) courseDiv.style.display = 'block';
                    document.getElementById('manual_reported_ladderize_course_div').style.display = 'none';
                    if (courseYearDiv) courseYearDiv.style.display = 'none';
                    document.getElementById('manual_reported_course_div').querySelector('label').textContent = 'Course / Department';
                    // Set ID label for teacher
                    const idLabel = document.querySelector('#manual_reported_id_div label');
                    const idInput = document.getElementById('manual_reported_id');
                    if (idLabel) idLabel.textContent = 'Teacher ID (Optional):';
                    if (idInput) idInput.placeholder = 'Teacher ID (optional)';
                } else if (reportedType === 'course') {
                    if (manualReportedForm) manualReportedForm.style.display = 'block';
                    if (courseDiv) courseDiv.style.display = 'block';
                    if (courseYearDiv) courseYearDiv.style.display = 'block';
                    document.getElementById('manual_reported_ladderize_course_div').style.display = 'none';
                    document.getElementById('manual_reported_course_div').querySelector('label').textContent = 'Course / Subject';
                    // Hide search section for course type
                    reportedSearchSection.style.display = 'none';
                } else if (reportedType === 'other') {
                    if (nameDiv) nameDiv.style.display = 'block';
                    if (mobileDiv) mobileDiv.style.display = 'block';
                    customReportedDiv.style.display = 'block';
                }
            }

            const courseSelect = document.getElementById('manual_reported_course_select');
            const ladderizeSelect = document.getElementById('manual_reported_ladderize_course_select');
            if (courseSelect) courseSelect.value = '';
            if (ladderizeSelect) ladderizeSelect.value = '';
            populateManualReportedYearOptions('');
        }

        function searchReportedPersons() {
            const searchTerm = document.getElementById('reported_search').value.trim();
            if (!searchTerm || searchTerm.length < 2) {
                alert('Please enter at least 2 characters to search');
                return;
            }

            const resultsDiv = document.getElementById('reported_search_results');
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
                                
                                html += '<div style="padding: 8px; border-bottom: 1px solid #d1d5db; cursor: pointer;" onmouseover="this.style.background=\'#e5e7eb\'" onmouseout="this.style.background=\'\'" onclick="selectReportedPerson(\'' + person.id + '\', \'' + person.full_name.replace(/'/g, "\\\\'") + '\', \'' + idValue.replace(/'/g, "\\\\'") + '\', \'' + (courseValue ? courseValue.replace(/'/g, "\\\\'") : '') + '\', \'' + person.role + '\')">';
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

        function selectReportedPerson(userId, name, externalId, courseValue, role) {
            const reportedType = document.getElementById('reported_person_type').value;
            
            selectedReportedPersons.push({
                type: reportedType,
                name: name,
                user_id: userId || null,
                id: userId || externalId || null,
                external_id: externalId || '',
                course: courseValue
            });
            
            updateReportedPersonsDisplay();
            document.getElementById('reported_search').value = '';
            document.getElementById('reported_search_results').style.display = 'none';
        }

        function addManualReportedPerson() {
            const reportedType = document.getElementById('reported_person_type').value;
            const name = document.getElementById('manual_reported_name').value.trim();
            const id = document.getElementById('manual_reported_id').value.trim();
            const courseSelect = document.getElementById('manual_reported_course_select');
            const ladderizeCourseSelect = document.getElementById('manual_reported_ladderize_course_select');
            const yearSelect = document.getElementById('manual_reported_year');
            const mobile = document.getElementById('manual_reported_mobile').value.trim();
            const customType = document.getElementById('custom_reported_type').value.trim();
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
                user_id: null,
                id: '',
                external_id: '',
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
                person.external_id = id;
                person.id = id;
                person.course = selectedCourse + ' / ' + selectedYear;
            } else if (reportedType === 'course') {
                const selectedYear = yearSelect ? yearSelect.value : '';
                if (!courseValue || !selectedYear) {
                    alert('Please select a course and year.');
                    return;
                }
                person.name = courseValue + ' - ' + selectedYear;
                person.id = courseValue + '_' + selectedYear;
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
                person.external_id = id;
                person.id = id || 'other_' + Date.now();
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
                person.external_id = id;
                person.id = id;
                person.course = selectedCourse;
            }

            selectedReportedPersons.push(person);
            updateReportedPersonsDisplay();
            clearManualReportedForm();
        }

        function updateReportedPersonsDisplay() {
            const listDiv = document.getElementById('reported_list');
            const selectedDiv = document.getElementById('selected_reported');
            
            if (selectedReportedPersons.length === 0) {
                selectedDiv.style.display = 'none';
                return;
            }
            
            selectedDiv.style.display = 'block';
            let html = '';
            selectedReportedPersons.forEach((person, index) => {
                html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 1px solid #e5e7eb;">';
                html += '<div>';
                html += '<strong>' + person.name + '</strong>';
                const displayId = person.external_id || person.user_id || '';
                if (displayId) html += ' (ID: ' + displayId + ')';
                if (person.course) html += ' - ' + person.course;
                html += ' <small style="color: #6b7280;">[' + person.type + ']</small>';
                html += '</div>';
                html += '<button type="button" class="button btn-danger" style="padding: 2px 6px; font-size: 12px;" onclick="removeReportedPerson(' + index + ')">Remove</button>';
                html += '</div>';
            });
            listDiv.innerHTML = html;
        }

        function removeReportedPerson(index) {
            selectedReportedPersons.splice(index, 1);
            updateReportedPersonsDisplay();
        }

        function showManualReportedForm() {
            document.getElementById('manual_reported_form').style.display = 'block';
        }

        function clearManualReportedForm() {
            document.getElementById('manual_reported_name').value = '';
            document.getElementById('manual_reported_id').value = '';
            document.getElementById('manual_reported_mobile').value = '';
            document.getElementById('manual_reported_course_select').value = '';
            document.getElementById('manual_reported_ladderize_course_select').value = '';
            document.getElementById('manual_reported_year').value = '';
            populateManualReportedYearOptions('');
        }

        function populateManualReportedYearOptions(courseValue) {
            const yearSelect = document.getElementById('manual_reported_year');
            if (!yearSelect) return;
            const twoYearPrograms = ['Associate in Computer Technology', 'Associate in Business Knowledge', 'Associate in Hospitality Management', 'Associate in Tourism Management'];
            const years = twoYearPrograms.includes(courseValue) ? ['1st Year', '2nd Year'] : ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Graduate'];
            const currentValue = yearSelect.value;
            yearSelect.innerHTML = '<option value="">Select year</option>' + years.map(year => `\n                <option value="${year}"${currentValue === String(year) ? ' selected' : ''}>${year}</option>`).join('');
        }

        document.getElementById('manual_reported_course_select').addEventListener('change', function () {
            const ladderSelect = document.getElementById('manual_reported_ladderize_course_select');
            if (this.value) {
                ladderSelect.value = '';
                ladderSelect.disabled = true;
                populateManualReportedYearOptions(this.value);
            } else {
                ladderSelect.disabled = false;
                populateManualReportedYearOptions('');
            }
        });

        document.getElementById('manual_reported_ladderize_course_select').addEventListener('change', function () {
            const courseSelect = document.getElementById('manual_reported_course_select');
            if (this.value) {
                courseSelect.value = '';
                courseSelect.disabled = true;
                populateManualReportedYearOptions(this.value);
            } else {
                courseSelect.disabled = false;
                populateManualReportedYearOptions('');
            }
        });

        // Form submission handler
        document.getElementById('manualCaseForm').addEventListener('submit', function(e) {
            // Populate hidden fields with JSON data
            document.getElementById('selected_reporters_input').value = JSON.stringify(selectedReporters);
            document.getElementById('selected_reported_persons_input').value = JSON.stringify(selectedReportedPersons);
            
            // Validate that we have at least one reporter and one reported person
            if (selectedReporters.length === 0 && document.getElementById('reporter_type').value !== 'proactive') {
                e.preventDefault();
                alert('Please add at least one reporter.');
                return false;
            }
            
            if (selectedReportedPersons.length === 0) {
                e.preventDefault();
                alert('Please add at least one reported person.');
                return false;
            }
        });
        
        // Auto-switch to tab if specified in URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabButton = document.querySelector(`[data-tab="${tabParam}"]`);
                if (tabButton) {
                    // Create a synthetic event
                    const event = new Event('click', { bubbles: true });
                    event.preventDefault = function() {};
                    // Manually call switchTab
                    switchTab({ target: tabButton, preventDefault: function() {} }, tabParam);
                }
            }

            // Setup bulk/individual remove interactions
            const bulkBtn = document.getElementById('bulkRemoveSubmit');
            if (bulkBtn) {
                bulkBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Get all checked checkboxes
                    const checkedBoxes = Array.from(document.querySelectorAll('.bulk-checkbox:checked'));
                    if (checkedBoxes.length === 0) {
                        alert('Please select at least one case to remove.');
                        return;
                    }
                    // Update confirmation message
                    const msg = document.getElementById('bulkRemoveMessage');
                    if (msg) msg.textContent = 'Are you sure you want to remove ' + checkedBoxes.length + ' selected case(s)? This will archive them and hide them from the list.';
                    // Copy checkboxes to confirmation form
                    const container = document.getElementById('bulkRemoveCheckboxesContainer');
                    if (container) {
                        container.innerHTML = '';
                        checkedBoxes.forEach(cb => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'case_ids[]';
                            input.value = cb.value;
                            container.appendChild(input);
                        });
                    }
                    // Show confirmation modal
                    openModal('bulk_remove_confirm_modal');
                });
            }

            // When any checkbox changes, toggle the bulk remove button visibility
            document.querySelectorAll('.bulk-checkbox').forEach(cb => {
                cb.addEventListener('change', function() {
                    const anyChecked = Array.from(document.querySelectorAll('.bulk-checkbox')).some(c => c.checked);
                    const btn = document.getElementById('bulkRemoveSubmit');
                    if (btn) btn.style.display = anyChecked ? 'inline-block' : 'none';
                });
            });
        });

        // Toggle functions for remove UI
        function toggleIndividualRemove() {
            const icons = document.querySelectorAll('.remove-icon');
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            // hide checkboxes when entering individual mode
            checkboxes.forEach(c => c.style.display = 'none');
            icons.forEach(i => {
                i.style.display = (i.style.display === 'inline-block') ? 'none' : 'inline-block';
            });
            // hide bulk remove submit and uncheck all
            const bulkBtn = document.getElementById('bulkRemoveSubmit'); if (bulkBtn) bulkBtn.style.display = 'none';
            checkboxes.forEach(c => c.checked = false);
            // close bulk confirmation modal
            closeModal('bulk_remove_confirm_modal');
        }

        function toggleBulkRemove() {
            const icons = document.querySelectorAll('.remove-icon');
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            // hide remove icons when entering bulk mode
            icons.forEach(i => i.style.display = 'none');
            // toggle checkbox visibility
            const anyHidden = Array.from(checkboxes).some(c => c.style.display === 'none');
            checkboxes.forEach(c => c.style.display = anyHidden ? 'inline-block' : 'none');
            // ensure bulk button hidden until a checkbox is selected
            const bulkBtn = document.getElementById('bulkRemoveSubmit'); if (bulkBtn) bulkBtn.style.display = 'none';
            checkboxes.forEach(c => c.checked = false);
            // close any open individual remove modals
            document.querySelectorAll('[id^="remove_confirm_"]').forEach(modal => closeModal(modal.id));
        }

    </script>
    <script>
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
    </script>
</body>
</html>
