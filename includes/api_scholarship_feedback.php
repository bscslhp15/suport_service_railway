<?php
/**
 * API Handler for Scholarship Validation Feedback
 * Handles POST requests to save feedback after validation completion
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/scholarship_feedback_functions.php';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Extract and validate input
$action = $input['action'] ?? null;
$validation_id = isset($input['validation_id']) ? (int)$input['validation_id'] : null;
$student_id = isset($input['student_id']) ? (int)$input['student_id'] : null;
$process_rating = isset($input['process_rating']) ? (int)$input['process_rating'] : null;
$support_rating = isset($input['support_rating']) ? (int)$input['support_rating'] : null;
$review_text = $input['review_text'] ?? null;
$feedback_text = $input['feedback_text'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

// Validate ratings if provided
if (($process_rating !== null && !validate_scholarship_feedback_rating($process_rating)) ||
    ($support_rating !== null && !validate_scholarship_feedback_rating($support_rating))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid rating value (must be 0-5)']);
    exit;
}

// Process different actions
switch ($action) {
    case 'save':
        if (!$validation_id || !$student_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing validation_id or student_id']);
            exit;
        }

        // Verify student is the owner of the feedback
        if ($_SESSION['user_id'] !== $student_id && !is_admin_user($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $feedback_data = [
            'validation_id' => $validation_id,
            'student_id' => $student_id,
            'process_rating' => $process_rating,
            'support_rating' => $support_rating,
            'review_text' => $review_text ? secure_input($review_text) : null,
            'feedback_text' => $feedback_text ? secure_input($feedback_text) : null,
        ];
        
        if (save_scholarship_validation_feedback($feedback_data)) {
            echo json_encode([
                'success' => true,
                'message' => 'Feedback saved successfully',
                'data' => $feedback_data
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save feedback']);
        }
        break;

    case 'get':
        if (!$validation_id || !$student_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing validation_id or student_id']);
            exit;
        }

        // Get feedback for a specific validation and student
        $feedback = get_scholarship_validation_feedback($validation_id, $student_id);
        
        if ($feedback) {
            echo json_encode([
                'success' => true,
                'data' => $feedback
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'data' => null,
                'message' => 'No feedback found'
            ]);
        }
        break;

    case 'get_all':
        // Get all feedback for a validation (admin only)
        if (!is_admin_user($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        if ($validation_id) {
            $all_feedback = get_all_scholarship_feedback_for_validation($validation_id);
            $ratings = get_scholarship_feedback_average_ratings($validation_id);
        } else {
            $all_feedback = get_all_scholarship_feedback_for_validation(0);
            $ratings = ['avg_process_rating' => 0, 'avg_support_rating' => 0, 'total_responses' => count($all_feedback)];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $all_feedback,
            'summary' => $ratings
        ]);
        break;

    case 'delete':
        // Delete feedback (owner or admin only)
        $feedback_id = isset($input['feedback_id']) ? (int)$input['feedback_id'] : null;
        
        if (!$feedback_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing feedback_id']);
            exit;
        }
        
        // Verify permission
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT student_id FROM scholarship_validation_feedback WHERE id = :id');
        $stmt->execute(['id' => $feedback_id]);
        $feedback = $stmt->fetch();
        
        if (!$feedback) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Feedback not found']);
            exit;
        }
        
        if ($_SESSION['user_id'] !== $feedback['student_id'] && !is_admin_user($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        if (delete_scholarship_validation_feedback($feedback_id)) {
            echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete feedback']);
        }
        break;

    case 'summary':
        // Get feedback summary for a validation (admin only)
        if (!is_admin_user($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $summary = get_scholarship_feedback_summary($validation_id);
        echo json_encode([
            'success' => true,
            'data' => $summary
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}

/**
 * Check if user is admin
 */
function is_admin_user(int $user_id): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT 1 FROM users WHERE id = :id AND (
            role IN ("admin", "staff") OR 
            (role = "teacher" AND head_service = "ssc_scholarship")
        )'
    );
    $stmt->execute(['id' => $user_id]);
    return (bool)$stmt->fetch();
}
