<?php
require_once '../includes/db.php';
$pdo = get_db();

echo "=== TEACHERS IN DATABASE ===\n";
$stmt = $pdo->query('SELECT id, full_name, employee_id, course_year FROM users WHERE role = "teacher" LIMIT 5');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($results)) {
    echo "No teachers found in database\n";
} else {
    foreach ($results as $row) {
        echo "ID: {$row['id']}, Name: {$row['full_name']}, Employee ID: {$row['employee_id']}, Course: {$row['course_year']}\n";
    }
}

echo "\n=== Testing search for 'ley' (should find student) ===\n";
$stmt = $pdo->prepare('SELECT id, full_name, student_id, course_year, "student" as role FROM users WHERE role = "student" AND (full_name LIKE ? OR student_id LIKE ?)');
$stmt->execute(['%ley%', '%ley%']);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row) . "\n";
}
?>