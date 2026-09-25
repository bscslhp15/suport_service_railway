<?php
require_once '../includes/db.php';
$pdo = get_db();

echo "=== STUDENTS ===\n";
$stmt = $pdo->query('SELECT id, full_name, student_id, course_year FROM users WHERE role = "student" LIMIT 3');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: {$row['id']}, Name: {$row['full_name']}, Student ID: {$row['student_id']}, Course: {$row['course_year']}\n";
}

echo "\n=== TEACHERS ===\n";
$stmt = $pdo->query('SELECT id, full_name, employee_id, course FROM users WHERE role = "teacher" LIMIT 3');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: {$row['id']}, Name: {$row['full_name']}, Employee ID: {$row['employee_id']}, Course: {$row['course']}\n";
}
?>