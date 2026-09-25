<?php
require_once 'includes/functions.php';

$pdo = get_db();

// Generate password hash for "123456"
$new_password = "123456";
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update the user's password and reset failed attempts
$stmt = $pdo->prepare('UPDATE users SET password_hash = ?, failed_attempts = 0, lockout_until = NULL WHERE email = ?');
$stmt->execute([$password_hash, 'fernand2fable@gmail.com']);

echo "✅ Password reset successfully!" . PHP_EOL;
echo "Email: fernand2fable@gmail.com" . PHP_EOL;
echo "Password: 123456" . PHP_EOL;
echo "Account unlocked - you can now login" . PHP_EOL;
?>
