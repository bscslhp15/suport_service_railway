<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Initialize database connection
$conn = get_db();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}
$action = $_GET['action'] ?? '';

// Only allow authorized users to modify scholarship data
$writeActions = [
    'save_announcement',
    'delete_announcement',
    'save_application_status',
    'save_update',
    'save_validation'
];

// Only allow admin and scholarship teachers to perform these actions
if (in_array($action, $writeActions, true)) {
    if ($user['role'] !== 'admin' && !($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

switch ($action) {
    // Announcements CRUD
    case 'get_announcements':
        getAnnouncements();
        break;
    case 'save_announcement':
        saveAnnouncement();
        break;
    case 'delete_announcement':
        deleteAnnouncement();
        break;

    // Application Status
    case 'get_applications':
        getApplications();
        break;
    case 'save_application_status':
        saveApplicationStatus();
        break;

    // Ready to Submit
    case 'mark_ready_to_submit':
        markReadyToSubmit();
        break;

    // Updates
    case 'get_updates':
        getUpdates();
        break;
    case 'save_update':
        saveUpdate();
        break;

    // Reports
    case 'get_reports':
        getReports();
        break;

    // Document Validation
    case 'search_students':
        searchStudents();
        break;
    case 'save_validation':
        saveValidation();
        break;
    case 'get_validations':
        getValidations();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getAnnouncements() {
    global $conn;

    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM scholarship_announcements WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM scholarship_announcements ORDER BY created_at DESC");
        $stmt->execute();
    }
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get images for each announcement
    foreach ($announcements as &$announcement) {
        $photoStmt = $conn->prepare("SELECT image_path FROM scholarship_announcement_images WHERE announcement_id = ? ORDER BY created_at ASC");
        $photoStmt->execute([$announcement['id']]);
        $photos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);
        $announcement['images'] = $photos;
    }

    echo json_encode(['success' => true, 'announcements' => $announcements]);
}

function saveAnnouncement() {
    global $conn, $user;

    $data = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
        $data = $_POST;
    } else {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    if (empty($data['title']) || empty($data['content'])) {
        echo json_encode(['success' => false, 'message' => 'Title and content are required']);
        return;
    }

    $conn->beginTransaction();
    try {
        if (!empty($data['id'])) {
            // Update existing announcement
            $stmt = $conn->prepare("UPDATE scholarship_announcements SET
                title = ?, content = ?, program_type = ?, deadline = ?, updated_at = NOW()
                WHERE id = ?");

            $result = $stmt->execute([
                $data['title'], $data['content'], $data['program_type'] ?? '',
                $data['deadline'] ?? null, $data['id']
            ]);
            $announcementId = $data['id'];
        } else {
            // Create new announcement
            $stmt = $conn->prepare("INSERT INTO scholarship_announcements
                (title, content, program_type, deadline, created_by)
                VALUES (?, ?, ?, ?, ?)");

            $result = $stmt->execute([
                $data['title'], $data['content'], $data['program_type'] ?? '',
                $data['deadline'] ?? null, $user['id']
            ]);
            $announcementId = $conn->lastInsertId();
        }

        if (!$result) {
            throw new Exception('Failed to save announcement');
        }

        // Handle image updates (deletion of removed images)
        if (!empty($data['id'])) {
            $keepImages = $_POST['keep_images'] ?? [];
            if (!is_array($keepImages)) {
                $keepImages = [];
            }
            
            // Get all current images for this announcement
            $currentStmt = $conn->prepare("SELECT image_path FROM scholarship_announcement_images WHERE announcement_id = ?");
            $currentStmt->execute([$announcementId]);
            $currentImages = $currentStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete images not in the keep list
            foreach ($currentImages as $currentImage) {
                if (!in_array($currentImage, $keepImages, true)) {
                    // Delete the image file
                    $filePath = __DIR__ . '/../' . $currentImage;
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    // Delete the database record
                    $delStmt = $conn->prepare("DELETE FROM scholarship_announcement_images WHERE announcement_id = ? AND image_path = ?");
                    $delStmt->execute([$announcementId, $currentImage]);
                }
            }
        }

        // Handle multiple image uploads
        if (!empty($_FILES['images'])) {
            $images = $_FILES['images'];
            if (is_array($images['name'])) {
                foreach ($images['name'] as $key => $name) {
                    if ($images['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $images['name'][$key],
                            'type' => $images['type'][$key],
                            'tmp_name' => $images['tmp_name'][$key],
                            'error' => $images['error'][$key],
                            'size' => $images['size'][$key]
                        ];
                        $uploadedImage = save_scholarship_image_upload($file);
                        if ($uploadedImage !== null) {
                            $stmt = $conn->prepare("INSERT INTO scholarship_announcement_images (announcement_id, image_path) VALUES (?, ?)");
                            $stmt->execute([$announcementId, $uploadedImage]);
                        }
                    }
                }
            } elseif ($images['error'] === UPLOAD_ERR_OK) {
                $uploadedImage = save_scholarship_image_upload($images);
                if ($uploadedImage !== null) {
                    $stmt = $conn->prepare("INSERT INTO scholarship_announcement_images (announcement_id, image_path) VALUES (?, ?)");
                    $stmt->execute([$announcementId, $uploadedImage]);
                }
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Announcement saved successfully']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to save announcement: ' . $e->getMessage()]);
    }
}

function deleteAnnouncement() {
    global $conn, $user;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID is required']);
        return;
    }

    if ($user['role'] === 'admin') {
        $stmt = $conn->prepare("DELETE FROM scholarship_announcements WHERE id = ?");
        $result = $stmt->execute([$data['id']]);
    } else {
        $stmt = $conn->prepare("DELETE FROM scholarship_announcements WHERE id = ? AND created_by = ?");
        $result = $stmt->execute([$data['id'], $user['id']]);
    }

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Announcement not found or you are not allowed to remove it']);
    }
}

function getApplications() {
    global $conn, $user;

    // For students: get their own applications
    // For admin/SSC head: get all applications
    if ($user['role'] === 'student') {
        $stmt = $conn->prepare("SELECT * FROM scholarship_applications WHERE student_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user['id']]);
    } else {
        $stmt = $conn->prepare("SELECT sa.*, u.full_name, u.student_id as student_number FROM scholarship_applications sa JOIN users u ON sa.student_id = u.id ORDER BY sa.created_at DESC");
        $stmt->execute();
    }

    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'applications' => $applications]);
}

function saveApplicationStatus() {
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id']) || !isset($data['status'])) {
        echo json_encode(['success' => false, 'message' => 'Application ID and status are required']);
        return;
    }

    $stmt = $conn->prepare("UPDATE scholarship_applications SET
        status = ?, comments = ?, updated_at = NOW()
        WHERE id = ?");

    $result = $stmt->execute([
        $data['status'], $data['comments'] ?? '', $data['id']
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

function markReadyToSubmit() {
    global $conn, $user;

    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['announcement_id'])) {
            echo json_encode(['success' => false, 'message' => 'Announcement ID is required']);
            return;
        }

        // Check if application already exists
        $checkStmt = $conn->prepare("SELECT id FROM scholarship_applications WHERE student_id = ? AND announcement_id = ?");
        $checkStmt->execute([$user['id'], $data['announcement_id']]);
        $existing = $checkStmt->fetchColumn();

        if ($existing) {
            $stmt = $conn->prepare("UPDATE scholarship_applications SET ready_timestamp = NOW(), status = 'ready' WHERE id = ?");
            $result = $stmt->execute([$existing]);
        } else {
            $stmt = $conn->prepare("INSERT INTO scholarship_applications (student_id, announcement_id, ready_timestamp, status) VALUES (?, ?, NOW(), 'ready')");
            $result = $stmt->execute([$user['id'], $data['announcement_id']]);
        }

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Marked as ready to submit']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark as ready']);
        }
    } catch (Exception $e) {
        error_log('Error in markReadyToSubmit: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

function getUpdates() {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM scholarship_updates ORDER BY created_at DESC");
    $stmt->execute();
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'updates' => $updates]);
}

