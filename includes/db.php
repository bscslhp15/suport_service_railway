<?php
function get_db() {
    static $pdo;
    if ($pdo === null) {
        $configFile = __DIR__ . '/db_config.php';
        if (is_file($configFile)) {
            require_once $configFile;
        }

        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
        $db = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'support_system');
        $user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
        $pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
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
