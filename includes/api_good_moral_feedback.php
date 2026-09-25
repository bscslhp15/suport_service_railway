<?php
/**
 * Good Moral Feedback API
 * Handles feedback submission for Good Moral Certificate requests
 */

require_once __DIR__ . '/session.php';
require_login();

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

try {
    // Get request data (handles both JSON and form data)
    function get_request_data() {
        if (strtolower($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?? [];
        }
        return $_POST;
    }
    
    $data = get_request_data();
    $action = $data['action'] ?? $_REQUEST['action'] ?? '';
    
    if (!in_array($action, ['save', 'get', 'get_all', 'delete'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    $pdo = get_db();
    
    // Create table if it doesn't exist
    function ensure_good_moral_feedback_table() {
        global $pdo;
        $sql = "
        CREATE TABLE IF NOT EXISTS good_moral_feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL,
            service_rating TINYINT UNSIGNED NOT NULL CHECK (service_rating BETWEEN 1 AND 5),
            feedback_text LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student (student_id),
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            error_log('Good Moral Feedback table creation error: ' . $e->getMessage());
        }
    }
    
    ensure_good_moral_feedback_table();
    
    // Check permissions
    $user = current_user();
    $currentUserId = $user['id'] ?? 0;
    
    switch ($action) {
        case 'save':
            $studentId = (int)($data['student_id'] ?? $currentUserId);
            $rating = (int)($data['service_rating'] ?? 0);
            $feedbackText = secure_input($data['feedback_text'] ?? '');
            
            // Validate input
            if ($studentId <= 0 || $rating < 1 || $rating > 5 || empty($feedbackText)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid input']);
                exit;
            }
            
            // Only allow students to submit feedback for themselves
            if ($currentUserId !== $studentId && $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO good_moral_feedback (student_id, service_rating, feedback_text)
                    VALUES (:student_id, :service_rating, :feedback_text)
                    ON DUPLICATE KEY UPDATE
                        service_rating = VALUES(service_rating),
                        feedback_text = VALUES(feedback_text),
                        updated_at = NOW()
                ');
                
                if ($stmt->execute([
                    ':student_id' => $studentId,
                    ':service_rating' => $rating,
                    ':feedback_text' => $feedbackText
                ])) {
                    echo json_encode(['success' => true, 'message' => 'Feedback saved successfully']);
                } else {
                    throw new Exception('Failed to save feedback');
                }
            } catch (Exception $e) {
                error_log('Good Moral Feedback save error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
            
        case 'get':
            $studentId = (int)($data['student_id'] ?? $currentUserId);
            
            if ($currentUserId !== $studentId && $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare('SELECT id, student_id, service_rating, feedback_text, created_at FROM good_moral_feedback WHERE student_id = ?');
                $stmt->execute([$studentId]);
                $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($feedback) {
                    echo json_encode(['success' => true, 'data' => $feedback]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No feedback found']);
                }
            } catch (Exception $e) {
                error_log('Good Moral Feedback get error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
            
        case 'get_all':
            // Only admin/counselor can view all feedback
            if ($user['role'] !== 'admin' && $user['role'] !== 'teacher') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare('
                    SELECT 
                        gmf.id, 
                        gmf.student_id, 
                        gmf.service_rating, 
                        gmf.feedback_text, 
                        gmf.created_at,
                        u.full_name,
                        u.email,
                        u.course_year
                    FROM good_moral_feedback gmf
                    JOIN users u ON gmf.student_id = u.id
                    ORDER BY gmf.created_at DESC
                ');
                $stmt->execute();
                $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $feedback]);
            } catch (Exception $e) {
                error_log('Good Moral Feedback get_all error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
            
        case 'delete':
            $studentId = (int)($data['student_id'] ?? $currentUserId);
            
            if ($currentUserId !== $studentId && $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare('DELETE FROM good_moral_feedback WHERE student_id = ?');
                if ($stmt->execute([$studentId])) {
                    echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
                } else {
                    throw new Exception('Failed to delete feedback');
                }
            } catch (Exception $e) {
                error_log('Good Moral Feedback delete error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
    }
} catch (Exception $e) {
    error_log('Good Moral Feedback API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
