<?php
/**
 * Announcement Interactions Functions
 * Handles likes and comments with per-account tracking
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

/**
 * Check whether a teacher is the head of the service shown on the announcements page.
 */
function can_manage_announcement_comments(array $user, string $service): bool {
    if (($user['role'] ?? '') !== 'teacher') {
        return false;
    }

    $headService = strtolower(trim((string)($user['head_service'] ?? '')));
    $requestedService = strtolower(trim($service));
    $serviceHeadMap = [
        'ssc/scholarship' => 'ssc_scholarship',
        'ssc' => 'ssc_scholarship',
        'scholarship' => 'ssc_scholarship',
        'library' => 'library',
        'clinic' => 'clinic',
        'guidance' => 'guidance',
    ];

    return isset($serviceHeadMap[$requestedService])
        && $headService === $serviceHeadMap[$requestedService];
}

/**
 * Add or remove a like for an announcement (per account, max 1 like)
 */
function toggle_announcement_like(int $userId, string $announcementType, int $announcementId): array {
    $pdo = get_db();
    
    try {
        $pdo->beginTransaction();
        
        // Check if user already liked this announcement
        $stmt = $pdo->prepare(
            'SELECT id FROM announcement_likes WHERE user_id = ? AND announcement_type = ? AND announcement_id = ?'
        );
        $stmt->execute([$userId, $announcementType, $announcementId]);
        $existingLike = $stmt->fetch();
        
        if ($existingLike) {
            // Remove the like
            $stmt = $pdo->prepare(
                'DELETE FROM announcement_likes WHERE user_id = ? AND announcement_type = ? AND announcement_id = ?'
            );
            $stmt->execute([$userId, $announcementType, $announcementId]);
            $action = 'removed';
        } else {
            // Add the like
            $stmt = $pdo->prepare(
                'INSERT INTO announcement_likes (user_id, announcement_type, announcement_id) VALUES (?, ?, ?)'
            );
            $stmt->execute([$userId, $announcementType, $announcementId]);
            $action = 'added';
        }
        
        // Get updated like count
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM announcement_likes WHERE announcement_type = ? AND announcement_id = ?'
        );
        $stmt->execute([$announcementType, $announcementId]);
        $likeCount = (int)$stmt->fetchColumn();
        
        $pdo->commit();
        
        return [
            'success' => true,
            'action' => $action,
            'likeCount' => $likeCount
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Check if user has liked an announcement
 */
function has_user_liked_announcement(int $userId, string $announcementType, int $announcementId): bool {
    $pdo = get_db();
    
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM announcement_likes WHERE user_id = ? AND announcement_type = ? AND announcement_id = ?'
    );
    $stmt->execute([$userId, $announcementType, $announcementId]);
    
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Get like count for an announcement
 */
function get_announcement_like_count(string $announcementType, int $announcementId): int {
    $pdo = get_db();
    
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM announcement_likes WHERE announcement_type = ? AND announcement_id = ?'
    );
    $stmt->execute([$announcementType, $announcementId]);
    
    return (int)$stmt->fetchColumn();
}

/**
 * Add a comment to an announcement
 */
function add_announcement_comment(int $userId, array $user, string $announcementType, int $announcementId, string $commentText): array {
    $pdo = get_db();
    
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO announcement_comments (user_id, user_name, user_course, announcement_type, announcement_id, comment_text) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        
        $userName = $user['full_name'] ?? 'Unknown';
        $userCourse = $user['course_year'] ?? $user['course'] ?? 'Course not specified';
        
        $stmt->execute([
            $userId,
            $userName,
            $userCourse,
            $announcementType,
            $announcementId,
            trim($commentText)
        ]);
        
        $commentId = (int)$pdo->lastInsertId();
        
        return [
            'success' => true,
            'commentId' => $commentId,
            'message' => 'Comment added successfully'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get all comments for an announcement
 */
function get_announcement_comments(string $announcementType, int $announcementId, ?int $currentUserId = null, bool $canManageAllComments = false): array {
    $pdo = get_db();
    
    $stmt = $pdo->prepare(
        'SELECT id, user_id, user_name, user_course, comment_text, created_at 
         FROM announcement_comments 
         WHERE announcement_type = ? AND announcement_id = ? 
         ORDER BY created_at DESC'
    );
    $stmt->execute([$announcementType, $announcementId]);
    
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for display
    return array_map(function ($comment) use ($currentUserId, $canManageAllComments) {
        $userId = isset($comment['user_id']) ? (int)$comment['user_id'] : null;
        return [
            'id' => $comment['id'],
            'name' => htmlspecialchars($comment['user_name']),
            'course' => htmlspecialchars($comment['user_course']),
            'text' => htmlspecialchars($comment['comment_text']),
            'timestamp' => strtotime($comment['created_at']) * 1000, // Convert to milliseconds for JS
            'time' => $comment['created_at'],
            'canDelete' => $canManageAllComments || ($currentUserId !== null && $userId === $currentUserId)
        ];
    }, $comments);
}

/**
 * Delete a comment only when it belongs to the current user and announcement.
 */
function delete_announcement_comment(int $commentId, int $userId, string $announcementType, int $announcementId, bool $canManageAllComments = false): bool {
    $pdo = get_db();

    if ($canManageAllComments) {
        $stmt = $pdo->prepare(
            'DELETE FROM announcement_comments
             WHERE id = ? AND announcement_type = ? AND announcement_id = ?'
        );
        $stmt->execute([$commentId, $announcementType, $announcementId]);
    } else {
        $stmt = $pdo->prepare(
            'DELETE FROM announcement_comments
             WHERE id = ? AND user_id = ? AND announcement_type = ? AND announcement_id = ?'
        );
        $stmt->execute([$commentId, $userId, $announcementType, $announcementId]);
    }

    return $stmt->rowCount() > 0;
}

/**
 * Get comment count for an announcement
 */
function get_announcement_comment_count(string $announcementType, int $announcementId): int {
    $pdo = get_db();
    
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM announcement_comments WHERE announcement_type = ? AND announcement_id = ?'
    );
    $stmt->execute([$announcementType, $announcementId]);
    
    return (int)$stmt->fetchColumn();
}
?>
