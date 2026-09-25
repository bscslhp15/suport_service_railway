<?php
/**
 * Guidance Case Feedback API
 * REST endpoint for managing guidance case feedback
 */

// Set error reporting BEFORE anything else
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header EARLY to prevent HTML errors from being output
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/guidance_feedback_functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Helper function to check if user is admin/counselor
function is_admin_user() {
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Check if admin or staff
    if (in_array($_SESSION['user_role'] ?? '', ['admin', 'staff'])) {
        return true;
    }
    
    // Check if counselor (teacher with head_service='guidance')
    if ($_SESSION['user_role'] === 'teacher') {
        try {
            $pdo = get_db();
            if (!$pdo) {
                return false;
            }
            
            $user_id = (int)$_SESSION['user_id'];
            $stmt = $pdo->prepare('SELECT head_service FROM users WHERE id = :id LIMIT 1');
            if (!$stmt->execute(['id' => $user_id])) {
                return false;
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($user && $user['head_service'] === 'guidance');
        } catch (Exception $e) {
            error_log('Permission check error: ' . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

// Helper function to get request data (handles both JSON and form data)
function get_request_data() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // Check if JSON
    if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    // Form data
    return $_POST + $_REQUEST;
}

$requestData = get_request_data();
$action = $requestData['action'] ?? null;
$pdo = get_db();

try {
    if ($action === 'save') {
        // Save feedback - only students can submit for their own cases
        $case_id = $requestData['case_id'] ?? null;
        $student_id = $requestData['student_id'] ?? null;
        $service_rating = $requestData['service_rating'] ?? 0;
        $feedback_text = $requestData['feedback_text'] ?? '';
        
        if (!$case_id || !$student_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        // Verify student is requesting for themselves
        if ((int)$student_id !== (int)$_SESSION['user_id'] && !is_admin_user()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
        
        if (!validate_guidance_feedback_rating((int)$service_rating)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid rating']);
            exit;
        }
        
        $data = [
            'case_id' => (int)$case_id,
            'student_id' => (int)$student_id,
            'service_rating' => (int)$service_rating,
            'feedback_text' => secure_input($feedback_text)
        ];
        
        if (save_guidance_case_feedback($data)) {
            echo json_encode(['success' => true, 'message' => 'Feedback saved']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save']);
        }
        
    } elseif ($action === 'get') {
        // Get specific feedback
        $case_id = $requestData['case_id'] ?? null;
        $student_id = $requestData['student_id'] ?? null;
        
        if (!$case_id || !$student_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        $feedback = get_guidance_case_feedback((int)$case_id, (int)$student_id);
        
        if ($feedback) {
            echo json_encode(['success' => true, 'data' => $feedback]);
        } else {
            echo json_encode(['success' => false, 'data' => null, 'message' => 'No feedback found']);
        }
        
    } elseif ($action === 'get_all') {
        // Get all feedback for a case (admin/counselor only)
        if (!is_admin_user()) {
            // Log debug info for troubleshooting
            error_log('Permission denied for get_all. Session: ' . json_encode([
                'user_id' => $_SESSION['user_id'] ?? null,
                'role' => $_SESSION['role'] ?? null
            ]));
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden - Admin/counselor access required']);
            exit;
        }
        
        $case_id = $requestData['case_id'] ?? 0;
        $feedback = get_all_guidance_case_feedback((int)$case_id);
        
        echo json_encode(['success' => true, 'data' => $feedback]);
        
    } elseif ($action === 'delete') {
        // Delete feedback (admin or self)
        if (!is_admin_user() && (int)$_SESSION['user_id'] !== (int)($requestData['student_id'] ?? 0)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
        
        $stmt = $pdo->prepare('DELETE FROM guidance_case_feedback WHERE case_id = :case_id AND student_id = :student_id');
        $result = $stmt->execute([
            'case_id' => $requestData['case_id'] ?? 0,
            'student_id' => $requestData['student_id'] ?? 0
        ]);
        
        echo json_encode(['success' => $result]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
