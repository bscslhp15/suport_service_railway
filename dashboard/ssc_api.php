<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Initialize database connection
$conn = get_db();
$conn->exec("ALTER TABLE ssc_events MODIFY COLUMN event_date DATE NULL");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = current_user();
$action = $_GET['action'] ?? '';

// Only allow SSC head users to modify SSC data
$writeActions = [
    'save_event',
    'delete_event',
    'save_candidate',
    'delete_candidate',
    'save_initiative',
    'delete_initiative',
    'save_election_date',
    'save_officer',
    'delete_officer'
];

if (in_array($action, $writeActions, true)) {
    if (!(
        ($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship') ||
        $user['role'] === 'admin'
    )) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

switch ($action) {
    // Events CRUD
    case 'get_events':
        getEvents();
        break;
    case 'save_event':
        saveEvent();
        break;
    case 'delete_event':
        deleteEvent();
        break;

    // Candidates CRUD
    case 'get_candidates':
        getCandidates();
        break;
    case 'save_candidate':
        saveCandidate();
        break;
    case 'delete_candidate':
        deleteCandidate();
        break;

    // Initiatives CRUD
    case 'get_initiatives':
        getInitiatives();
        break;
    case 'save_initiative':
        saveInitiative();
        break;
    case 'delete_initiative':
        deleteInitiative();
        break;

    // Election Settings
    case 'get_election_date':
        getElectionDate();
        break;
    case 'save_election_date':
        saveElectionDate();
        break;

    // Officers
    case 'get_officers':
        getOfficers();
        break;
    case 'get_officer_years':
        getOfficerYears();
        break;
    case 'save_officer':
        saveOfficer();
        break;
    case 'delete_officer':
        deleteOfficer();
        break;

    // Reports
    case 'get_reports':
        getReports();
        break;

    // Feedback
    case 'get_all_feedback':
        getAllSscFeedback();
        break;
    case 'get_feedback_stats':
        getSscFeedbackStats();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getEvents() {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM ssc_events ORDER BY event_date ASC, created_at DESC");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get photos for each event
    foreach ($events as &$event) {
        $photoStmt = $conn->prepare("SELECT photo_url FROM ssc_event_photos WHERE event_id = ? ORDER BY created_at ASC");
        $photoStmt->execute([$event['id']]);
        $photos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);

        // Backward compatibility: if no photos in new table, use old photo_url field
        if (empty($photos) && !empty($event['photo_url'])) {
            $photos = [$event['photo_url']];
        }

        $event['photos'] = $photos;
    }

    echo json_encode(['success' => true, 'events' => $events]);
}

function saveEvent() {
    global $conn, $user;

    $data = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
        $data = $_POST;
    } else {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    if (empty($data['title'])) {
        echo json_encode(['success' => false, 'message' => 'Event title is required']);
        return;
    }

    $eventDate = trim((string)($data['event_date'] ?? ''));
    if ($eventDate === '') {
        $eventDate = null;
    }
    $eventTime = trim((string)($data['event_time'] ?? ''));
    if ($eventTime === '') {
        $eventTime = null;
    }

    $eventType = $data['event_type'] ?? 'event';
    
    $conn->beginTransaction();
    try {
        if (!empty($data['id'])) {
            // Update existing event
            $stmt = $conn->prepare("UPDATE ssc_events SET
                title = ?, description = ?, event_date = ?, event_time = ?,
                location = ?, organizer = ?, status = ?, event_type = ?, updated_at = NOW()
                WHERE id = ? AND created_by = ?");

            $result = $stmt->execute([
                $data['title'], $data['description'] ?? '', $eventDate,
                $eventTime, $data['location'] ?? '', $data['organizer'] ?? '',
                $data['status'] ?? 'upcoming', $eventType, $data['id'], $user['id']
            ]);

            $eventId = $data['id'];
        } else {
            // Create new event
            $stmt = $conn->prepare("INSERT INTO ssc_events
                (title, description, event_date, event_time, location, organizer, status, event_type, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $result = $stmt->execute([
                $data['title'], $data['description'] ?? '', $eventDate,
                $eventTime, $data['location'] ?? '', $data['organizer'] ?? '',
                $data['status'] ?? 'upcoming', $eventType, $user['id']
            ]);

            $eventId = $conn->lastInsertId();
        }

        if (!$result) {
            throw new Exception('Failed to save event');
        }

        // Handle removals of existing event photos when editing
        if (!empty($data['id']) && !empty($_POST['removed_photos']) && is_array($_POST['removed_photos'])) {
            foreach ($_POST['removed_photos'] as $removedPhoto) {
                $removedPhoto = trim((string)$removedPhoto);
                if ($removedPhoto === '') {
                    continue;
                }

                $deleteStmt = $conn->prepare("DELETE FROM ssc_event_photos WHERE event_id = ? AND photo_url = ?");
                $deleteStmt->execute([$eventId, $removedPhoto]);

                $filePath = __DIR__ . '/../' . ltrim($removedPhoto, '/\\');
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        // Handle multiple photo uploads
        if (!empty($_FILES['event_photos'])) {
            $photos = $_FILES['event_photos'];
            if (is_array($photos['name'])) {
                // Multiple files
                foreach ($photos['name'] as $key => $name) {
                    if ($photos['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $photos['name'][$key],
                            'type' => $photos['type'][$key],
                            'tmp_name' => $photos['tmp_name'][$key],
                            'error' => $photos['error'][$key],
                            'size' => $photos['size'][$key]
                        ];
                        $uploadedPhoto = save_ssc_event_photo_upload($file);
                        if ($uploadedPhoto !== null) {
                            $stmt = $conn->prepare("INSERT INTO ssc_event_photos (event_id, photo_url) VALUES (?, ?)");
                            $stmt->execute([$eventId, $uploadedPhoto]);
                        }
                    }
                }
                // Clear old photo_url since we're using new system
                $clearStmt = $conn->prepare("UPDATE ssc_events SET photo_url = NULL WHERE id = ?");
                $clearStmt->execute([$eventId]);
            } elseif ($photos['error'] === UPLOAD_ERR_OK) {
                // Single file
                $uploadedPhoto = save_ssc_event_photo_upload($photos);
                if ($uploadedPhoto !== null) {
                    $stmt = $conn->prepare("INSERT INTO ssc_event_photos (event_id, photo_url) VALUES (?, ?)");
                    $stmt->execute([$eventId, $uploadedPhoto]);
                    // Clear old photo_url
                    $clearStmt = $conn->prepare("UPDATE ssc_events SET photo_url = NULL WHERE id = ?");
                    $clearStmt->execute([$eventId]);
                }
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Event saved successfully']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to save event: ' . $e->getMessage()]);
    }
}

function deleteEvent() {
    global $conn, $user;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        return;
    }

    // Admins can delete any event, SSC heads can only delete their own events
    if ($user['role'] === 'admin') {
        $stmt = $conn->prepare("DELETE FROM ssc_events WHERE id = ?");
        $result = $stmt->execute([$data['id']]);
    } else {
        $stmt = $conn->prepare("DELETE FROM ssc_events WHERE id = ? AND created_by = ?");
        $result = $stmt->execute([$data['id'], $user['id']]);
    }

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete event']);
    }
}

function getCandidates() {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM ssc_candidates ORDER BY created_at DESC");
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adjust photo_url for correct path - return relative to root
    foreach ($candidates as &$candidate) {
        if (!empty($candidate['photo_url'])) {
            $candidate['photo_url'] = $candidate['photo_url']; // Keep as is, frontend will add correct prefix
        }
    }

    echo json_encode(['success' => true, 'candidates' => $candidates]);
}

function saveCandidate() {
    global $conn, $user;

    try {
        $input = file_get_contents('php://input');
        $jsonData = null;
        if (!empty($input) && strpos(trim($input), '{') === 0) {
            $jsonData = json_decode($input, true);
        }

        // If only ID and status are provided (no full_name or position), do a status-only update
        if (is_array($jsonData) && isset($jsonData['id']) && isset($jsonData['status']) && !isset($jsonData['full_name']) && !isset($jsonData['position'])) {
            $stmt = $conn->prepare("UPDATE ssc_candidates SET status = ?, updated_at = NOW() WHERE id = ? AND created_by = ?");
            $result = $stmt->execute([$jsonData['status'], $jsonData['id'], $user['id']]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
            return;
        }

        // Handle full updates via form post or JSON payload
        $data = !empty($_POST) ? $_POST : ($jsonData ?? []);
        $photo_url = '';

        // Handle file upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../IMG ASSETS/ssc_candidates/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
            $uploadFile = $uploadDir . $fileName;

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($_FILES['photo']['type'], $allowedTypes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
                return;
            }

            // Validate file size (max 5MB)
            if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                return;
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
                $photo_url = 'IMG ASSETS/ssc_candidates/' . $fileName;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload photo']);
                return;
            }
        }

        if (empty($data['full_name']) || empty($data['position'])) {
            echo json_encode(['success' => false, 'message' => 'Name and position are required']);
            return;
        }

        if (isset($data['id']) && !empty($data['id'])) {
            // Update existing candidate
            $stmt = $conn->prepare("UPDATE ssc_candidates SET
                full_name = ?, position = ?, course_year = ?, platform = ?,
                photo_url = COALESCE(?, photo_url), status = ?, officer_year = ?, updated_at = NOW()
                WHERE id = ? AND created_by = ?");

            $result = $stmt->execute([
                $data['full_name'], $data['position'], $data['course_year'] ?? '',
                $data['platform'] ?? '', $photo_url ?: null,
                $data['status'] ?? 'active', $data['officer_year'] ?? null, $data['id'], $user['id']
            ]);
        } else {
            // Create new candidate
            $stmt = $conn->prepare("INSERT INTO ssc_candidates
                (full_name, position, course_year, platform, photo_url, status, officer_year, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $result = $stmt->execute([
                $data['full_name'], $data['position'], $data['course_year'] ?? '',
                $data['platform'] ?? '', $photo_url ?: null,
                    $data['status'] ?? 'active', $data['officer_year'] ?? null, $user['id']
            ]);
        
                error_log("Saving new candidate: full_name={$data['full_name']}, status={$data['status']}, officer_year={$data['officer_year']}, created_by={$user['id']}, result=$result");
        }

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Candidate saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save candidate']);
        }
    } catch (Exception $e) {
        error_log('ssc_api saveCandidate error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error saving candidate: ' . $e->getMessage()]);
    }
}

function deleteCandidate() {
    global $conn, $user;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Candidate ID is required']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM ssc_candidates WHERE id = ? AND created_by = ?");
    $result = $stmt->execute([$data['id'], $user['id']]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Candidate deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete candidate']);
    }
}

function getInitiatives() {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM ssc_initiatives ORDER BY created_at DESC");
    $stmt->execute();
    $initiatives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'initiatives' => $initiatives]);
}

function saveInitiative() {
    global $conn, $user;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['title'])) {
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        return;
    }

    if (isset($data['id']) && !empty($data['id'])) {
        // Update existing initiative
        $stmt = $conn->prepare("UPDATE ssc_initiatives SET
            title = ?, description = ?, category = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND created_by = ?");

        $result = $stmt->execute([
            $data['title'], $data['description'] ?? '', $data['category'] ?? '',
            $data['status'] ?? 'planning', $data['id'], $user['id']
        ]);
    } else {
        // Create new initiative
        $stmt = $conn->prepare("INSERT INTO ssc_initiatives
            (title, description, category, status, created_by)
            VALUES (?, ?, ?, ?, ?)");

        $result = $stmt->execute([
            $data['title'], $data['description'] ?? '', $data['category'] ?? '',
            $data['status'] ?? 'planning', $user['id']
        ]);
    }

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Initiative saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save initiative']);
    }
}

function deleteInitiative() {
    global $conn, $user;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Initiative ID is required']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM ssc_initiatives WHERE id = ? AND created_by = ?");
    $result = $stmt->execute([$data['id'], $user['id']]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Initiative deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete initiative']);
    }
}

function getElectionDate() {
    global $conn;
    
    // Try to get from a ssc_settings table or return empty
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM ssc_settings WHERE setting_key = 'election_date' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['setting_value'])) {
            echo json_encode(['success' => true, 'election_date' => $result['setting_value']]);
        } else {
            echo json_encode(['success' => true, 'election_date' => null]);
        }
    } catch (Exception $e) {
        // Table might not exist, return empty
        echo json_encode(['success' => true, 'election_date' => null]);
    }
}

