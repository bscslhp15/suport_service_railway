<?php
require_once '../includes/db.php';
$pdo = get_db();
$stmt = $pdo->query('SELECT id, full_name, student_id, course_year FROM users WHERE role = "student" LIMIT 5');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo 'All students in database:' . PHP_EOL;
foreach ($results as $student) {
    echo '- ' . $student['full_name'] . ' (' . $student['student_id'] . ')' . PHP_EOL;
}
?>