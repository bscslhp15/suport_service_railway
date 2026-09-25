<?php
require_once '../includes/functions.php';
$pdo = get_db();
$stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "student"');
echo 'Student count: ' . $stmt->fetchColumn() . PHP_EOL;

// Test the search query
$stmt = $pdo->prepare('SELECT id, full_name, course_year FROM users WHERE role = "student" AND full_name LIKE ? LIMIT 5');
$stmt->execute(['%test%']);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo 'Search results: ' . count($students) . PHP_EOL;
print_r($students);
