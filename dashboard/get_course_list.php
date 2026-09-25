<?php
require_once __DIR__ . '/../includes/session.php';
require_login();

header('Content-Type: application/json');

try {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT DISTINCT course FROM ssaa_alumni WHERE course IS NOT NULL AND course != '' ORDER BY course ASC");
    $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['success' => true, 'courses' => $courses]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch courses.']);
}