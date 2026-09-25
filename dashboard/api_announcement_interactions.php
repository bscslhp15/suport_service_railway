<?php
/**
 * API Endpoint for Announcement Interactions (Likes & Comments)
 * Handles per-account like/comment operations with user details
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/announcement_interactions.php';

header('Content-Type: application/json');

require_login();
$user = current_user();

if (!in_array($user['role'], ['student', 'teacher'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$requestService = strtolower(trim((string)($_POST['service'] ?? $_GET['service'] ?? '')));
$canManageAllComments = can_manage_announcement_comments($user, $requestService);

try {
    switch ($action) {
        case 'toggle-like':
            $announcementType = $_POST['type'] ?? '';
            $announcementId = (int)($_POST['id'] ?? 0);
            
            if (!$announcementType || $announcementId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }
            
            $result = toggle_announcement_like($user['id'], $announcementType, $announcementId);
            echo json_encode($result);
            break;
        
        case 'add-comment':
            $announcementType = $_POST['type'] ?? '';
            $announcementId = (int)($_POST['id'] ?? 0);
            $commentText = $_POST['text'] ?? '';
            
            if (!$announcementType || $announcementId <= 0 || trim($commentText) === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }
            
            // Prevent comments longer than 500 characters
            if (strlen($commentText) > 500) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Comment too long (max 500 characters)']);
                exit;
            }
            
            $result = add_announcement_comment($user['id'], $user, $announcementType, $announcementId, $commentText);
            
            if ($result['success']) {
                // Return the new comment with formatted data
                $allComments = get_announcement_comments($announcementType, $announcementId, $user['id'], $canManageAllComments);
                $newComment = null;
                foreach ($allComments as $comment) {
                    if ($comment['id'] === $result['commentId']) {
                        $newComment = $comment;
                        break;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'comment' => $newComment,
                    'totalComments' => count($allComments)
                ]);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;

        case 'delete-comment':
            $announcementType = $_POST['type'] ?? '';
            $announcementId = (int)($_POST['id'] ?? 0);
            $commentId = (int)($_POST['comment_id'] ?? 0);

            if (!$announcementType || $announcementId <= 0 || $commentId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }

            $deleted = delete_announcement_comment($commentId, $user['id'], $announcementType, $announcementId, $canManageAllComments);

            if (!$deleted) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unable to delete comment']);
                exit;
            }

            $comments = get_announcement_comments($announcementType, $announcementId);
            echo json_encode(['success' => true, 'totalComments' => count($comments)]);
            break;

        case 'get-comments':
            $announcementType = $_GET['type'] ?? '';
            $announcementId = (int)($_GET['id'] ?? 0);
            
            if (!$announcementType || $announcementId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }
            
            $comments = get_announcement_comments($announcementType, $announcementId, $user['id'], $canManageAllComments);
            echo json_encode(['success' => true, 'comments' => $comments]);
            break;
        
        case 'get-like-status':
            $announcementType = $_GET['type'] ?? '';
            $announcementId = (int)($_GET['id'] ?? 0);
            
            if (!$announcementType || $announcementId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }
            
            $hasLiked = has_user_liked_announcement($user['id'], $announcementType, $announcementId);
            $likeCount = get_announcement_like_count($announcementType, $announcementId);
            
            echo json_encode([
                'success' => true,
                'hasLiked' => $hasLiked,
                'likeCount' => $likeCount
            ]);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
