<?php
require_once __DIR__ . '/db.php';

try {
    $db = get_db();
    
    // Create carousel images table
    $sql = "
        CREATE TABLE IF NOT EXISTS carousel_images (
            id INT PRIMARY KEY AUTO_INCREMENT,
            login_type ENUM('student', 'teacher') NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_path (image_path),
            INDEX idx_login_type (login_type),
            INDEX idx_order (display_order)
        )
    ";
    
    $db->exec($sql);
    echo "✓ carousel_images table created successfully";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
?>
