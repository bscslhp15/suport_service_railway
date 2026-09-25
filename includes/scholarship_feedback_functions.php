<?php
/**
 * Scholarship Feedback Functions
 * Functions for managing scholarship validation process feedback
 */

function save_scholarship_validation_feedback(array $data): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO scholarship_validation_feedback (validation_id, student_id, process_rating, support_rating, review_text, feedback_text) 
         VALUES (:validation_id, :student_id, :process_rating, :support_rating, :review_text, :feedback_text)
         ON DUPLICATE KEY UPDATE 
         process_rating = VALUES(process_rating), 
         support_rating = VALUES(support_rating), 
         review_text = VALUES(review_text), 
         feedback_text = VALUES(feedback_text)'
    );
    
    return $stmt->execute([
        ':validation_id' => $data['validation_id'],
        ':student_id' => $data['student_id'],
        ':process_rating' => $data['process_rating'] ?? 0,
        ':support_rating' => $data['support_rating'] ?? 0,
        ':review_text' => $data['review_text'] ?? null,
        ':feedback_text' => $data['feedback_text'] ?? null,
    ]);
}

function get_scholarship_validation_feedback(int $validationId, int $studentId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT * FROM scholarship_validation_feedback 
         WHERE validation_id = :validation_id AND student_id = :student_id LIMIT 1'
    );
    $stmt->execute(['validation_id' => $validationId, 'student_id' => $studentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

function get_all_scholarship_feedback_for_validation(int $validationId): array {
    $pdo = get_db();
    if ($validationId > 0) {
        $stmt = $pdo->prepare(
            'SELECT svf.*, u.full_name, u.student_id 
             FROM scholarship_validation_feedback svf
             JOIN users u ON svf.student_id = u.id
             WHERE svf.validation_id = :validation_id
             ORDER BY svf.created_at DESC'
        );
        $stmt->execute(['validation_id' => $validationId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT svf.*, u.full_name, u.student_id 
             FROM scholarship_validation_feedback svf
             JOIN users u ON svf.student_id = u.id
             ORDER BY svf.created_at DESC'
        );
        $stmt->execute();
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_scholarship_feedback_average_ratings(int $validationId): array {
    $pdo = get_db();
    if ($validationId > 0) {
        $stmt = $pdo->prepare(
            'SELECT 
             AVG(process_rating) as avg_process_rating,
             AVG(support_rating) as avg_support_rating,
             COUNT(*) as total_responses
             FROM scholarship_validation_feedback
             WHERE validation_id = :validation_id'
        );
        $stmt->execute(['validation_id' => $validationId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT 
             AVG(process_rating) as avg_process_rating,
             AVG(support_rating) as avg_support_rating,
             COUNT(*) as total_responses
             FROM scholarship_validation_feedback'
        );
        $stmt->execute();
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'avg_process_rating' => $result ? round((float)$result['avg_process_rating'], 2) : 0,
        'avg_support_rating' => $result ? round((float)$result['avg_support_rating'], 2) : 0,
        'total_responses' => $result ? (int)$result['total_responses'] : 0,
    ];
}

function count_scholarship_feedback_responses(int $validationId): int {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM scholarship_validation_feedback WHERE validation_id = :validation_id'
    );
    $stmt->execute(['validation_id' => $validationId]);
    return (int)$stmt->fetchColumn();
}

function get_student_scholarship_feedback(int $studentId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT svf.*, sdv.validation_date, sdv.status 
         FROM scholarship_validation_feedback svf
         JOIN scholarship_document_validations sdv ON svf.validation_id = sdv.id
         WHERE svf.student_id = :student_id
         ORDER BY svf.created_at DESC'
    );
    $stmt->execute(['student_id' => $studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function delete_scholarship_validation_feedback(int $feedbackId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM scholarship_validation_feedback WHERE id = :id');
    return $stmt->execute(['id' => $feedbackId]);
}

function validate_scholarship_feedback_rating(int $rating): bool {
    return $rating >= 0 && $rating <= 5;
}

function get_scholarship_feedback_summary(int $validationId): array {
    $pdo = get_db();
    
    // Get average ratings
    $ratings = get_scholarship_feedback_average_ratings($validationId);
    
    // Get all feedback entries
    $feedback = get_all_scholarship_feedback_for_validation($validationId);
    
    // Compile summary
    $summary = [
        'validation_id' => $validationId,
        'total_responses' => count($feedback),
        'average_process_rating' => $ratings['avg_process_rating'],
        'average_support_rating' => $ratings['avg_support_rating'],
        'feedback_entries' => $feedback,
        'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
    ];
    
    return $summary;
}
