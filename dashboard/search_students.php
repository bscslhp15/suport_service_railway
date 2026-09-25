<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../includes/session.php';
    require_login();

    $user = current_user();

    // Only counselors can search
    if ($user['role'] !== 'teacher' || $user['head_service'] !== 'guidance') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    require_once __DIR__ . '/../includes/functions.php';

    $pdo = get_db();
    $query = trim($_GET['q'] ?? '');

    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Query too short']);
        exit;
    }

    // Search both students and teachers by name or ID
    $searchTerm = '%' . $query . '%';
    
    // Search in students table
    $studentStmt = $pdo->prepare(
        "SELECT id, full_name, student_id, course_year, 'student' as role
         FROM users 
         WHERE role = 'student' AND (
            full_name LIKE ? OR 
            student_id LIKE ?
         )"
    );
    $studentStmt->execute([$searchTerm, $searchTerm]);
    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search in teachers table
    $teacherStmt = $pdo->prepare(
        "SELECT id, full_name, employee_id, course_year, 'teacher' as role
         FROM users 
         WHERE role = 'teacher' AND (
            full_name LIKE ? OR 
            employee_id LIKE ?
         )"
    );
    $teacherStmt->execute([$searchTerm, $searchTerm]);
    $teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine results - students first, then teachers
    $results = array_merge($students, $teachers);
    // Limit to 10 total results
    $results = array_slice($results, 0, 10);

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
