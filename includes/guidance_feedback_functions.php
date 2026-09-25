<?php
/**
 * Guidance Case Feedback Functions
 * Functions for managing guidance case service feedback and ratings
 */

require_once __DIR__ . '/functions.php';

function save_guidance_case_feedback(array $data): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO guidance_case_feedback (case_id, student_id, service_rating, feedback_text) 
         VALUES (:case_id, :student_id, :service_rating, :feedback_text)
         ON DUPLICATE KEY UPDATE 
         service_rating = VALUES(service_rating), 
         feedback_text = VALUES(feedback_text)'
    );
    
    return $stmt->execute([
        ':case_id' => $data['case_id'],
        ':student_id' => $data['student_id'],
        ':service_rating' => $data['service_rating'] ?? 0,
        ':feedback_text' => $data['feedback_text'] ?? null,
    ]);
}

function get_guidance_case_feedback(int $caseId, int $studentId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT * FROM guidance_case_feedback 
         WHERE case_id = :case_id AND student_id = :student_id LIMIT 1'
    );
    $stmt->execute(['case_id' => $caseId, 'student_id' => $studentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

function get_all_guidance_case_feedback(int $caseId): array {
    $pdo = get_db();
    if ($caseId > 0) {
        $stmt = $pdo->prepare(
            'SELECT gcf.*, u.full_name, u.student_id, u.course_year
             FROM guidance_case_feedback gcf
             JOIN users u ON gcf.student_id = u.id
             WHERE gcf.case_id = :case_id
             ORDER BY gcf.created_at DESC'
        );
        $stmt->execute(['case_id' => $caseId]);
    } else {
        $stmt = $pdo->query(
            'SELECT gcf.*, u.full_name, u.student_id, u.course_year
             FROM guidance_case_feedback gcf
             JOIN users u ON gcf.student_id = u.id
             ORDER BY gcf.created_at DESC'
        );
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function get_student_guidance_feedback(int $studentId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT gcf.*, cc.case_number, cc.type_of_concern, cc.status
         FROM guidance_case_feedback gcf
         JOIN guidance_cases cc ON gcf.case_id = cc.id
         WHERE gcf.student_id = :student_id
         ORDER BY gcf.created_at DESC'
    );
    $stmt->execute(['student_id' => $studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function validate_guidance_feedback_rating(int $rating): bool {
    return $rating >= 0 && $rating <= 5;
}

function get_guidance_feedback_average_ratings(int $caseId): array {
    $pdo = get_db();
    if ($caseId > 0) {
        $stmt = $pdo->prepare(
            'SELECT 
                COUNT(*) as total_feedback,
                AVG(service_rating) as avg_service_rating,
                ROUND(AVG(service_rating), 1) as avg_service_rating_rounded
             FROM guidance_case_feedback 
             WHERE case_id = :case_id'
        );
        $stmt->execute(['case_id' => $caseId]);
    } else {
        $stmt = $pdo->query(
            'SELECT 
                COUNT(*) as total_feedback,
                AVG(service_rating) as avg_service_rating,
                ROUND(AVG(service_rating), 1) as avg_service_rating_rounded
             FROM guidance_case_feedback'
        );
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: ['total_feedback' => 0, 'avg_service_rating' => 0, 'avg_service_rating_rounded' => 0];
}

function get_guidance_feedback_summary(int $caseId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT 
            case_id,
            COUNT(*) as total_feedback,
            ROUND(AVG(service_rating), 1) as avg_rating,
            SUM(CASE WHEN service_rating >= 4 THEN 1 ELSE 0 END) as positive_count,
            SUM(CASE WHEN service_rating < 3 THEN 1 ELSE 0 END) as negative_count
         FROM guidance_case_feedback 
         WHERE case_id = :case_id
         GROUP BY case_id'
    );
    $stmt->execute(['case_id' => $caseId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: [
        'total_feedback' => 0,
        'avg_rating' => 0,
        'positive_count' => 0,
        'negative_count' => 0
    ];
}
?>
