<?php
$pass = password_hash('TestPassword123', PASSWORD_BCRYPT);
echo "UPDATE users SET password_hash = '$pass' WHERE id = 16;\n";
?>