function saveUpdate() {
    global $conn, $user;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['title']) || empty($data['content'])) {
        echo json_encode(['success' => false, 'message' => 'Title and content are required']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO scholarship_updates
        (title, content, update_type, created_by)
        VALUES (?, ?, ?, ?)");

    $result = $stmt->execute([
        $data['title'], $data['content'], $data['update_type'] ?? 'general', $user['id']
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Update posted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to post update']);
    }
}

function getReports() {
    global $conn;

    // Get announcement count
    $announcementStmt = $conn->prepare("SELECT COUNT(*) FROM scholarship_announcements");
    $announcementStmt->execute();
    $announcementCount = $announcementStmt->fetchColumn();

    // Get application count
    $applicationStmt = $conn->prepare("SELECT COUNT(*) FROM scholarship_applications");
    $applicationStmt->execute();
    $applicationCount = $applicationStmt->fetchColumn();

    // Get update count
    $updateStmt = $conn->prepare("SELECT COUNT(*) FROM scholarship_updates");
    $updateStmt->execute();
    $updateCount = $updateStmt->fetchColumn();

    // Get ready count
    $readyStmt = $conn->prepare("SELECT COUNT(*) FROM scholarship_applications WHERE status = 'ready'");
    $readyStmt->execute();
    $readyCount = $readyStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'reports' => [
            'announcements' => (int)$announcementCount,
            'applications' => (int)$applicationCount,
            'updates' => (int)$updateCount,
            'ready_count' => (int)$readyCount
        ]
    ]);
}

