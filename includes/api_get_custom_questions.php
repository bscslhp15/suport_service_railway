<?php
/**
 * API: Get custom survey questions based on employment status
 * Used by alumni_tracer_form.php to dynamically load questions
 */

require_once '../includes/db.php';

header('Content-Type: application/json');

$employmentStatus = $_GET['status'] ?? null;

if (!$employmentStatus) {
    echo json_encode(['error' => 'No employment status provided', 'questions' => []]);
    exit;
}

try {
    $conn = get_db();
    
    // Get custom questions for the employment status
    $stmt = $conn->prepare("
        SELECT id, question_text, question_type, answer_choices, is_removable
        FROM ssaa_alumni_survey_questions
        WHERE employment_status IN ('all', ?)
        AND is_removable = 1
        ORDER BY is_default DESC, order_index ASC
    ");
    $stmt->execute([$employmentStatus]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'status' => $employmentStatus,
        'questions' => $questions,
        'count' => count($questions)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'questions' => []
    ]);
}
?>
