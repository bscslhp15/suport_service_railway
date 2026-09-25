<?php
// Test the search_students.php endpoint functionality directly
require_once '../includes/db.php';

$pdo = get_db();
$query = 'TEACHER';
$searchTerm = '%' . $query . '%';

echo "=== Testing search for '{$query}' ===\n\n";

// Search students
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

// Search teachers
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

// Combine results
$results = array_merge($students, $teachers);
$results = array_slice($results, 0, 10);

echo "Found " . count($results) . " results:\n\n";
foreach ($results as $result) {
    echo "JSON: " . json_encode($result) . "\n";
}

echo "\n=== Search Results (JSON format) ===\n";
echo json_encode([
    'success' => true,
    'results' => $results
], JSON_PRETTY_PRINT);
?>