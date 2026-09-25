<?php
/**
 * Initialize announcement interaction tables - Run once
 */
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = get_db();

    // Create announcement_likes table
    $sql1 = "
    CREATE TABLE IF NOT EXISTS announcement_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        announcement_type VARCHAR(50) NOT NULL,
        announcement_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (user_id, announcement_type, announcement_id),
        INDEX idx_announcement (announcement_type, announcement_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Create announcement_comments table
    $sql2 = "
    CREATE TABLE IF NOT EXISTS announcement_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_name VARCHAR(255) NOT NULL,
        user_course VARCHAR(255),
        announcement_type VARCHAR(50) NOT NULL,
        announcement_id INT NOT NULL,
        comment_text LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_announcement (announcement_type, announcement_id),
        INDEX idx_user (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql1);
    echo "<p style='color: green; font-weight: bold;'>✓ Created announcement_likes table</p>";

    $pdo->exec($sql2);
    echo "<p style='color: green; font-weight: bold;'>✓ Created announcement_comments table</p>";

    echo "<p style='color: green; font-size: 1.2em;'><strong>Tables created successfully!</strong></p>";
    echo "<p><a href='dashboard/school_announcements.php'>Go to Announcements</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
