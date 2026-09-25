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
$resetEnabled = filter_var(getenv('DB_BOOTSTRAP_RESET') ?: 'false', FILTER_VALIDATE_BOOLEAN);

if (!is_file($sqlFile)) {
    fwrite(STDERR, "Database dump not found: {$sqlFile}\n");
    exit(1);
}

try {
    $database = new mysqli($host, $user, $password, $db, (int) $port);
    if ($database->connect_errno) {
        throw new RuntimeException($database->connect_error);
    }
    $database->set_charset('utf8mb4');

    if ($resetEnabled) {
        $tablesResult = $database->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()');
        $tables = $tablesResult ? $tablesResult->fetch_all(MYSQLI_NUM) : [];
        $database->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            $quotedTable = '`' . str_replace('`', '``', (string) $table[0]) . '`';
            $database->query("DROP TABLE IF EXISTS {$quotedTable}");
        }
        $database->query('SET FOREIGN_KEY_CHECKS=1');
    }

    $countResult = $database->query('SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = DATABASE()');
    $tableCount = (int) $countResult->fetch_assoc()['table_count'];
    if ($tableCount > 0) {
        fwrite(STDOUT, "Database already contains {$tableCount} table(s); skipping bootstrap.\n");
        exit(0);
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "Database dump is empty.\n");
        exit(1);
    }

    if (!$database->multi_query($sql)) {
        throw new RuntimeException($database->error);
    }
    while ($database->more_results() && $database->next_result()) {
        if ($database->errno) {
            throw new RuntimeException($database->error);
        }
    }
    fwrite(STDOUT, "Database bootstrap completed from CURRENT.SQL.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Database bootstrap failed: {$error->getMessage()}\n");
    exit(1);
}
