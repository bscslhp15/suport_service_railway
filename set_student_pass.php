<?php
require 'includes/db.php';
$pdo = get_db();

// Set password for test student: 'TestPassword123'
$pass_hash = password_hash('TestPassword123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, email_verified = 1 WHERE id = 36');
$stmt->execute(['hash' => $pass_hash]);
echo "✓ Updated student 36 (Ley Harvie Barreto Peralta) password to: TestPassword123\n";
?>