function searchStudents() {
    global $conn, $user;

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }

    try {
        $query = $_GET['q'] ?? '';
        if (empty($query)) {
            echo json_encode(['success' => true, 'students' => []]);
            return;
        }

        $stmt = $conn->prepare("SELECT id, full_name, student_id, CONCAT(course_year, '') as course_year, role
            FROM users
            WHERE (role = 'student' OR role = 'teacher')
            AND (full_name LIKE ? OR student_id LIKE ?)
            ORDER BY role, full_name
            LIMIT 10");

        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'students' => $students]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function saveValidation() {
    global $conn, $user;

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['student_id']) || empty($data['announcement_id'])) {
        echo json_encode(['success' => false, 'message' => 'Student ID and announcement ID are required']);
        return;
    }

    $requirements = [];
    if (!empty($data['requirements']) && is_array($data['requirements'])) {
        $requirements = $data['requirements'];
    }

    $stmt = $conn->prepare("INSERT INTO scholarship_document_validations
        (student_id, announcement_id, requirements, validation_date, notes, validated_by)
        VALUES (?, ?, ?, ?, ?, ?)");

    $result = $stmt->execute([
        $data['student_id'],
        $data['announcement_id'],
        json_encode($requirements),
        $data['validation_date'],
        $data['notes'] ?? '',
        $user['id']
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Validation saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save validation']);
    }
}

function getValidations() {
    global $conn, $user;

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }

    try {
        $query = "
            SELECT
                v.id, v.student_id, v.announcement_id, v.validated_by, v.validated_at, v.validation_date, v.requirements, v.notes,
                u.full_name as student_name,
                u.student_id as student_id_number,
                sa.title as announcement_title,
                vb.full_name as validator_name
            FROM scholarship_document_validations v
            JOIN users u ON v.student_id = u.id
            JOIN scholarship_announcements sa ON v.announcement_id = sa.id
            JOIN users vb ON v.validated_by = vb.id";

        // If user is a student, only show their own validations
        if ($user['role'] === 'student') {
            $query .= " WHERE v.student_id = ?";
            $stmt = $conn->prepare($query . " ORDER BY v.validated_at DESC");
            $stmt->execute([$user['id']]);
        } else {
            $query .= " ORDER BY v.validated_at DESC LIMIT 50";
            $stmt = $conn->prepare($query);
            $stmt->execute();
        }

        $validations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON requirements
        foreach ($validations as &$validation) {
            $validation['requirements'] = json_decode($validation['requirements'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $validation['requirements'] = []; // Fallback to empty array if JSON is invalid
            }
        }

        echo json_encode(['success' => true, 'validations' => $validations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>