function saveElectionDate() {
    global $conn, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['election_date'])) {
        echo json_encode(['success' => false, 'message' => 'Election date is required']);
        return;
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['election_date'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        return;
    }
    
    try {
        // Try to update or insert into ssc_settings table
        $stmt = $conn->prepare("INSERT INTO ssc_settings (setting_key, setting_value, updated_at) 
                               VALUES ('election_date', ?, NOW())
                               ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $result = $stmt->execute([$data['election_date'], $data['election_date']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Election date saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save election date']);
        }
    } catch (Exception $e) {
        // Table might not exist, try creating it
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS ssc_settings (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value VARCHAR(255),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            
            // Now try to insert again
            $stmt = $conn->prepare("INSERT INTO ssc_settings (setting_key, setting_value, updated_at) 
                                   VALUES ('election_date', ?, NOW())
                                   ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $result = $stmt->execute([$data['election_date'], $data['election_date']]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Election date saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save election date']);
            }
        } catch (Exception $e2) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e2->getMessage()]);
        }
    }
}

function getOfficers() {
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT id, position, officer_name, order_sequence FROM ssc_officers ORDER BY order_sequence ASC, position ASC");
        $stmt->execute();
        $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'officers' => $officers
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving officers',
            'error' => $e->getMessage()
        ]);
    }
}

