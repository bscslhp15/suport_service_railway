<?php
require 'includes/db.php';
$pdo = get_db();
$stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = 16');
$stmt->execute(['hash' => '$2y$10$wXqmtVcAjeK1yxX0R8O84.Z11w/ZoEekMZnouhoV.iF2AhTtyKW9e']);
echo "✓ Updated counselor password to: TestPassword123\n";
?>
