<?php
require_once '../includes/db.php';
$pdo = get_db();

echo "=== USERS TABLE STRUCTURE ===\n";
$stmt = $pdo->query('DESCRIBE users');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "{$row['Field']}\n";
}
?>