function getOfficerYears() {
    global $conn;

    try {
        $currentYear = date('Y');
        $currentAcademicYear = ($currentYear - 1) . '-' . $currentYear;
        
        // Get distinct years from ssc_candidates where status is 'elected' or 'advicer'
        $stmt = $conn->prepare("SELECT DISTINCT officer_year AS year FROM ssc_candidates WHERE status IN ('elected', 'advicer', 'previous') AND officer_year IS NOT NULL AND officer_year <> '' ORDER BY year DESC");
        $stmt->execute();
        $yearResults = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $years = [];
        $hasPresent = false;
        
        foreach ($yearResults as $year) {
            $year = trim($year);
            if ($year === $currentAcademicYear) {
                $hasPresent = true;
            } else {
                $years[] = $year;
            }
        }
        
        // Always include "present" if we have current year data
        if ($hasPresent) {
            array_unshift($years, 'present');
        }
        
        echo json_encode([
            'success' => true,
            'years' => $years
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving officer years',
            'error' => $e->getMessage()
        ]);
    }
}

function saveOfficer() {
    global $conn, $user;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['position']) || empty($data['officer_name'])) {
        echo json_encode(['success' => false, 'message' => 'Position and officer name are required']);
        return;
    }

    try {
        // Get next order sequence
        $seqStmt = $conn->prepare("SELECT MAX(order_sequence) as max_seq FROM ssc_officers");
        $seqStmt->execute();
        $result = $seqStmt->fetch(PDO::FETCH_ASSOC);
        $nextSeq = ($result['max_seq'] ?? 0) + 1;

        $stmt = $conn->prepare("INSERT INTO ssc_officers (position, officer_name, order_sequence, created_by) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$data['position'], $data['officer_name'], $nextSeq, $user['id']]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Officer added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add officer']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error saving officer: ' . $e->getMessage()]);
    }
}

