<?php
ini_set('display_errors', '0');
header('Content-Type: application/json');

require_once __DIR__ . '/session.php';
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
            // Save clinic feedback
            $user_id = $_SESSION['user_id'];
            $rating = (int)($data['rating'] ?? 0);
            $comments = $data['comments'] ?? '';
            $visit_id = !empty($data['visit_id']) ? (int)$data['visit_id'] : null;

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
                SELECT id FROM clinic_feedback 
                WHERE user_id = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing feedback
                $stmt = $pdo->prepare("
                    UPDATE clinic_feedback 
                    SET rating = ?, comments = ?, visit_id = ?
                    WHERE user_id = ? AND DATE(created_at) = CURDATE()
                ");
                $stmt->execute([$rating, $comments, $visit_id, $user_id]);
            } else {
                // Insert new feedback
                $stmt = $pdo->prepare("
                    INSERT INTO clinic_feedback (user_id, visit_id, rating, comments, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$user_id, $visit_id, $rating, $comments]);
            }

            echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
            break;

        case 'get_user_feedback':
            // Get feedback from current user
            $user_id = $_SESSION['user_id'];
            $pdo = get_db();
            
            $stmt = $pdo->prepare("
                SELECT * FROM clinic_feedback
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $feedbacks]);
            break;

        case 'get_all':
            // Get all clinic feedback for clinic head
            $pdo = get_db();
            
            $stmt = $pdo->prepare("
                SELECT cf.*, u.full_name, u.email
                FROM clinic_feedback cf
                JOIN users u ON cf.user_id = u.id
                ORDER BY cf.created_at DESC
            ");
            $stmt->execute();
            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $feedbacks]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
