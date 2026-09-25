<?php
// Missing library dashboard functions

function get_library_resources_for_user(int $userId, bool $approvedOnly = true, int $limit = 6): array {
    $user = find_user_by_id($userId);
    $courseYear = trim($user['course_year'] ?? '');
    if ($courseYear === '') {
        return [];
    }
    $pdo = get_db();
    if ($approvedOnly) {
        // Only show resources for the user's course
        $stmt = $pdo->prepare('SELECT lr.*, u.full_name as uploader_name FROM library_resources lr LEFT JOIN users u ON lr.uploaded_by = u.id WHERE lr.is_approved = 1 AND lr.course_year = :course_year ORDER BY lr.created_at DESC LIMIT :limit');
    } else {
        $stmt = $pdo->prepare('SELECT lr.*, u.full_name as uploader_name FROM library_resources lr LEFT JOIN users u ON lr.uploaded_by = u.id WHERE lr.course_year = :course_year ORDER BY lr.created_at DESC LIMIT :limit');
    }
    $stmt->bindValue(':course_year', $courseYear, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_open_access_resource_categories(string $courseYear = ''): array {
    $pdo = get_db();
    // Filter by course: show resources for the student's course OR general resources (NULL)
    $stmt = $pdo->prepare('SELECT DISTINCT category FROM library_open_access_resources WHERE is_active = 1 AND category IS NOT NULL AND (course_year = :course_year OR course_year IS NULL) ORDER BY category ASC');
    $stmt->bindValue(':course_year', $courseYear, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function get_open_access_resources(string $courseYear = '', int $limit = 0): array {
    $pdo = get_db();
    // Filter by course: show resources for the student's course OR general resources (NULL)
    $sql = 'SELECT lor.*, u.full_name as creator_name FROM library_open_access_resources lor LEFT JOIN users u ON lor.created_by = u.id WHERE lor.is_active = 1 AND (lor.course_year = :course_year OR lor.course_year IS NULL) ORDER BY lor.created_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT :limit';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':course_year', $courseYear, PDO::PARAM_STR);
    if ($limit > 0) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_user_course_category(int $userId): ?string {
    $user = find_user_by_id($userId);
    if (!$user) {
        return null;
    }
    return $user['course_year'] ?? $user['course'] ?? null;
}