function deleteOfficer() {
    global $conn, $user;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Officer ID is required']);
        return;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM ssc_officers WHERE id = ? AND created_by = ?");
        $result = $stmt->execute([$data['id'], $user['id']]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Officer deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete officer']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error deleting officer: ' . $e->getMessage()]);
    }
}

function getReports() {
    global $conn;

    try {
        // Get event count
        $eventStmt = $conn->prepare("SELECT COUNT(*) FROM ssc_events");
        $eventStmt->execute();
        $eventCount = $eventStmt->fetchColumn();

        // Get candidate count
        $candidateStmt = $conn->prepare("SELECT COUNT(*) FROM ssc_candidates");
        $candidateStmt->execute();
        $candidateCount = $candidateStmt->fetchColumn();

        // Get initiative count
        $initiativeStmt = $conn->prepare("SELECT COUNT(*) FROM ssc_initiatives");
        $initiativeStmt->execute();
        $initiativeCount = $initiativeStmt->fetchColumn();

        // Get completed event count
        $completedStmt = $conn->prepare("SELECT COUNT(*) FROM ssc_events WHERE status = 'completed'");
        $completedStmt->execute();
        $completedCount = $completedStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'reports' => [
                'events' => (int)$eventCount,
                'candidates' => (int)$candidateCount,
                'initiatives' => (int)$initiativeCount,
                'completed' => (int)$completedCount
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error retrieving reports',
            'error' => $e->getMessage()
        ]);
    }
}

function getAllSscFeedback() {
    global $conn;

    try {
        $stmt = $conn->prepare("
            SELECT sf.*, u.full_name, u.email
            FROM ssc_feedback sf
            JOIN users u ON sf.user_id = u.id
            WHERE sf.is_approved = 1
            ORDER BY sf.created_at DESC
        ");
        $stmt->execute();
        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $feedbacks]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error retrieving feedback: ' . $e->getMessage()]);
    }
}

function getSscFeedbackStats() {
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM ssc_feedback WHERE is_approved = 1");
        $stmt->execute();
        $avgRating = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total_feedback FROM ssc_feedback WHERE is_approved = 1");
        $stmt->execute();
        $totalFeedback = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'avg_rating' => round($avgRating['avg_rating'] ?? 0, 2),
            'total_feedback' => $totalFeedback['total_feedback'] ?? 0
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error retrieving stats: ' . $e->getMessage()]);
    }
}
?>
