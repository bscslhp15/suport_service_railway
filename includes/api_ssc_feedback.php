<?php
ini_set('display_errors', '0');
header('Content-Type: application/json');

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/clinic_functions.php';
require_login();

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $data['action'] ?? $_REQUEST['action'] ?? null;

    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No action specified']);
        exit;
    }

    switch ($action) {
        case 'save':
            // Save SSC feedback
            $user_id = $_SESSION['user_id'];
            $rating = (int)($data['rating'] ?? 0);
            $comments = $data['comments'] ?? '';
            $event_id = !empty($data['event_id']) ? (int)$data['event_id'] : null;

            if ($rating < 1 || $rating > 5) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid rating']);
                exit;
            }

            if (empty($comments)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Comments are required']);
                exit;
            }

            $pdo = get_db();
            
            // Check if user already submitted feedback today
            $stmt = $pdo->prepare("
                SELECT id FROM ssc_feedback 
                WHERE user_id = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing feedback
                $stmt = $pdo->prepare("
                    UPDATE ssc_feedback 
                    SET rating = ?, comments = ?, event_id = ?, is_approved = 1
                    WHERE user_id = ? AND DATE(created_at) = CURDATE()
                ");
                $stmt->execute([$rating, $comments, $event_id, $user_id]);
            } else {
                // Insert new feedback (auto-approved)
                $stmt = $pdo->prepare("
                    INSERT INTO ssc_feedback (user_id, event_id, rating, comments, is_approved, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$user_id, $event_id, $rating, $comments]);
            }

            echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
            break;

        case 'get_user_feedback':
            // Get feedback from current user
            $user_id = $_SESSION['user_id'];
            $pdo = get_db();
            
            $stmt = $pdo->prepare("
                SELECT * FROM ssc_feedback
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $feedbacks]);
            break;

        case 'get_all':
            // Get all SSC feedback for SSC head
            $pdo = get_db();
            
            $stmt = $pdo->prepare("
                SELECT sf.*, u.full_name, u.email
                FROM ssc_feedback sf
                JOIN users u ON sf.user_id = u.id
                WHERE sf.is_approved = 1
                ORDER BY sf.created_at DESC
            ");
            $stmt->execute();
            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $feedbacks]);
            break;

        case 'get_stats':
            // Get SSC feedback statistics
            $pdo = get_db();
            
            $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM ssc_feedback WHERE is_approved = 1");
            $stmt->execute();
            $avgRating = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_feedback FROM ssc_feedback WHERE is_approved = 1");
            $stmt->execute();
            $totalFeedback = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'avg_rating' => round($avgRating['avg_rating'] ?? 0, 2),
                'total_feedback' => $totalFeedback['total_feedback'] ?? 0
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
