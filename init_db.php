<?php
$root = __DIR__;
$sqlFile = $root . '/db.sql';
if (!file_exists($sqlFile)) {
    die('SQL file not found.');
}

$content = file_get_contents($sqlFile);
$pdo = null;
try {
    $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Split the SQL file into individual statements
    $statements = array_filter(array_map('trim', explode(';', $content)), function($stmt) {
        return !empty($stmt) && strlen($stmt) > 1;
    });

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement . ';');
        }
    }

    echo "Database initialized successfully.";
} catch (PDOException $ex) {
    echo 'Database initialization failed: ' . $ex->getMessage();
}
