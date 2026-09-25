<?php
function get_db() {
    static $pdo;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1');
        $db = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'support_system');
        $user = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
        $pass = getenv('DB_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: '');
        $port = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306');
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            
            // Ensure database schema is created
            require_once __DIR__ . '/clinic_functions.php';
            ensure_clinic_schema();
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}
