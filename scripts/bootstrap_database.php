<?php
$bootstrapEnabled = filter_var(getenv('DB_BOOTSTRAP') ?: 'false', FILTER_VALIDATE_BOOLEAN);
if (!$bootstrapEnabled) {
    exit(0);
}

$host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1');
$port = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306');
$db = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'support_system');
$user = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$password = getenv('DB_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: '');
$sqlFile = dirname(__DIR__) . '/CURRENT.SQL';

if (!is_file($sqlFile)) {
    fwrite(STDERR, "Database dump not found: {$sqlFile}\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );

    $tableCount = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    if ($tableCount > 0) {
        fwrite(STDOUT, "Database already contains {$tableCount} table(s); skipping bootstrap.\n");
        exit(0);
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "Database dump is empty.\n");
        exit(1);
    }

    $pdo->exec($sql);
    fwrite(STDOUT, "Database bootstrap completed from CURRENT.SQL.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Database bootstrap failed: {$error->getMessage()}\n");
    exit(1);
}
