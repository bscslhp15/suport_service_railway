<?php
/**
 * API Endpoint: Get book and service rating averages
 * Returns JSON with average ratings and counts
 */
require_once __DIR__ . '/functions_fixed.php';

header('Content-Type: application/json');

$bookId = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;

if ($bookId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid book_id']);
    exit;
}

try {
    $pdo = get_db();
    
    // Get book rating (average and count)
    $bookRatingStmt = $pdo->prepare('
        SELECT AVG(book_rating) as avg_rating, COUNT(*) as count
        FROM library_feedback
        WHERE book_id = :book_id AND book_rating > 0
    ');
    $bookRatingStmt->execute(['book_id' => $bookId]);
    $bookRatingData = $bookRatingStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get service rating (average and count)
    $serviceRatingStmt = $pdo->prepare('
        SELECT AVG(service_rating) as avg_rating, COUNT(*) as count
        FROM library_feedback
        WHERE book_id = :book_id AND service_rating > 0
    ');
    $serviceRatingStmt->execute(['book_id' => $bookId]);
    $serviceRatingData = $serviceRatingStmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'book_rating_avg' => $bookRatingData['avg_rating'] ? round((float)$bookRatingData['avg_rating'], 1) : null,
        'book_rating_count' => (int)($bookRatingData['count'] ?? 0),
        'service_rating_avg' => $serviceRatingData['avg_rating'] ? round((float)$serviceRatingData['avg_rating'], 1) : null,
        'service_rating_count' => (int)($serviceRatingData['count'] ?? 0),
    ];
    
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
