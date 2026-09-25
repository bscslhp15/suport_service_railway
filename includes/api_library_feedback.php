<?php
/**
 * Library Feedback API
 * REST endpoint for retrieving library book and service feedback
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
    
    if (!in_array($action, ['get_all', 'get_stats'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    
    $pdo = get_db();
    
    // Check permissions
    $user = current_user();
    $currentUserId = $user['id'] ?? 0;
    
    switch ($action) {
        case 'get_all':
            // Get all library feedback - admin/counselor only
            if ($user['role'] !== 'admin' && $user['role'] !== 'teacher') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare('
                    SELECT 
                        lf.id, 
                        lf.borrow_id, 
                        lf.user_id, 
                        lf.book_id, 
                        lf.book_rating, 
                        lf.service_rating, 
                        lf.book_review, 
                        lf.service_feedback, 
                        lf.created_at,
                        u.full_name,
                        u.email,
                        u.course_year,
                        lb.title as book_title
                    FROM library_feedback lf
                    JOIN users u ON lf.user_id = u.id
                    JOIN library_books lb ON lf.book_id = lb.id
                    ORDER BY lf.created_at DESC
                ');
                $stmt->execute();
                $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $feedback]);
            } catch (Exception $e) {
                error_log('Library Feedback get_all error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
            
        case 'get_stats':
            // Get library feedback statistics
            if ($user['role'] !== 'admin' && $user['role'] !== 'teacher') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }
            
            try {
                // Get average ratings
                $stmt = $pdo->prepare('
                    SELECT 
                        COUNT(DISTINCT CASE WHEN book_rating > 0 THEN 1 END) as book_reviews_count,
                        COUNT(DISTINCT CASE WHEN service_rating > 0 THEN 1 END) as service_reviews_count,
                        ROUND(AVG(CASE WHEN book_rating > 0 THEN book_rating END), 1) as avg_book_rating,
                        ROUND(AVG(CASE WHEN service_rating > 0 THEN service_rating END), 1) as avg_service_rating,
                        COUNT(*) as total_feedback
                    FROM library_feedback
                ');
                $stmt->execute();
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $stats]);
            } catch (Exception $e) {
                error_log('Library Feedback get_stats error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
    }
} catch (Exception $e) {
    error_log('Library Feedback API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
