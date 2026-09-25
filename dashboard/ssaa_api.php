<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/clinic_functions.php';

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
$action = $_GET['action'] ?? '';

function ensureAlumniExtraColumns($conn) {
    $columns = [
        'achievements' => 'TEXT DEFAULT NULL',
        'quote' => 'TEXT DEFAULT NULL',
        'profile_image' => 'VARCHAR(255) DEFAULT NULL',
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME DEFAULT NULL',
        'archived_by' => 'INT(11) DEFAULT NULL'
    ];

    foreach ($columns as $column => $definition) {
        $columnCheck = $conn->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS " .
            "WHERE TABLE_SCHEMA = DATABASE() " .
            "  AND TABLE_NAME = 'ssaa_alumni' " .
            "  AND COLUMN_NAME = '$column'"
        )->fetchColumn();

        if (!$columnCheck) {
            $conn->exec("ALTER TABLE ssaa_alumni ADD COLUMN `$column` $definition");
        }
    }
}

ensureAlumniExtraColumns($conn);

switch ($action) {
    case 'get_student_progress':
        getStudentProgress();
        break;
    case 'get_alumni_count':
        getAlumniCount();
        break;
    case 'get_alumni_list':
        getAlumniList();
        break;
    case 'update_employment':
        updateEmployment();
        break;
    case 'get_upcoming_events':
        getUpcomingEvents();
        break;
    case 'get_all_feedback':
        getAllSsaaFeedback();
        break;
    case 'get_feedback_stats':
        getSsaaFeedbackStats();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getStudentProgress() {
    global $conn, $user;

    if ($user['role'] !== 'student') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM ssaa_student_progression WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'progress' => $progress]);
}

function getAlumniCount() {
    global $conn, $user;

    // Get alumni count for the user's course
    $course = $user['course'] ?? $user['course_year'] ?? '';
    if (strpos($course, ' ') !== false) {
        $courseParts = explode(' ', $course);
        array_pop($courseParts); // Remove year
        $course = implode(' ', $courseParts);
    }

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ssaa_alumni WHERE (is_archived = 0 OR is_archived IS NULL) AND course LIKE ?");
    $stmt->execute(['%' . $course . '%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'count' => $result['count']]);
}

function updateEmployment() {
    global $conn, $user;

    if ($user['role'] !== 'student') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }

    $data = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
        $data = $_POST;
    } else {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    if (empty($data['employment_status'])) {
        echo json_encode(['success' => false, 'message' => 'Employment status is required']);
        return;
    }

    // Check if alumni record exists
    $stmt = $conn->prepare("SELECT id FROM ssaa_alumni WHERE user_id = ? AND (is_archived = 0 OR is_archived IS NULL)");
    $stmt->execute([$user['id']]);
    $alumni = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alumni) {
        echo json_encode(['success' => false, 'message' => 'Alumni record not found']);
        return;
    }

    $stmt = $conn->prepare("UPDATE ssaa_alumni SET
        employment_status = ?,
        company_name = ?,
        job_title = ?,
        last_updated = NOW()
        WHERE id = ?");

    $result = $stmt->execute([
        $data['employment_status'],
        $data['company_name'] ?? null,
        $data['job_title'] ?? null,
        $alumni['id']
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Employment information updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update employment information']);
    }
}

function getAlumniList() {
    global $conn;

    $query = trim($_GET['query'] ?? '');
    $year = trim($_GET['year'] ?? '');
    $course = trim($_GET['course'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $where = 'WHERE 1=1';
    $params = [];

    if ($query !== '') {
        $where .= ' AND (full_name LIKE ? OR course LIKE ? OR company_name LIKE ? OR job_title LIKE ?)';
        $searchTerm = "%$query%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    if ($year !== '') {
        $where .= ' AND graduation_year = ?';
        $params[] = $year;
    }

    if ($course !== '') {
        $where .= ' AND course LIKE ?';
        $params[] = "%$course%";
    }

    if ($status !== '') {
        $where .= ' AND LOWER(employment_status) = LOWER(?)';
        $params[] = $status;
    }

    $where .= ' AND (is_archived = 0 OR is_archived IS NULL)';
    $stmt = $conn->prepare("SELECT id, full_name, email, student_id, course, graduation_year, employment_status, company_name AS company, job_title, industry, job_level, salary_range, employment_date, location, job_description, contact_number, achievements, quote, profile_image FROM ssaa_alumni $where ORDER BY graduation_year DESC, full_name LIMIT 200");
    $stmt->execute($params);
    $alumni = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'alumni' => $alumni]);
}

function getUpcomingEvents() {
    global $conn;

    $stmt = $conn->prepare("SELECT title, event_date, description FROM ssaa_graduation_events 
                           WHERE event_date >= CURDATE() AND status = 'scheduled' 
                           ORDER BY event_date ASC LIMIT 3");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'events' => $events]);
}

function getAllSsaaFeedback() {
    $feedbacks = get_all_ssaa_feedback();
    
    echo json_encode([
        'success' => true,
        'data' => $feedbacks
    ]);
}

function getSsaaFeedbackStats() {
    $avgRating = get_ssaa_feedback_average_rating();
    $totalFeedback = get_ssaa_feedback_count();
    
    // Count excellent feedbacks (rating >= 4)
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ssaa_feedback WHERE is_approved = 1 AND rating >= 4");
    $stmt->execute();
    $excellentCount = (int)$stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'avg_rating' => $avgRating,
        'total_feedback' => $totalFeedback,
        'excellent_count' => $excellentCount
    ]);
}
?>